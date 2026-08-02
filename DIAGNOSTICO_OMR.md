# DIAGNÓSTICO OMR — Sistema de Leitura de Cartão-Resposta

**Data:** 2026-03-03
**Escopo:** Web (Laravel 12 — `platform/`) + Mobile (React Native/Expo — `duoscanner/`)

---

## 1. RESUMO EXECUTIVO

O módulo OMR possui **dois problemas críticos** e **várias lacunas estruturais**:

| # | Problema | Severidade | Impacto |
|---|---------|-----------|---------|
| 1 | Bug de tipagem no grading (T/F) | **CRÍTICO** | Todas as questões V/F são corrigidas errado |
| 2 | Engine OMR mobile incompleta (3 módulos inexistentes) | **CRÍTICO** | App crasha ou usa fallback inútil |
| 3 | Web não tem OMR real | **ALTO** | Upload vai direto pra revisão manual sem detecção |
| 4 | Sem overlay visual | **ALTO** | Impossível verificar se alinhamento está correto |
| 5 | Sem templates no banco | **MÉDIO** | ROIs/thresholds hardcoded, sem calibração |
| 6 | Sem engine Python/OpenCV | **MÉDIO** | Processamento server-side inexistente |
| 7 | Sem testes OMR | **MÉDIO** | Nenhum teste unitário para grading/detecção |
| 8 | Sem debug outputs | **BAIXO** | Impossível diagnosticar falhas de leitura |

---

## 2. INVENTÁRIO DE ARQUIVOS OMR

### 2.1 Web (Laravel — `platform/`)

```
app/
├── Http/Controllers/
│   ├── OmrController.php              # Web UI (upload, review, confirm, reject)
│   └── Api/
│       ├── OmrApiController.php       # API mobile (store pages, confirm, reject)
│       └── OmrScanPageController.php  # Vazio (11 linhas, sem métodos)
├── Http/Middleware/
│   └── CheckOmrApiAccess.php          # Verifica feature mobile_omr_scanning
├── Models/
│   ├── OmrScan.php                    # Scan principal
│   ├── OmrScanPage.php               # Páginas multi-folha
│   ├── OmrCalibration.php            # Offset/scale/rotation por exam
│   ├── OmrAuditLog.php               # Log de alterações
│   ├── Exam.php                       # Prova (questions via pivot)
│   ├── ExamCopy.php                   # Cópia embaralhada (questions_map, options_map)
│   ├── ExamSubmission.php             # Submissão do aluno
│   └── ExamAnswer.php                 # Resposta por questão
├── Services/
│   └── OmrGradingService.php          # ★ Correção de notas (BUG AQUI)
├── Jobs/
│   └── ConsolidateAnswersJob.php      # Consolida páginas → OmrScan
database/migrations/
│   ├── 2026_02_23_*_create_omr_scans_table.php
│   ├── 2026_03_01_*_add_copy_id_and_source.php
│   ├── 2026_03_03_*_add_layout_fields.php
│   ├── 2026_03_03_*_create_omr_scan_pages_table.php
│   ├── 2026_03_03_*_create_omr_calibrations_table.php
│   ├── 2026_03_03_*_add_omr_hmac_secret.php
│   ├── 2026_03_03_*_create_omr_audit_logs_table.php
│   └── 2026_03_03_*_add_missing_columns.php
resources/views/omr/
│   ├── index.blade.php                # Lista de scans
│   ├── upload.blade.php               # Upload de arquivo
│   ├── webscan.blade.php              # Captura por webcam
│   └── review.blade.php              # ★ Tela de revisão manual (BUG AQUI)
tests/Feature/OMR/
│   └── WebScanTest.php                # Apenas 2 testes básicos
```

### 2.2 Mobile (React Native — `duoscanner/`)

