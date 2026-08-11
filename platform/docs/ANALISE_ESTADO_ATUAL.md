# Análise do Estado Atual — Plataforma Avaliações Online

> **Documento histórico (07/06/2026).** Este diagnóstico registra o estado anterior à rodada de correção e finalização de 29/07/2026. As lacunas, contagens e conclusões abaixo não representam o produto atual. Consulte o [relatório de qualidade e entrega](QUALITY_REPORT_2026-07-29.md) para o estado validado.

> Levantamento read-only do sistema (web Laravel + app DuoScanner). **Nenhuma alteração de código foi feita.** Data da análise: 2026-06-07.

---

## 0. Resumo geral do sistema

SaaS educacional **multi-tenant** para criação, aplicação e **correção automática (OMR)** de avaliações.

- **Stack:** Laravel 12 / PHP 8.2, Blade + Tailwind + Alpine.js + Vite 7, SQLite (dev). Spatie Permission 6, Sanctum 4. PDF via dompdf. OMR no navegador via OpenCV.js + jsQR (Web Worker TS compilado em `resources/js/omr-core`).
- **App mobile:** DuoScanner (React Native + Expo SDK 55) consome a API (`/api/v1`, `/api/v2`) para correção via câmera.
- **Perfis:** `global_admin`, `institution_admin`, `teacher`, `student`.
- **Multi-tenancy:** `TenantScope` (global scope por `organization_id`).
- **Tamanho:** ~43 controllers, ~40 models, 13 services, ~65 migrations, ~95 views.

O sistema está **funcional de ponta a ponta** nos fluxos principais (criar prova → gerar cartão → escanear → corrigir → nota). As lacunas concentram-se em **enforcement de permissões granulares**, **correção do exame online (não-OMR)**, e **código legado/duplicado** acumulado de várias fases.

---

## 1. O que já foi implementado (e como funciona)

### Autenticação e base
- **Auth completo** (Laravel Breeze): login, registro, verificação de e-mail, reset de senha, perfil. ✅ Completo.
- **Multi-tenancy** via `TenantScope` + `organization_id`. ✅
- **Vínculo usuário↔organização** duplo: FK legado (`users.organization_id`) + pivot `user_organization` (status ativo). Serviços `UserLinkerService`/`UserFinderService`. ⚠️ Modelo duplo é fonte conhecida de bugs (ver Riscos).

### Institucional (institution_admin / teacher)
- **Dashboard** institucional. ✅
- **Settings** da instituição. ✅
- **Billing**: troca/cancelamento de plano (`Institution/BillingController`). ✅ (sem gateway de pagamento real — ver Pendentes.)
- **Convites** (`Institution/InviteController`): criar, listar, aceitar/recusar por token, "convites recebidos". ⚠️ **Não envia e-mail** (TODO em `InviteManagerService:146`) — o token é compartilhado manualmente.
- **Professores** (CRUD + busca + tela de permissões). ✅ CRUD; ⚠️ permissões só de gestão (ver §3).
- **Turmas** (`SchoolClassController`): CRUD, matrícula, transferência de titularidade (com `ClassOwnershipService` + log). ✅
- **Alunos** (`StudentController`): CRUD + busca. ✅
- **Cargos/Roles de instituição** (`InstitutionRoleController` + `InstitutionRoleService`): CRUD de cargos com 14 permissões granulares + atribuição. ✅ Gestão; ⚠️ **não são aplicadas** (ver §3/§6).
- **Relatórios** (`ReportController`). ✅ (verificar profundidade).

### Banco de questões
- **Questões** (CRUD): múltipla escolha, V/F, dissertativa. Vínculo com **disciplina**, **área de conhecimento**, **BNCC** (`BNCcController` + estrutura curricular) e **habilidades customizadas** (`CustomSkillController`). Compartilhamento público (`QuestionShare`) e duplicação. ✅
- **Taxonomia** (`TaxonomyController`): criação AJAX de áreas/disciplinas. ✅
- V/F agora com `options=['Verdadeiro','Falso']` + `correct_option` (corrigido por migration de backfill + seeder).

