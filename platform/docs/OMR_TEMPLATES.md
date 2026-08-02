# OMR — Templates de Cartão-Resposta, Geração e Leitura

> Documentação técnica do subsistema OMR (Optical Mark Recognition): templates dinâmicos de
> cartão-resposta, vínculo com avaliações, geração adaptativa do cartão, QR Code seguro e
> leitura/correção. Reflete a implementação real (Fases 1–4) e o design da Fase 5 (offline).
>
> Stack: Laravel 12 · PHP 8.2 · Blade · Vite · Alpine.js · Konva.js (editor) · OpenCV.js +
> jsQR (leitura, client-side). SGBD em dev: SQLite.

---

## 1. Visão geral

Um **template** descreve a geometria e a identidade visual de um cartão-resposta. Cada
**avaliação** (`/exams`) é vinculada a um template; ao exportar, o sistema gera um cartão que
**se adapta ao número real de questões** (dentro dos limites do template) e embute um **QR Code
seguro**. Na leitura, o motor detecta os 4 marcadores, corrige a perspectiva, lê as bolhas pela
geometria do template (embarcada no QR) e a correção compara com o gabarito.

Princípio central — **fonte de verdade única de geometria**: o mesmo conjunto de parâmetros
(`layout_config`) é usado para (a) **imprimir** o cartão (posicionamento absoluto na blade) e
(b) **ler** o cartão (o array `g` no QR). Impressão e leitura coincidem por construção.

```
/exams (cria prova)
   └─ vincula card_template_id (+ versão)
        └─ Exportar Cartão  ──►  AnswerSheetGeneratorService
                                   ├─ buildPageGeometry(template.layout_config) → g + posições
                                   ├─ adapta nº de colunas ao nº real de questões
                                   ├─ QR: ids + template + g + gabarito CIFRADO + HMAC
                                   └─ PDF (blade absoluta)
   (impressão → aluno preenche → foto/scan)
        └─ /institution/omr (Web Scan)  ──►  OmrBrowserEngine (OpenCV.js, no navegador)
                                              ├─ detecta 4 marcadores → warp (perspectiva)
                                              ├─ lê QR (jsQR) → g, tpl_id/tpl_v
                                              ├─ Otsu + ROIs por `g` → respostas (índice visual)
                                              └─ POST omr_payload
        └─ OmrController::store()  ──►  verifica HMAC + integridade (exam/cópia) + vincula template
        └─ Conferência (review)  ──►  ajustar + atribuir aluno + confirmar
        └─ OmrGradingService::grade()  ──►  reverse-map (options_map) → nota
```

---

## 2. Estrutura dos templates (modelo de dados)

### `omr_templates` (modelo unificado e versionado)
Campos relevantes (migração `..._evolve_omr_templates_for_card_templates`):

| Campo | Tipo | Uso |
|---|---|---|
| `name`, `slug` | string | identificação |
| `owner_type` / `owner_id` | morph nullable | dono: `App\Models\User` (professor), `Organization` ou null (sistema) |
| `organization_id` | FK nullable | escopo multi-tenant |
| `visibility_scope` | string | `private` · `org_public` · `system` |
| `is_system`, `is_default` | bool | template padrão do sistema |
| **`layout_config`** | json | **geometria** (ver §5) — fonte de verdade |
| `header_config` | json | `{title, show_institution, show_qr}` |
| `logo_path` | string | logotipo (disk `public`, pasta `omr-logos`) |
| `max_questions`, `max_columns` | int | limites (a prova pode usar menos) |
| `columns`, `rows_per_column`, `max_options` | int | espelho do layout |
| `current_version` | int | versão atual do layout |
| campos legados | — | `corner_points_json`, `thresholds_json`, `width/height` (modelo antigo de ROI; não usados na leitura por `g`) |

### `omr_template_versions` (snapshots)
`omr_template_id`, `version`, `layout_config` (snapshot), `header_config`, `logo_path`.
Cada **save no editor cria uma nova versão**. A prova guarda a versão com que foi gerada
(`exams.card_template_version`) → **cartões antigos continuam legíveis** mesmo após editar o
template (não-regressão).

