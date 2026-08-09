# Fase 1 — Membership autoritativo e workspaces

**Data:** 2026-08-08  
**Tarefa:** `IAM-002`  
**Parecer:** APROVADO COM PENDÊNCIA HUMANA

## Resultado

O contexto de organização deixou de ser inferido apenas por `users.organization_id`. Web e API agora resolvem um workspace autorizado, aplicam o papel do membership ativo e falham de forma fechada quando o contexto é ausente, inválido ou inativo. A coluna legada permanece somente como preferência/fallback compatível para contas que nunca tiveram memberships.

O cadastro diferencia instituição e professor independente. O professor pode criar ou reutilizar um workspace pessoal, criar turmas próprias vinculadas ao usuário e manter papéis diferentes em workspaces distintos sem alterar sua conta global. O Mobile solicita escolha quando o login é ambíguo e envia `X-Workspace-Id` em todas as chamadas autenticadas.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| IAM-01 — conta global e memberships independentes | Papel e contexto ainda dependiam amplamente de `users.type` e `organization_id` | Papel contextual centralizado no membership; troca não persiste na conta global | `User.php`, `WorkspaceContextService.php`, middlewares e rotas | `WorkspaceContextTest`, regressão completa | Revisão local independente | APROVADO |
| IAM-02 — membership autoritativo e fail-closed | Fallback legado podia reaparecer após membership inativo | Fallback permitido somente sem qualquer pivot; contexto explícito não autorizado recebe 403 | `ApplyWorkspaceContext.php`, `EnsureActiveAccount.php`, `WorkspaceContextService.php` | `TenantIsolationHardeningTest`, `ActiveAccountMiddlewareTest` | Revisão local independente | APROVADO |
| IAM-03 — papéis diferentes por workspace | Rotas e policies usavam papel global | `workspace_role` e `hasWorkspaceRole` aplicados em Web, API, policies e serviços pedagógicos | `CheckWorkspaceRole.php`, controllers, policies | Cenários estudante→professor e login/API multi-contexto | Revisão local independente | APROVADO |
| IAM-04 — professor independente | Cadastro vazio criava organização sem semântica explícita; não havia seletor/criação pessoal | `workspace_type`, cadastro pessoal/institucional, criação idempotente de workspace pessoal e turma `owner_type=user` | migration, cadastro, `WorkspaceController.php`, `SchoolClassController.php` | Cadastro e criação de turma pessoal | Revisão local independente | APROVADO |
| IAM-05 — vincular conta existente | Responsável existente precisava ter tipo/FK global compatível | Membership `guardian` é anexado sem mudar tipo, senha ou organização padrão globais | `GuardianLinkController.php`, `GuardianPortalController.php` | `GuardianPortalTest` multi-workspace | Revisão local independente | APROVADO |
| IAM-06 — preservar conta global | Troca de contexto poderia induzir mutação do FK | Contexto é aplicado apenas em memória/sessão/header; desvinculações existentes continuam preservando a conta | `WorkspaceController.php`, middleware, serviços existentes | Testes de troca e desvinculação | Revisão local independente | APROVADO |
| Isolamento de conteúdo | Questões e templates agregavam todas as organizações ativas | Listas e autorização limitadas ao workspace selecionado; export OMR também autorizado | `QuestionController.php`, `OmrTemplate.php`, `OmrTemplateController.php` | Cenários cruzados de questão/template | Revisão local independente | APROVADO |
| Contrato Mobile | Login não escolhia nem mantinha workspace | Resposta `WORKSPACE_REQUIRED`, seletor no login e header automático após autenticação | `duoscanner/app/(auth)/login.tsx`, services e types | TypeScript, grid/QR e Expo Doctor | Revisão local independente | APROVADO |

## Alterações técnicas

- Banco: migration aditiva `workspace_type` em `organizations`, com padrão `institutional` e índice por tipo/atividade.
- Web: seletor de workspace, criação de espaço pessoal e indicação do contexto na navegação.
- API: `X-Workspace-Id`, `workspace_role` e lista de workspaces autorizados; login ambíguo retorna `409 WORKSPACE_REQUIRED` sem emitir token.
- Mobile: seleção explícita no login e persistência operacional do contexto pelo header.
- Autorização: rotas, policies, OMR, relatórios e conteúdo pedagógico consultam o papel contextual.
- Memberships: listas e contagens de alunos/professores usam o pivot autoritativo com fallback legado controlado.
- Planos: a tela institucional exige o proprietário real; contas históricas sem `owner_user_id` mantêm fallback limitado a `institution_admin` sem memberships.
- Compatibilidade: `users.organization_id` não foi removido nem regravado ao trocar de contexto.

## Evidências

- Laravel: `366 passed (1209 assertions)` na regressão completa final.
- Foco de workspace/tenant/guardian: `31 passed (155 assertions)` após a revisão final.
- Web JavaScript: `11 passed`.
- Vite: build de produção aprovado.
- Playwright: `6 passed` em desktop e mobile.
- Mobile: TypeScript aprovado; contratos de grid/QR `6 passed`; Expo Doctor `18/18`.
- Composer: manifesto válido.
- `git diff --check`: aprovado.
- Pint dos arquivos em escopo: aprovado. A execução sobre todo `tests/` encontra um import não usado preexistente em `MonthlyUsageAndCourtesyTest.php`, arquivo não alterado por esta fase.

## Revisão independente

Os revisores delegados de backend e UX não puderam executar uma nova rodada por limite externo de uso dos agentes. Foi feita uma passagem separada de revisão local depois da implementação. Ela encontrou e corrigiu:

1. agregação indevida de questões e templates entre workspaces;
2. exportação/edição de template OMR sem fronteira suficiente;
3. rotas e policies ainda dependentes do papel global;
4. fallback legado após membership inativo;
5. contexto Mobile não persistido entre chamadas;
6. listas e quotas baseadas no FK/tipo global;
7. vínculo de responsável existente que mutaria ou recusaria indevidamente a conta global.

Todos os cenários receberam teste e reteste. Parecer técnico: **APROVADO COM PENDÊNCIA HUMANA**.

## Pendências reais

- Executar a migration em staging com backup e conferir a classificação dos workspaces históricos; o padrão seguro é `institutional`, pois não há evidência suficiente para converter registros antigos automaticamente.
- Fazer smoke test do seletor em aparelhos Android/iOS reais e validar textos, teclado e área de toque.
- A matriz completa de cargos customizados, relações acadêmicas N:N e convites/ativação sem senha provisória permanece em `IAM-003`/`IAM-004`.
- A generalização definitiva de entitlements por contexto permanece em `PLAN-002`; esta fase cobre somente o plano individual necessário à turma pessoal e a propriedade do billing institucional.