```
src/lib/
├── omr-processor.ts        # ★ Pipeline OMR 8 etapas (INCOMPLETO)
├── png-decoder.ts           # Decodificador PNG puro JS (OK)
├── template-registry.ts    # Medidas físicas do layout (mm)
├── qr-parser.ts            # Parser de QR Code do cartão (OK)
├── grading.ts              # Correção client-side (OK)
└── image-utils.ts          # Utilitários de imagem (OK)
src/services/
├── api.ts                   # Axios config
├── omr.ts                   # Endpoints OMR API
└── sync-service.ts          # Sincronização offline→server
app/scan/
├── camera.tsx               # Captura + detecção QR
├── adjust.tsx               # Crop/zoom/rotação manual
├── review-marks.tsx        # ★ Exibição de resultados OMR
├── manual-edit.tsx          # Edição manual de respostas
├── review-data.tsx          # Seleção de aluno
└── result.tsx               # Resultado final + notas
src/store/
└── scan-store.ts            # Estado Zustand com persistência
```

### 2.3 Engine Python/OpenCV

**NÃO EXISTE.** Todo processamento OMR está no mobile (TypeScript) e mesmo assim incompleto.

---

## 3. BUG CRÍTICO #1 — TIPAGEM NO GRADING (T/F)

### 3.1 Localização

**Arquivo:** `platform/app/Services/OmrGradingService.php` linhas 35 e 74-75

### 3.2 Código problemático

```php
// Em gradeAnswers() e grade():
$isCorrect = (int) $originalAnswer === (int) ($question->content['correct_option'] ?? -1);
```

### 3.3 Por que falha

Para questões `true_false`, os valores passados pelo frontend são **strings**:

| Valor recebido | `(int)` resultado | Esperado |
|---------------|-------------------|----------|
| `"true"` | `0` | 1 (verdadeiro) |
| `"false"` | `0` | 0 (falso) |
| `true` (bool) | `1` | 1 |
| `false` (bool) | `0` | 0 |
| `"1"` | `1` | 1 |
| `"0"` | `0` | 0 |

**Resultado:** `(int)"true"` = `0` e `(int)"false"` = `0`. Ambos viram 0.
Se `correct_option` = `0` (falso), AMBOS são marcados como corretos.
Se `correct_option` = `1` (verdadeiro), NENHUM é marcado como correto.
→ **100% das questões V/F com resposta string estão erradas.**

### 3.4 Frontend agravante

**Arquivo:** `platform/resources/views/omr/review.blade.php` linhas 125-126

```html
<option value="true" ...>V</option>
<option value="false" ...>F</option>
```

O `<select>` envia `"true"` e `"false"` (strings). O backend recebe via `$request->answers` como strings e faz `(int)"true"` = 0.

### 3.5 Impacto

- Toda correção de V/F via web está **100% quebrada**
- Mobile pode ter o mesmo problema se enviar strings "true"/"false" em vez de 1/0
- `ExamSubmission.score` fica errado para qualquer prova com questões V/F

---

## 4. BUG CRÍTICO #2 — ENGINE OMR MOBILE INCOMPLETA

### 4.1 Módulos inexistentes

O `omr-processor.ts` importa 3 módulos que **não existem**:

```typescript
import { detectFiducials, type FiducialResult } from './fiducial-detector';    // NÃO EXISTE
import { computeHomography, invertHomography, warpPerspective, sortCorners } from './homography';  // NÃO EXISTE
import { classifyBubble, selectAnswer, extractROI } from './bubble-classifier'; // NÃO EXISTE
```

### 4.2 Consequência

- `processOMR()` falha na linha ~121 ao chamar `detectFiducials()`
- O fallback `legacyFileSizeProcessing()` usa heurísticas de tamanho de arquivo (inútil para leitura real)
- Nenhuma bolha é lida por pixel, nenhuma homografia é aplicada

### 4.3 O que cada módulo deveria fazer

| Módulo | Responsabilidade |
|--------|-----------------|
| `fiducial-detector.ts` | Encontrar 4 quadrados pretos nos cantos via BFS/flood-fill no pixel data |
| `homography.ts` | Calcular transformação perspectiva (DLT), warp da imagem para retângulo padrão |
| `bubble-classifier.ts` | Extrair ROI de cada bolha, calcular fill ratio, classificar (filled/empty/ambiguous) |

---

## 5. PROBLEMA #3 — WEB SEM OMR REAL

### 5.1 Código atual

**Arquivo:** `platform/app/Http/Controllers/OmrController.php` método `store()`, linhas 90-93:

```php
$scan = OmrScan::create([
    'status' => 'review',                 // Vai direto pra revisão manual
    'detected_answers' => [],              // Sempre vazio!
    'confidence_score' => 0,               // Zero
]);
```

### 5.2 O que deveria acontecer

1. Upload da imagem
2. Criar registro com status `pending`
3. Disparar `ProcessOmrScanJob` na fila
4. Job chama engine OMR (Python CLI ou microserviço)
5. Engine retorna respostas detectadas + scores + qualidade
6. Atualizar scan com respostas e status `review` ou `processed`

### 5.3 O que acontece hoje

Upload → status `review` → **professor preenche tudo manualmente** → confirma → nota.
Não há nenhuma detecção automática no web.

---

## 6. PROBLEMA #4 — SEM OVERLAY VISUAL

### 6.1 Web

`review.blade.php` mostra a imagem do scan em um `<img>` e os selects de resposta ao lado. **Não há canvas/overlay** desenhando:
- Posição dos 4 marcadores detectados
- ROIs das bolhas sobre a imagem
- Indicação visual do que foi lido vs. o que deveria estar

### 6.2 Mobile

`review-marks.tsx` mostra:
- A imagem em um bloco
- Um `BubbleGrid` componente **separado** com seletores de bolha
- **Não sobrepõe** nenhum elemento visual sobre a foto do cartão

### 6.3 Impacto

Sem overlay, é **impossível** para o professor/revisor verificar se o alinhamento está correto. Se a homografia errar por 5px, a leitura fica toda errada e ninguém percebe.

---

## 7. PROBLEMA #5 — SEM TEMPLATES NO BANCO

### 7.1 Estado atual

- Não existem tabelas `templates` ou `template_questions` no banco
- `OmrCalibration` tem apenas offset/scale/rotation por exam (sem ROIs)
- `template-registry.ts` no mobile tem medidas hardcoded em mm
- Nenhum CRUD de template no web

### 7.2 O que falta

| Dado | Onde deveria estar |
|------|-------------------|
| Posições dos 4 cantos (template) | `templates.corner_points_json` |
| ROIs das bolhas por questão | `template_questions.rois_json` |
| Thresholds (mark/blank/uncertain) | `templates.thresholds_json` |
| Largura/altura do template warped | `templates.width`, `templates.height` |
| Variação de alternativas por questão | `template_questions.option_labels_json` |

---

## 8. OUTROS PROBLEMAS

### 8.1 Dupla execução de grading no confirm web

`OmrController::confirm()` chama:
1. `gradeAnswers()` → retorna score sem persistir
2. `grade()` → persiste ExamSubmission + ExamAnswer

Ambos duplicam a lógica. E `grade()` cria `ExamSubmission` via `updateOrCreate`, podendo sobrescrever submissão anterior do mesmo aluno.

### 8.2 ConsolidateAnswersJob — riscos

- Linha ~107: `$exam->organization->users()->first()?->id` — pode dar null
- Status final é `synced` (diferente do web que usa `review`)
- Não valida se as respostas do mobile fazem sentido para as questões do exam

### 8.3 Sem ProcessOmrScanJob

Não existe nenhum job para processar scans via engine OMR. O `ConsolidateAnswersJob` apenas mescla páginas, não faz leitura de imagem.

### 8.4 Testes insuficientes

- Apenas 2 testes em `WebScanTest.php` (acesso à página e rejeição de arquivo inválido)
- Zero testes para grading service
- Zero testes para normalização de respostas
- Zero testes de integração mobile→API→grading
- Zero fixtures de imagens para validar pipeline OMR

---

## 9. PLANO DE CORREÇÃO

### FASE 1 — Correção do Bug de Tipagem (URGENTE)

1. Criar `normalizeAnswer($answer, $questionType)` no backend
2. Corrigir `OmrGradingService.php` — substituir `(int)` por normalização
3. Corrigir `review.blade.php` — usar `value="1"` / `value="0"` para V/F
4. Testes PHPUnit cobrindo todos os formatos de resposta

### FASE 2 — Modelagem de Templates