### `exams` (vínculo)
`card_template_id` (FK → omr_templates) + `card_template_version` (int). `answer_sheet_type_slug`
é mantido por compat de exames antigos (mapeado ao template padrão na exportação).

### Models
`App\Models\OmrTemplate` — `owner()` (morphTo), `versions()`, `scopeVisible($user)`,
`currentLayout()`/`layoutForVersion($v)`. `App\Models\OmrTemplateVersion`. `Exam::cardTemplate()`.

---

## 3. Permissões e visibilidade

- **Visibilidade** (`OmrTemplate::scopeVisible($user)`): retorna templates do **sistema** +
  da(s) **organização(ões) ativas** do usuário (`activeOrganizations()` + FK legado) + de sua
  **propriedade** (`owner_type=User, owner_id=user`). `global_admin` vê tudo. Espelha o padrão de
  `QuestionController::index`. Usado em `OmrTemplateController::index` e no select de `/exams`.
- **Edição** (`OmrTemplateController::authorizeEdit`): só o owner, a org do usuário ou
  `global_admin`; templates **do sistema são somente leitura**; o padrão não pode ser excluído.
- **Rotas**: grupo `institution.omr.*` protegido por `role:institution_admin|teacher|global_admin`.
  *Refinamento futuro*: aplicar `inst_perm:view_omr`/`manage_omr` (middleware
  `CheckInstitutionPermission`) quando os papéis de instituição estiverem semeados com essas
  permissões — hoje evitamos para não bloquear professores sem papel configurado.

---

## 4. Fluxo: criação da prova → geração → leitura → correção

1. **Criar/editar prova** em `/exams` (questões com `content.correct_option`).
2. **Selecionar template**: painel "Cartão-Resposta (OMR)" em `exams/show` (select de templates
   visíveis). `ExamController::exportAnswerSheet` resolve o template (form > vínculo > padrão),
   valida visibilidade e registra `card_template_id` + versão na prova.
3. **Gerar cópias** (`ExamPrintService::generateCopies`): cria `ExamCopy` com `questions_map`
   (ordem impressa) e `options_map` (embaralhamento de alternativas), `validation_hash` único.
4. **Gerar PDF** (`AnswerSheetGeneratorService::generate($exam, $copy, OmrTemplate, $scanMode)`):
   geometria de `template.currentLayout()`, adapta colunas, monta o QR, renderiza a blade
   absoluta `pdf/answer-sheet-essential.blade.php`.
5. **Imprimir → preencher → fotografar/scan.**
6. **Web Scan** (`/institution/omr`): `OmrBrowserEngine` lê e envia `omr_payload`.
7. **`OmrController::store()`**: verifica QR, vincula template, mapeia respostas, cria o `OmrScan`
   (status `reviewing`).
8. **Conferência** (`omr/review`): ajustar respostas + atribuir aluno + confirmar.
9. **`OmrGradingService`**: gera a nota e a `ExamSubmission`.

---

## 5. Geometria do cartão (`layout_config` e o array `g`)

`AnswerSheetGeneratorService::buildPageGeometry($layout, $questions, $qStart)` é o cálculo único.

- **Frame**: retângulo cujos **cantos são os centros dos 4 marcadores fiduciais**. Parâmetros
  (mm): `frame_left_mm`, `frame_top_mm`, `frame_width_mm`; altura derivada de
  `grid_pad_top_mm + rows*row_spacing_mm`. Marcadores de `frame_fiducial_mm` (≈8 mm — maiores que
  os marcadores do QR, ~6 mm, para a detecção pegar os 4 certos).
- **Bolhas** (posição absoluta, espelhada na impressão e no `g`):
  - `col = floor((n-1)/rows)`, `row = (n-1) % rows`
  - `x = frame_left + col*(frame_width/cols) + cell_indent_mm + opt*(bubble+option_gap)`
  - `y = frame_top + grid_pad_top_mm + row*row_spacing_mm`