### Provas (exams)
- **CRUD de provas**, picker de questões (modal + busca + bulk add), reordenação, pontos por questão, vínculo com turmas, duplicação. ✅
- **Exportação PDF**: 3 caminhos — `exportPdf` (`exams/pdf`), `printAdvanced`/"Imprimir Lote" (`exams/pdf_advanced`, prova+cartão+gabarito), `exportAnswerSheet` (cartão OMR via `pdf/answer-sheet-essential`). ✅ Unificados na geração do cartão (`AnswerSheetGeneratorService::buildCardPages`).
- **Correção manual de submissões** (`gradeSubmission`/`storeGrade`). ✅

### OMR (núcleo, maduro)
- **Geração de cartão** template-driven, adaptativa (colunas conforme nº de questões), **frame compacto**, marcadores fiduciais e **QR seguro e compacto** (HMAC `chk` + `g` geometria + `oc` contagem de opções; `gab_enc` somente histórico). ✅
- **Templates dinâmicos** (`omr_templates` + `omr_template_versions`): editor visual Konva.js, auto-detecção OpenCV, visibilidade (sistema/org/privado), versionamento. ✅ Fases 1–5 (ver `docs/OMR_TEMPLATES.md`).
- **Leitura no navegador** (Web Scan + Upload): OpenCV.js detecta 4 marcadores (filtro de solidez exclui QR), corrige perspectiva (warp), lê bolhas (Otsu), respeita `oc` (V/F = só A/B). ✅ Validado 43/43.
- **Tela de Debug** (`/institution/omr/debug`): mostra marcadores, warp, ROIs e preenchimento. ✅ Ferramenta de diagnóstico.
- **Conferência** (`omr/review`): respostas em espaço visual, **coloração acertos (verde) / erros (vermelho)** ao vivo, contador, **gabarito ao lado dos erros**, **validação de coerência** (QR impresso × gabarito oficial → ⚠ bloqueia auto-correção em divergência), botão "Confirmar e Gerar Nota". ✅ Recente.
- **Correção** (`OmrGradingService`): shuffle-aware (reverse-map via `options_map`), uniforme MC/V/F. ✅ Validado.
- **Relatórios OMR**, batch update, reject. ✅ (verificar profundidade de reports).

### Portal do aluno
- Entrar por código, ver intro, iniciar, **executar prova online**, enviar, ver resultados. ✅ Fluxo existe; ⚠️ correção online incompleta (ver §3).

### Admin global
- Dashboard, **Planos** (CRUD + limites + features normalizados), **Usuários** (CRUD), **Audit Logs**, **Logs** do sistema, **Lixeira** (restore/force-delete), **Config OMR** (regras de precedência `ConfigRule` + auditoria + simulação). ✅

### API / DuoScanner
- `/api/v1` e `/api/v2`: login Sanctum, listar/baixar provas, **OMR scans** (index/store/show/confirm/reject) com middleware `omr_api` (feature de plano `omr` + pivot ativa). v2 adiciona **config efetiva** para o app. ✅
- App **DuoScanner** (pasta `duoscanner/`): scan OMR por câmera. ✅ (fora do escopo deste repo; ver `duoscanner/DUOSCANNER_DOC.md`).

---

## 2. O que ainda NÃO foi implementado (pendente/previsto)

| Item | Situação |
|---|---|
| **Envio de e-mail de convite** | `InviteManagerService:146` — `// TODO: Implementar InviteMailable`. Convites funcionam só por token compartilhado manualmente. |
| **Gateway de pagamento real** | Billing troca de plano sem cobrança/integração (Stripe/Pagar.me etc.). `Admin/UserController:75` usa vencimento "placeholder +30 dias". |
| **Enforcement das permissões de instituição** | Middlewares `inst_perm`, `restrict_trash`, `restrict_logs` **registrados mas não aplicados em nenhuma rota** (`web.php`). As 14 permissões granulares e os cargos só são geridos, nunca verificados. |
| **Provisionamento de chave offline (DuoScanner)** | Desenhado em `docs/OMR_TEMPLATES.md`, **não implementado** (endpoint que entrega o segredo da org ao app). |
| **Leitura por ROI-por-questão no servidor** | "Adiado" — a leitura usa `g`+marcadores; override de ROI por questão não existe. |
| **`generateRois` / `exportJson` de template** | Rotas existem (`OmrTemplateController@generateRois/exportJson`); verificar se implementadas de fato ou stubs. |

