# Fase 5 — Auditoria unificada e tenant-aware

**Data:** 2026-08-08
**Tarefa:** `AUD-001`
**Parecer:** APROVADO COM PENDÊNCIA OPERACIONAL

## Resultado

`audit_logs` passou a ser a fonte unificada de consulta de auditoria. Cada novo registro suporta organização, ator, entidade, ação, origem (`web`, `api`, `console` ou `system`), request ID, severidade, contexto e before/after normalizados. O payload histórico foi preservado para compatibilidade.

`event_logs` não foi removido. Eventos operacionais novos são espelhados de forma idempotente na trilha unificada, e a migration copia eventos históricos usando `legacy_event_log_id` único. A tela institucional consulta somente `audit_logs.organization_id` do workspace autenticado; filtros nunca substituem esse escopo.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| IAM-10 — correção Web auditável | Ações usavam payloads heterogêneos e sem contrato comum de origem/request ID | `AuditLog::log` normaliza tenant, ator, origem, request ID e alterações | `AuditLog.php`, migration | Regressão OMR/segurança e contrato Web/API | Revisão local independente | APROVADO |
| IAM-12 — ator/contexto/before-after/origem | `audit_logs` não possuía tenant, request ID, origem ou colunas before/after; redaction automática não cobria todos os logs manuais | Novas colunas aditivas, redaction central e request ID validado/gerado | `AuditLog.php`, `Auditable.php`, migration | Redaction, before/after, request ID fornecido e inválido | Revisão local independente | APROVADO |
| REP-04 — isolamento institucional | Instituição consultava uma trilha separada em `event_logs` | Consulta unificada fixa o tenant antes de filtros de ator, texto, origem, severidade e período | `Institution/LogController.php`, view institucional | Busca/ator estrangeiros retornam coleção vazia | Revisão local independente | APROVADO |
| Compatibilidade histórica | Duas tabelas e produtores independentes | Backfill aditivo e espelhamento por chave única; nenhuma tabela ou registro é removido | `EventLog.php`, migration | Espelhamento chamado duas vezes produz um único audit log | Revisão local independente | APROVADO |
| Auditoria de plano | Troca/cancelamento registravam contexto, mas não before/after normalizado | Estado anterior e posterior explicitados | `BillingController.php` | Regressão de billing e auditoria | Revisão local independente | APROVADO |

## Alterações técnicas

- Banco: `audit_logs` recebeu `organization_id`, `request_id`, `origin`, `severity`, `context_json`, `before_json`, `after_json` e `legacy_event_log_id`, com índices de consulta e FK não destrutiva.
- Compatibilidade: `payload`, `user_id`, `action`, `model_type`, `model_id`, `ip_address` e `user_agent` foram mantidos.
- Backend: sanitização de credenciais/tokens centralizada em `AuditLog`, inclusive chamadas manuais.
- Web: telas institucional e global mostram origem, request ID, contexto e alterações normalizadas; filtros recebem validação de tipo/tamanho.
- API/Mobile: ações auditadas em requisições `api/*` recebem origem `api` e workspace do header já autorizado.
- OMR/QR/offline: nenhum contrato funcional foi alterado.

## Evidências

- Regressão Laravel completa após a correção final: `391 passed (1359 assertions)`.
- Auditoria/segurança focada: `45 passed (158 assertions)`.
- Contrato unificado dedicado: `4 passed (25 assertions)`.
- Reteste final de contrato e plano: `17 passed (47 assertions)`.
- Migration em SQLite vazio: `up` aprovado.
- Rollback isolado: falhou inicialmente pela ordem dos índices; corrigido e retestado com sucesso.
- JavaScript/Vitest: `11 passed` em 2 arquivos.
- E2E/Playwright: `6 passed` nos perfis desktop e mobile; uma rodada anterior excedeu o timeout local e passou integralmente no reteste.
- Build de produção/Vite: `133 modules transformed`, concluído sem erro.
- Composer, Blade e Pint: aprovados.

## Revisão independente

A revisão encontrou e corrigiu dois problemas objetivos: o teste legado ainda tipava a coleção institucional como `EventLog`, e o primeiro rollback SQLite removia colunas antes de seus índices simples. Após as correções, o teste tenant-aware, a migration e o rollback passaram.

Também foi verificado que request IDs externos só são aceitos com caracteres seguros e até 100 posições; valores inválidos são substituídos por UUID. Dados com chaves de senha, token ou secret são redigidos antes de qualquer coluna ser persistida.

Parecer: **APROVADO COM PENDÊNCIA OPERACIONAL**.

## Pendências reais

- Executar a migration em staging com volume semelhante à produção e medir tempo de backfill/lock antes do rollout.
- Logs históricos que não continham `organization_id` são associados pelo tenant legado do ator quando disponível; origem histórica não comprovável é registrada como `system`. Casos sem evidência permanecem globais, sem atribuição inventada.
- Política jurídica de retenção/exportação de auditoria depende de decisão de negócio/LGPD e não foi presumida nesta fase.
