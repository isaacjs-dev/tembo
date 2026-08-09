# Fase 4 — Plano somente pelo proprietário

**Data:** 2026-08-08
**Tarefa:** `IAM-005`
**Parecer:** APROVADO

## Resultado

A leitura, troca e o cancelamento do plano institucional agora compartilham uma única regra de propriedade. O usuário precisa estar em um contexto ativo e ser o `owner_user_id` do workspace. Diretor, Coordenador, Pedagogo, administrador delegado e administrador global sem propriedade explícita recebem `403` antes de qualquer mutação.

A navegação usa a mesma regra: gestores delegados continuam acessando as configurações institucionais permitidas, mas não veem os links `Assinatura` ou `Assinar`. O backend permanece como autoridade, portanto ocultar o link não substitui a autorização.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| IAM-09 — somente proprietário altera plano | O controller já possuía um gate privado, mas a UI oferecia billing a administradores delegados e não havia matriz negativa completa | `PlanOwnershipService` centraliza contexto ativo, owner explícito e fallback legado controlado | `PlanOwnershipService.php`, `BillingController.php`, `app.blade.php` | Leitura, troca e cancelamento por cinco perfis não proprietários | Revisão local independente | APROVADO |
| Compatibilidade de conta institucional legada | Conta sem pivot e sem `owner_user_id` ainda dependia do vínculo direto histórico | Fallback preservado somente para `institution_admin` sem qualquer membership e no próprio `organization_id` original | `PlanOwnershipService.php` | Regressão `BillingAndAccessTest` | Revisão local independente | APROVADO |
| Contexto inativo | Owner explícito poderia ser confundido com acesso operacional permanente | Propriedade não concede acesso quando o membership está inativo | `PlanOwnershipService.php` | Owner com membership inativo recebe `403` | Revisão local independente | APROVADO |
| Navegação coerente | Admin delegado via `role_in_org=admin` via `Assinatura`, mas o backend o bloqueava | Link e CTA condicionados à mesma regra central de ownership | `app.blade.php` | Owner vê; delegado não vê | Revisão local independente | APROVADO |

## Alterações técnicas

- Backend: serviço reutilizável de propriedade do plano e autorização aplicada pelo `BillingController`.
- Web/UX: links de billing exibidos apenas ao proprietário do workspace atual.
- Banco/migrations: N/A.
- API/Mobile/OMR/QR/offline: N/A.
- Compatibilidade: fallback legado preservado sem permitir que um pivot inativo volte pelo `organization_id` antigo.

## Evidências

- Testes focados de billing, workspace e segurança: `38 passed (121 assertions)`.
- Blade: cache/compilação aprovado.
- Pint dos arquivos PHP em escopo: aprovado.
- Regressão Laravel completa: `387 passed (1331 assertions)`.
- JavaScript/Vitest: `11 passed` em 2 arquivos.
- E2E/Playwright: `6 passed` nos perfis desktop e mobile.
- Build de produção/Vite: `133 modules transformed`, concluído sem erro.
- Composer: `composer.json` válido.

## Revisão independente

A passagem de revisão confrontou os três endpoints de billing, a navegação, o contexto selecionado e o estado persistido. Confirmou que as tentativas negadas não criam, cancelam nem substituem assinaturas. Também confirmou que o fallback histórico exige simultaneamente tipo institucional, ausência total de memberships e correspondência com o `organization_id` original.

Parecer: **APROVADO**.

## Pendências reais

- A alteração de planos individuais do professor não faz parte do endpoint institucional e deverá ser tratada em tarefa própria quando o fluxo comercial for definido.
- Homologação visual manual do menu em navegadores/dispositivos reais permanece recomendada, sem bloquear esta correção de autorização.