---

## 3. O que está incompleto ou funcionando parcialmente

1. **Correção do exame ONLINE (portal do aluno) — `StudentPortalController::submit`**
   - V/F marcado como **"Simple mock for TF"** (linha 125): compara `studentAnswer` com `correct_option ?? 'true'`. Frágil — depende do formato exato enviado pela UI.
   - **Não reaproveita `OmrGradingService`** — lógica de correção **duplicada** e divergente da do OMR.
   - MC compara índice direto sem `options_map` (ok só porque o exame online não embaralha por cópia — mas é uma suposição implícita).
   - **Recomendação:** unificar no `OmrGradingService`.

2. **Permissões granulares (teacher × institution_admin)**
   - Hoje todas as rotas `institution.*` passam por `role:institution_admin|teacher|global_admin`. Um **professor tem o mesmo acesso de rota que o admin** da instituição. As permissões existem no banco mas não bloqueiam nada.

3. **Dois sistemas de configuração de cartão coexistindo**
   - **Legado:** `AnswerSheetType` + `ScanMode` + `ConfigRule` + `ConfigPrecedenceResolver` (admin/config + API v2).
   - **Atual:** `OmrTemplate` + `OmrTemplateVersion` (vínculo `exams.card_template_id`).
   - `ExamController` ainda referencia `answer_sheet_type`/`ConfigPrecedenceResolver` em alguns pontos → **sobreposição confusa**; risco de caminhos divergentes.

4. **Relatórios** (`ReportController`, `omr/reports`) — existem, mas é preciso verificar se entregam dados completos ou são telas iniciais.

5. **Validação de coerência** — implementada para scans NOVOS; scans antigos (criados antes) não têm `validation_errors` gravado (degradam para correção normal pelo banco — aceitável, mas inconsistente).

---

## 4. O que provavelmente NÃO é mais necessário manter (documentado, não removido)

> ⚠️ Nada foi removido. Confirmar referências antes de excluir.

| Item | Motivo |
|---|---|
| `app/Http/Controllers/Api/OMRController.php` | **Órfão.** As rotas usam `OmrApiController`. Nenhuma referência externa a `OMRController`. Provável legado superado. |
| `app/Http/Controllers/Api/OmrScanPageController.php` | **Não roteado** em `api.php`. Verificar; provável morto. |
| `app/Http/Controllers/Institution/TrashController.php` | **Não roteado** — `web.php` usa `Admin/TrashController`. Duplicado. |
| `resources/views/omr/templates/create.blade.php` e `edit.blade.php` | Órfãs — o controller `create/edit` agora retorna `editor.blade.php`. |
| `resources/views/institution/trash.blade.php` | Duplicado de `institution/trash/index.blade.php`. |
| `resources/views/pdf/answer-sheet-detailed.blade.php` | Provável legado — `answer-sheet-essential` adapta layout por geometria (1–4 colunas). Verificar uso. |
| `App\Models\OmrTemplateQuestion` + métodos `createTemplate*` em `AnswerSheetGeneratorService` | **Código morto** — cartão é único (lê por marcadores+QR), não cria template/ROI por cópia. |
| `App\Models\OmrCalibration` / "Calibração Local" | Despriorizada; a correção atual não depende dela. Verificar se ainda referenciada na review. |
| `AnswerSheetType` / `ScanMode` (e parte do subsistema Config) | Legado funcional, mas o caminho principal de cartão migrou para `OmrTemplate`. Avaliar consolidação. |
| Métodos legados `sign()/verify()` em `QrCodeSigningService` | Mantidos só para QRs antigos; remover quando não houver mais cartões legados em circulação. |

---

## 5. O que precisa ser melhorado