- **`g`** (embarcado no QR) = frações **do frame** ×10000:
  `[startXf, startYf, colSpacingf, rowSpacingf, bubbleSizef, optionSpacingf]`.
  O motor aplica `g` ao frame transformado (warpado p/ 2480×3508) → as ROIs caem exatamente
  sobre as bolhas (erro medido < 0,5 px).
- **Adaptação dinâmica**: `effectiveColumns = min(max_columns, ceil(numQ / rows_per_column))`.
  O cartão usa só as colunas necessárias; o QR leva `cols`/`rpp` efetivos. Erro claro se
  `numQ > max_questions`.

> A letra dentro da bolha é impressa em cinza-claro (#c7ccd1) para não contar como preenchimento
> na binarização.

---

## 6. Formato dos metadados do QR Code

JSON (compacto) gerado em `AnswerSheetGeneratorService::generate` + `QrCodeSigningService::buildPayload`:

| Campo | Significado |
|---|---|
| `e` | exam_id |
| `c` | copy_id |
| `h` | `ExamCopy.validation_hash` (vínculo do cartão à cópia) |
| `p` / `pt` | página / total de páginas |
| `qs` / `qe` | 1ª / última questão da página (1-based) |
| `v` | versão do formato do payload (**3** = template-aware + gabarito cifrado) |
| `cols` / `rpp` | colunas efetivas / linhas por coluna |
| `tpl` | slug do template (debug/humano) |
| **`tpl_id`** / **`tpl_v`** | id + versão do template (vínculo de leitura — FR-13) |
| **`g`** | geometria (frações do frame ×10000) — ver §5 |
| **`gab_enc`** | **gabarito CIFRADO** (AES-256-GCM, base64) — nunca texto puro |
| `pts` | pontos por questão (modo `qr_embedded`) |
| **`chk`** | **assinatura HMAC** (16 hex) do payload |

Modos de leitura (`scanMode`): `preloaded` (só identificação, sem gabarito), `hybrid` (gabarito
cifrado como fallback), `qr_embedded` (gabarito cifrado + pontos).

---

## 7. Segurança do QR Code (`QrCodeSigningService`)

### Chave por organização (sem default fixo)
`rawKey(orgId)` = `sha256( org.omr_hmac_secret  ||  (vazio→APP_KEY)  . '|omr|' . orgId )` → 32 bytes.
Nunca usa o antigo `'default-change-me'`. Cada organização tem sua própria chave determinística.

### Assinatura (integridade) — HMAC-SHA256
`chk = substr(hmac_sha256(canonical, key), 0, 16)`, onde
`canonical = e | c | h | p | tpl_id | tpl_v | gab_enc`. Qualquer adulteração (inclusive trocar o
`tpl_id` ou o `gab_enc`) invalida `chk`. Verificada no **servidor** (`verifyPayload`).

### Sigilo do gabarito — AES-256-GCM
`gab_enc = base64( iv(12) || tag(16) || ciphertext )`, `openssl_encrypt('aes-256-gcm')`.
`gab` (plaintext) é o array de **índices visuais** da resposta correta por questão; é removido do
payload após cifrar. `decryptGabarito` devolve o array; adulteração → `null` (a tag GCM falha).

> **As respostas corretas nunca trafegam em texto puro no QR.** Mesmo quem decodificar o QR com um
> leitor comum vê apenas `gab_enc` cifrado. A correção **online** sequer usa o `gab` do QR — o
> servidor recomputa pelo banco (ver §8). O `gab_enc` existe para **correção offline** (§11).

---

## 8. Vínculo seguro questão ↔ resposta (embaralhamento)

O cartão pode embaralhar **questões** e **alternativas** por cópia, mantendo a correção íntegra:

- **`ExamCopy.questions_map`**: array de `question_id` na **ordem impressa**. A posição física `p`
  na folha → `questions_map[p-1]` = id da questão. (Em `store()`, é assim que a resposta lida na
  posição `p` é atribuída à questão certa — nunca pela ordem da prova.)
- **`ExamCopy.options_map[question_id]`**: mapeia **índice visual → índice original** da
  alternativa. Ex.: `[1,0]` significa "bolha visual 0 = alternativa original 1".
- **Espaço das respostas**: `detected_answers`/`confirmed_answers` guardam o **índice visual**
  (0=A, 1=B…) — a posição física marcada. A conversão visual→original ocorre **uma única vez** no
  `OmrGradingService::grade()` via `options_map`, comparando com `content.correct_option`.
- **Gabarito no QR**: `gab[i] = array_search(correct_option, options_map[qid])` = a posição visual
  da alternativa correta naquela cópia → cifrado em `gab_enc`. Permite correção offline sem
  expor a resposta.

Assim, o vínculo questão→alternativa→template→avaliação sobrevive ao embaralhamento, e a correção
é a mesma online (recomputada do banco) e offline (via `gab_enc` decifrado).

---

## 9. ROIs e leitura (motor OMR)

Motor client-side em `resources/js/omr-core/` (TypeScript, compilado pelo Vite; Web Worker):

1. **Pré-processamento**: cinza → blur → threshold (para detecção de marcadores).
2. **Marcadores**: `findCornerMarkers` acha os 4 maiores quadrados (aspect ~1) → ordena TL,TR,BR,BL.
3. **Perspectiva**: `warp` (homografia) da imagem **em cinza** para 2480×3508 usando os 4 cantos.
   (Bug histórico: warpar o binário zerava a leitura — hoje warpa o cinza.)
4. **Leitura das bolhas** (`readBubbles`): binarização **global Otsu** (mede preenchimento sólido
   de forma estável), ROIs computadas por `g` (frações do frame), amostrando o **interior** da
   bolha (encolhido ~15% para ignorar o anel). `score = pixels_escuros / área`.
5. **Classificação** por thresholds (`mark` 0.45, `blank` 0.15, `uncertain` 0.25–0.40): `OK`,
   `BLANK`, `UNCERTAIN`, `DOUBLE`.
6. **Saída** (`omr_payload`): `answers[{q, selected, status, scores}]` + `quality` + `qrData`.

> ROIs **paramétricas** (derivadas de `g`) cobrem o caso uniforme. ROIs **por questão**
> (`OmrTemplateQuestion.rois_json`) para layouts irregulares: ver §13 (futuro).

---

## 10. Critérios de validação da leitura

Em `OmrController::store()`:
1. **Assinatura HMAC** (`verifyPayload`) — rejeita cartão adulterado / de outra organização.
2. **Integridade**: `qr.e == exam_id` (cartão é desta prova) e `qr.h == copy.validation_hash`
   (é desta cópia). Falha → `rejectScan()` (rollback + remove imagem + erro).
3. **Vínculo de template** (FR-13): grava `omr_scans.omr_template_id` + `layout_version` do QR.
4. **Qualidade do motor**: 4/4 marcadores; contagem de `UNCERTAIN`/`DOUBLE`/`BLANK` → confiança
   por questão (coloração na conferência).
5. **Conferência humana obrigatória**: o scan entra como `reviewing`; a nota só é gerada ao
   **confirmar com o aluno selecionado** (`confirm()` → `grade()`).

QR legado (sem `chk`/`tpl_id`) é **tolerado** (não bloqueia) para não regredir cartões antigos.

---

## 11. Uso offline

**Hoje (implementado):**
- A **leitura/detecção é 100% client-side** (OpenCV.js + jsQR no navegador/dispositivo) — não
  depende de rede para detectar marcadores, ler o QR e as bolhas.
- O **`gab_enc`** (gabarito cifrado) viaja no próprio cartão, permitindo correção sem servidor —
  **desde que o dispositivo tenha a chave da organização** para decifrar/verificar.

**Design do provisionamento de chave (a implementar no app DuoScanner):**
- O servidor deriva a chave de `organizations.omr_hmac_secret`. Para correção **offline** num
  dispositivo confiável, o app autenticado (Sanctum) deve **provisionar a chave** uma vez (no
  login/sync), guardando-a em **armazenamento seguro do dispositivo** (Keychain/Keystore).
- Critérios de segurança: transporte só por HTTPS; entrega apenas a usuários com permissão
  `manage_omr` e vínculo ativo na org; rotação de `omr_hmac_secret` invalida cartões antigos
  (por isso a chave entra na assinatura). **Não** expor a chave a perfis sem permissão.
- Com a chave no dispositivo, o app: lê o QR → `verifyPayload` (HMAC) → `decryptGabarito`
  (`gab_enc`) → corrige offline → sincroniza quando houver rede.

> Por ser uma funcionalidade de dispositivo (mobile, não testável neste ambiente web), o
> provisionamento fica documentado como design; a infraestrutura criptográfica server-side
> (`QrCodeSigningService`) já está pronta para suportá-lo.

---

## 12. Editor visual + auto-detecção

- **Editor** (`omr/templates/editor.blade.php`): formulário (capacidade + geometria + branding) à
  esquerda; **canvas Konva.js** à direita com preview ao vivo que **espelha `buildPageGeometry`**
  (marcadores, QR, grade de bolhas, números). Marcadores TL/BR arrastáveis ajustam o frame.
  `store/update` montam `layout_config` e criam/incrementam a **versão**.
- **Auto-detecção** (ponto de partida): upload de **imagem ou PDF** → OpenCV.js
  (`findCornerMarkers`) acha os marcadores → calcula `frame_*` (px→mm, A4); `jsQR` lê o QR e, se
  tiver `g`/`rpp`/`cols`, decodifica para os campos da grade. PDF via pdf.js (1ª página).
  Heurística de visão computacional (sem modelo treinado); os campos são a fonte de verdade.

---

## 13. Limitações conhecidas e próximos passos

- **ROI por questão** (`OmrTemplateQuestion.rois_json`): suportado no schema, mas o motor lê pela
  grade paramétrica (`g`). Layouts irregulares exigiriam o motor honrar ROIs por questão
  (mudança em `omr-core` + rebuild).
- **Multi-página** (`qStart > 1`): a geração suporta; o mapeamento de leitura é garantido para
  página única (≤ `cols*rows`). Validar multi-página antes de usar em provas muito grandes.
- **Verificação de assinatura no cliente**: inviável sem provisionar a chave (§11) — por isso a
  verificação criptográfica é server-side. No offline, depende do provisionamento.
- **`inst_perm` nas rotas**: aplicar quando os papéis de instituição tiverem `view_omr`/`manage_omr`.
- Migrar/aposentar formalmente `AnswerSheetType` (essential/detailed) — hoje só o template padrão
  e os personalizados são usados; o slug legado mapeia ao padrão.

---

## 14. Arquivos-chave

| Camada | Arquivo |
|---|---|
| Geração + geometria + g | `app/Services/AnswerSheetGeneratorService.php` |
| Segurança do QR | `app/Services/QrCodeSigningService.php` |
| Cópias/embaralhamento | `app/Services/ExamPrintService.php` |
| Correção | `app/Services/OmrGradingService.php` |
| Vínculo + exportação | `app/Http/Controllers/ExamController.php` (`exportAnswerSheet`) |
| Leitura + integridade | `app/Http/Controllers/OmrController.php` (`store`, `review`, `confirm`) |
| Templates CRUD/editor | `app/Http/Controllers/OmrTemplateController.php` |
| Models | `app/Models/OmrTemplate.php`, `OmrTemplateVersion.php`, `Exam.php`, `ExamCopy.php`, `OmrScan.php` |
| Motor (CV) | `resources/js/omr-core/{engine,worker,qr_reader,index}.ts` |
| Editor (UI) | `resources/views/omr/templates/editor.blade.php` |
| Cartão (PDF) | `resources/views/pdf/answer-sheet-essential.blade.php` |
| Seed do padrão | `database/seeders/OmrTemplateSeeder.php` |

---

*Documento gerado na Fase 5. Fases 1–4 implementadas e validadas; Fase 5 entrega esta
documentação + o design de provisionamento de chave offline.*
