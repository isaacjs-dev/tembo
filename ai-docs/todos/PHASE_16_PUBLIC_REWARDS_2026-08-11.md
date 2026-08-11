# Fase 16 — Recompensas públicas configuráveis

**Tarefa:** `PLAN-003` / `PLAN-04`
**Estado:** aprovado

## Resultado

A aprovação moderada de uma questão ou recurso público pode agora conceder créditos mensais ao colaborador. O efeito financeiro só existe quando um administrador global cria e ativa uma regra explícita. A regra define versão, tipo de conteúdo, recurso, quantidade, vigência e tetos mensal por colaborador e global.

Cada submissão produz no máximo uma decisão de premiação. Retry, concorrência e troca de versão não duplicam créditos nem reiniciam o teto do programa no mesmo mês. O ledger mantém o workspace e o membership que receberam o benefício. Membership revogada falha fechada; o caminho legado direto, ainda reconhecido pelo sistema, permanece compatível sem abrir acesso cross-tenant.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Configuração administrativa | Valores econômicos não existiam | Gestão restrita para criar versão, ativar/agendar e aposentar | Teste HTTP global admin/usuário comum | concluído |
| Versionamento e vigência | Apenas fatos de reputação | Regra imutável com versão única, janela, estado vigente/agendado/aposentado | Testes de ativação e agendamento | concluído |
| Exatamente uma vez | Aprovação não gerava crédito | Award único por submissão e idempotency key `public-approval:{submission}:{version}` | Retry retorna o mesmo award/evento | concluído |
| Tetos | Ausente | Cap por colaborador e global, contínuo entre versões do mesmo programa no mês | Casos granted/partial/capped e troca v1→v2 | concluído |
| Contexto | Ausente | `scope_key`, organization e membership persistidos; legado autorizado preservado | Testes ativo, revogado e legado | concluído |
| Ledger | Cotas só tinham consumo/reset | Evento `credit`, `bonus_credits` no período e saldo recalculado | Limite base 2 + prêmio 3 = 5 | concluído |
| Concorrência | N/A | Slot transacional estável por tipo bloqueia ativação, aposentadoria e premiação | Inspeção de locks + constraints únicas | concluído |

## Alterações técnicas

- `public_catalog_reward_rules`: configuração versionada e auditável;
- `public_catalog_reward_rule_slots`: mutex e ponteiros para regra vigente/agendada por tipo;
- `public_catalog_reward_awards`: decisão financeira imutável ligada à submissão e ao ledger;
- `usage_periods.bonus_credits`: crédito mensal separado do consumo;
- `MonthlyUsageService::credit()`: lançamento contextual e idempotente;
- `PublicCatalogRewardService`: seleção sob lock, caps e integração atômica à aprovação;
- painel de moderação: formulário e histórico das regras;
- rollback recusa remover créditos já concedidos, evitando perda de saldos/histórico.

APIs públicas, QR, OMR, Mobile e formatos offline não foram alterados.

## Evidências

- suíte Laravel completa final, sem execução concorrente: **481 testes / 2020 assertivas**, aprovada;
- regressão focada de catálogo, entitlements, ledger, segurança e auditoria: **64 / 318**, aprovada;
- testes PLAN-003: **6 / 38**, aprovados;
- JavaScript/Vitest: **11 / 11**, aprovado;
- Vite build: aprovado;
- Blade `view:cache`: aprovado;
- Pint dos arquivos afetados: aprovado;
- `git diff --check`: aprovado, apenas aviso local LF/CRLF no task master;
- migrations `000300` e `000310`: aplicadas com sucesso no banco local e exercitadas do zero por `RefreshDatabase`.

## Revisão independente

O primeiro parecer encontrou: lacuna ao agendar versão futura, ativação concorrente sem mutex comum, reset do cap na troca de versão, incompatibilidade do usuário legado e ordenação de imports. Um segundo reteste encontrou a promoção incompleta quando a vigência agendada começava. Todos foram corrigidos na causa e ganharam cobertura dedicada.

**Parecer final:** `APROVADO`. O revisor confirmou a ordem única de locks, promoção e aposentadoria atômicas, atualização dos slots, substituição posterior correta e caps agregados entre versões. Evidência independente: **38 testes / 190 assertivas**, além de Pint e diff-check aprovados, sem achados altos ou médios remanescentes.

## Pendências reais

- validar locks e contenção sob o SGBD de produção durante o rollout; a suíte automatizada usa SQLite;
- configurar valores, tetos e vigências reais é decisão administrativa/produto — nenhuma equivalência econômica foi inventada ou ativada automaticamente;
- rollback com créditos existentes exige reconciliação explícita e, por segurança, é recusado pela migration.