- **Segurança/permissões:** aplicar `inst_perm` nas rotas (ou Policies) para separar de fato `teacher` de `institution_admin`. Aplicar `restrict_trash`/`restrict_logs` onde previsto.
- **Unificar a correção:** `StudentPortalController` deve usar `OmrGradingService` (eliminar o "mock" de V/F e a duplicação).
- **Consolidar geração de PDF:** três caminhos (`pdf`, `pdf_advanced`, `answer-sheet-essential`) — o cartão já foi unificado em `buildCardPages`; avaliar unificar a prova também.
- **Consolidar configuração de cartão:** decidir entre `AnswerSheetType/ConfigRule` e `OmrTemplate` (hoje há overlap).
- **Remover código morto** (§4) para reduzir superfície de manutenção e risco de wiring acidental.
- **Validação de formulários:** revisar inputs sem validação (ex.: pontos de questão, quantidades de impressão, uploads).
- **Convites:** implementar e-mail (`InviteMailable`).
- **Padronização de comentários:** alguns arquivos têm `//` corrompido como `\` (ex.: `OmrController`, `ExamPrintService`) — cosmético, mas confunde.
- **Testes automatizados:** não há indício de suíte de testes cobrindo o fluxo OMR crítico (correção, shuffle, validação) — alto valor.

---

## 6. Riscos e pontos de atenção

1. **🔴 Permissões granulares não aplicadas** — professor acessa rotas de gestão como admin. Risco de segurança/negócio.
2. **🔴 Correção online de V/F é "mock"** — pode pontuar errado provas feitas pelo portal do aluno (diferente do OMR, que está correto).
3. **🟠 Vínculo usuário↔org duplo** (FK + pivot) — historicamente causa "aluno/professor não aparece"; sempre criar a pivot ativa (ver memória `dual-org-membership-gotcha`).
4. **🟢 Capacidade do QR** — novos cartões omitem o `gab_enc` redundante e dimensionam o QR pela malha modular; o limite físico continua validado antes da impressão.
5. **🟠 Idempotência do scan** — reenvio da mesma imagem de um scan não-confirmado **apaga e refaz**; correto, mas destrutivo se mal usado. Confirmados ficam bloqueados (ok).
6. **🟠 Backfill V/F default 0** — enunciados não mapeados receberam "Verdadeiro" como padrão; o professor precisa revisar gabaritos genéricos.
7. **🟠 Dois sistemas de config** podem divergir (cartão gerado por um caminho, lido/configurado por outro).
8. **🟡 SQLite em dev** — confirmar paridade com o banco de produção (tipos JSON, concorrência).
9. **🟡 Controllers órfãos** (`Api/OMRController`, `OmrScanPageController`) — risco de serem religados por engano.
10. **🟡 Dependência de CDNs** (OpenCV.js, jsQR, pdf.js, Konva) no navegador — offline/firewall pode quebrar a leitura. Avaliar hospedar localmente.

---

## 7. Ordem sugerida de prioridade para os próximos ajustes

**P0 — Correção e segurança (impacto direto na nota/acesso)**
1. Unificar a correção do **exame online** no `OmrGradingService` (remover o mock de V/F).
2. **Aplicar enforcement** das permissões de instituição (`inst_perm`/Policies) nas rotas — separar teacher de admin.

**P1 — Consistência e confiabilidade**
3. Decidir e consolidar o **sistema de configuração de cartão** (`OmrTemplate` como fonte única; aposentar `AnswerSheetType/ConfigRule` ou documentar o papel de cada um).
4. Implementar **e-mail de convite** (`InviteMailable`).
5. Adicionar **testes** do fluxo OMR (shuffle, V/F, validação de coerência, grade).

**P2 — Limpeza e manutenção**
6. Remover **código/views/controllers mortos** (§4) após confirmação de referências.
7. Consolidar **geração de PDF** da prova.
8. Hospedar libs JS **localmente** (resiliência offline) e revisar validações de formulário.

**P3 — Evolução**
9. **Provisionamento de chave offline** do DuoScanner.
10. **Gateway de pagamento** real no billing.
11. Aprofundar **relatórios** (desempenho por turma/aluno/habilidade BNCC).

---

> Observação: itens marcados "verificar" requerem leitura adicional do controller/serviço específico antes de uma decisão definitiva. Esta análise priorizou amplitude; o aprofundamento por módulo pode ser feito sob demanda.
