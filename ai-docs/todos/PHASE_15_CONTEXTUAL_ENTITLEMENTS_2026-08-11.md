# Fase 15 — Entitlements e cotas contextuais

**Tarefa:** `PLAN-002`  
**Estado:** concluída  
**Parecer:** APROVADO por revisão independente após correções e reteste  
**Pendência humana:** observar locks, índices e tempo de backfill na engine de produção

## Resultado

Plano, funcionalidades, cortesias e cotas agora são resolvidos no workspace selecionado. Uma pessoa vinculada a mais de uma instituição não recebe vantagens nem compartilha consumo entre contextos. O ledger mensal mantém usuário, organização, membership e chave de escopo, preservando idempotência e histórico.

Assinaturas respeitam início, expiração, carência e agendamento. Downgrades entram em vigor somente após a vigência atual; upgrades continuam imediatos; cancelamento concede uma única carência e não pode estender `canceled` ou `past_due`. O plano `Start`/`Free` é o fallback quando o catálogo está configurado.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| PLAN-03 — escopo | Plano/cortesia podiam vir de outro membership | Resolução pelo workspace ativo e fail-closed para tenant não autorizado | Testes multi-workspace e membership inativo | Revisão independente | aprovado |
| PLAN-03 — ledger | Período único por usuário misturava organizações | `scope_key` e `membership_id` em períodos/eventos; unicidade por escopo | Consumo simultâneo em duas instituições | Revisão independente | aprovado |
| PLAN-03 — papéis | Rotinas mensais dependiam de `users.type` | Abertura/reset usam papel contextual `teacher` | Global student/teacher e teacher/student | Revisão independente | aprovado |
| PLAN-05 — resolvedor | Três implementações divergentes | `EntitlementService` central; adapters e models delegam | Plan/limit/feature coerentes | Revisão independente | aprovado |
| PLAN-05 — vigência | Status ativo ignorava parte das janelas | Início, expiração, `scheduled`, `canceled`, `past_due` e grace explícitos | Testes temporais | Revisão independente | aprovado |
| PLAN-05 — downgrade | Troca inferior era imediata | Nova assinatura agendada para o fim da vigência | Teste de billing e resolução futura | Revisão independente | aprovado |
| PLAN-05 — carência | Cancelado deixava de valer; retry podia ampliar grace | Grace efetiva, cancelamento somente de `active`, prazo imutável em retry | Testes canceled/past_due | Revisão independente | aprovado |
| Idempotência | Reuso conflitante da chave podia pular consumo | Validação de identidade/escopo/recurso/valor e rechecagem pós-lock | Teste cross-scope | Revisão independente | aprovado |

## Alterações técnicas

### Banco e migração

- `subscriptions`: `grace_ends_at`, `cancelled_at` e índice de janela efetiva;
- `usage_periods`: `scope_key`, `membership_id`, backfill e unicidade usuário+escopo+recurso+mês;
- `usage_events`: mesma atribuição contextual e índice para consulta do ledger;
- o `down` ocorre normalmente enquanto só existe um escopo por usuário/recurso/mês; se já houver múltiplos ledgers, recusa antes de alterar o schema para não recombinar ou perder histórico.

### Regras e compatibilidade

- `EntitlementService` é a autoridade para plano, limite, feature e cortesia contextual;
- `EffectivePlanResolver`, `PlanLimiterService`, `User`, `Organization` e `HasPlanLimits` funcionam como adapters compatíveis;
- instalações históricas ainda sem catálogo preservam o comportamento anterior; quando `Start`/`Free` existe, ele é o fallback obrigatório;
- assinaturas legadas somente com `organization_id` continuam reconhecidas, sem confundir assinatura individual morph com institucional;
- cortesias `scheduled` já iniciadas valem pela própria janela, sem depender do cron diário.

### Consumidores

- publicação de Avaliação, questões e dashboard usam o contexto autenticado;
- OMR Web/API e consolidação em job recebem explicitamente a organização da Avaliação;
- feature OMR considera cortesia contextual do usuário;
- abertura mensal e reset administrativo percorrem memberships docentes ativos;
- billing mostra somente planos institucionais e ações apenas ao proprietário já autorizado no backend.

## Evidências

- baseline anterior: **23 testes, 75 assertivas**, aprovado;
- regressão focada final: **55 testes, 322 assertivas**, aprovada;
- revisão independente ampliada: **81 testes, 387 assertivas**, aprovada antes da última correção de carência;
- regressão final de billing/contexto: **26 testes, 102 assertivas**, aprovada;
- Laravel integral final: **475 testes, 1.975 assertivas**, aprovado;
- JavaScript Web: **11/11**, aprovado;
- Blade cache, Pint focado e `git diff --check`: aprovados;
- migration `up` aplicada sobre o banco local existente: aprovada;
- tentativa de criar/remover banco temporário para exercitar `down→up` foi bloqueada pela política do ambiente antes da execução, sem efeitos no filesystem ou nos dados.

## Revisão independente

A revisão encontrou e devolveu problemas reais de fallback `Start`, rollback com múltiplos escopos, cortesias agendadas, papéis contextuais, adapters, colisão idempotente, UI de billing, OMR com cortesia, fail-open de organização estrangeira e extensão de carência `past_due`. Todos foram corrigidos na causa e receberam testes. Parecer final: **APROVADO**, sem achados altos ou médios remanescentes.

## Pendências reais

- homologar backfill, índices e contenção de locks em MySQL/MariaDB com volume representativo;
- o rollback é deliberadamente recusado se múltiplos escopos já tiverem sido gravados, pois uma volta automática ao índice antigo exigiria perda ou mistura de ledger;
- cobrança externa, pagamentos e preços continuam fora desta fase; nenhuma transação financeira real foi executada.