1. Criar migrations: `templates`, `template_questions`
2. Criar Models com casts JSON
3. Criar CRUD API + UI admin para templates
4. Exportar template.json para a engine

### FASE 3 — Engine OMR Python/OpenCV

1. Criar CLI: `omr_read --image <path> --template <json> --debug <dir>`
2. Pipeline: QR → cantos → homografia → warp → ROIs → scores → classificação
3. Debug outputs: original, gray, thresh, corners, warped, overlay, result.json
4. Testes com fixtures

### FASE 4 — Integração Laravel ↔ Engine

1. Criar `ProcessOmrScanJob`
2. Job exporta template.json, chama CLI, importa resultado
3. Atualizar status: `pending` → `processed` / `NEEDS_REVIEW`
4. Web passa a ter OMR real no upload

### FASE 5 — Overlay Visual

1. Web: canvas HTML5 sobre imagem warped (cantos + bolhas + scores)
2. Mobile: react-native-svg sobre a foto (cantos + bolhas detectadas)
3. Ajuste fino (dx/dy/scale) com sliders/botões

### FASE 6 — Mobile: Completar Engine TS

1. Implementar `fiducial-detector.ts`
2. Implementar `homography.ts`
3. Implementar `bubble-classifier.ts`
4. Testes com imagens de fixture

### FASE 7 — Calibração

1. Comando artisan para rodar N amostras por template
2. Histograma de scores → sugerir thresholds ideais
3. Persistir thresholds no template
4. Documentar em `COMO_CALIBRAR.md`

### FASE 8 — Revisão Manual Assistida

1. Tela de revisão com overlay obrigatório
2. Fila de scans NEEDS_REVIEW
3. Forçar resposta com auditoria (quem, quando, antes/depois)
4. Recalcular nota após correção manual

---

## 10. ESTRUTURA DE DADOS — Question.content

A coluna `content` (JSON) em `questions` contém:

```json
{
  "statement": "Texto da questão...",
  "options": ["Opção A", "Opção B", "Opção C", "Opção D"],
  "correct_option": 2
}
```

Para true_false:
```json
{
  "statement": "A Terra é plana.",
  "correct_option": 0
}
```

Onde `correct_option` é um **inteiro** (0=falso, 1=verdadeiro para T/F; 0..4 para múltipla escolha).

O bug acontece porque o frontend envia `"true"`/`"false"` mas o gabarito usa `0`/`1`.

---

## 11. ROTAS OMR ATUAIS

### Web
| Método | Rota | Controller | Ação |
|--------|------|-----------|------|
| GET | `/omr` | OmrController@index | Lista scans |
| GET | `/omr/upload` | OmrController@create | Form upload |
| POST | `/omr/upload` | OmrController@store | Salva scan |
| GET | `/omr/{scan}/review` | OmrController@review | Revisão manual |
| POST | `/omr/{scan}/confirm` | OmrController@confirm | Confirma + nota |
| POST | `/omr/{scan}/reject` | OmrController@reject | Rejeita scan |
| GET | `/omr/webscan` | OmrController@webscan | Webcam capture |

### API
| Método | Rota | Controller | Ação |
|--------|------|-----------|------|
| GET | `/api/v1/omr/scans` | OmrApiController@index | Lista scans |
| POST | `/api/v1/omr/scans` | OmrApiController@store | Upload página |
| GET | `/api/v1/omr/scans/{scan}` | OmrApiController@show | Detalhes scan |
| PUT | `/api/v1/omr/scans/{scan}/confirm` | OmrApiController@confirm | Confirma |
| PUT | `/api/v1/omr/scans/{scan}/reject` | OmrApiController@reject | Rejeita |

---

## 12. CONCLUSÃO

O sistema tem uma **base estrutural razoável** (models, migrations, rotas, UI) mas falta o **core OMR**:
- A correção de notas está quebrada por casting errado
- A leitura de bolhas não funciona (módulos não implementados)
- O web não faz detecção nenhuma
- Não há validação visual (overlay)
- Não há templates formais no banco

A prioridade é: **(1) corrigir o bug de grading T/F**, **(2) criar a engine OMR real**, **(3) implementar overlay**, **(4) templates + calibração**.
