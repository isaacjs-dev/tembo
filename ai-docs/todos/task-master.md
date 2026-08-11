# Tembo — Task Master

Fonte: `ai-docs/PRD.md`. Estados válidos: `pending`, `in_progress`, `done`, `blocked`. Uma tarefa só inicia quando todas as dependências estão `done`.

| ID | Entrega | Requisitos PRD | Depende de | Estado | Aceite mínimo |
| --- | --- | --- | --- | --- | --- |
| BASE-001 | Inventário, auditoria e baseline automatizado | Governança, §4 | — | done | Arquitetura, lacunas e testes registrados |
| BASE-002 | Checkpoint do worktree e push solicitado | Governança | BASE-001 | done | Commits `509072b` e `9f09471` em `origin/main` |
| BASE-003 | PRD, matriz e roadmap únicos | §1–§10 | BASE-002 | done | Sem placeholders, requisitos e dependências rastreáveis |
| IAM-001 | Contexto obrigatório e isolamento fail-closed | IAM-02, IAM-11 | BASE-002 | done | Contexto nulo não expõe dados; testes cross-tenant |
| IAM-002 | Membership autoritativo e workspace pessoal | IAM-01..06 | IAM-001 | done | Usuário multi-contexto e professor independente |
| IAM-003 | Relações acadêmicas tenant-aware | IAM-03, IAM-07 | IAM-002 | done | Pivôs validados e índices únicos |
| IAM-004 | Matriz de policies, convites e desvinculação | IAM-05..08, IAM-10..12 | IAM-003 | done | Papéis não excedem escopo; conta global preservada |
| IAM-005 | Plano somente pelo proprietário | IAM-09 | IAM-004 | done | Diretor/coordenador/pedagogo rejeitados |
| AUD-001 | Auditoria unificada e tenant-aware | IAM-10, IAM-12, REP-04 | IAM-004 | done | Ator/contexto/before-after/origem e filtros seguros |
| OMR-P0-001 | Fechar bypass QR/tenant e tornar imagens privadas | QR-01..03, IAM-11, OFF-01 | BASE-002 | done | QR adulterado/tenant externo rejeitado; arquivo autenticado |
| OMR-P0-002 | Idempotência atômica e recibo determinístico | OMR-07, OFF-02..04 | OMR-P0-001 | done | Concorrência/retry produz uma página/operação |
| OMR-P0-003 | Corrigir `v`, `tpl_v`, `rpp`, `qs/qe` e maps Mobile | OMR-01, OMR-06, OMR-10 | OMR-P0-001 | done | Paridade de fixtures single-page e shuffle |
| ASM-001 | Disciplina e público por aluno/turma | ASM-04, IAM-07 | IAM-004 | done | Compatibilidade com códigos/turmas históricos |
| ASM-002 | Wizard de oito etapas com recuperação | ASM-02, ASM-03 | ASM-001 | done | Autosave e validação backend por etapa/publicação |
| ASM-003 | Modalidades, outputs e cópias versionadas | ASM-01, ASM-05..07, ASM-10..11 | ASM-002 | done | Digital/Avaliação/cartão/ambos/gabarito autorizados |
| ASM-004 | Introdução e jornada completas do aluno | ASM-08, ASM-09 | ASM-003 | done | Dados, estados, tentativas e resultado responsivos |
| PED-001 | Estabilizar revisões, aulas e atividades existentes | PED-01 | IAM-004 | done | Snapshots, autoria, permissões e idempotência cobertos por regressão |
| PED-002 | Entrega e execução de aulas/atividades pelo aluno | PED-02 | PED-001 | pending | Progresso, conclusão e relatório persistentes |
| LIB-001 | Recurso de Questão versionado e pivot N:M | LIB-01..03 | IAM-004 | done | Reuso sem duplicação e visibilidade compatível |
| LIB-002 | Biblioteca pessoal/específica/institucional/pública | LIB-01, LIB-04 | LIB-001 | done | Filtros, busca, paginação e policies |
| LIB-003 | Moderação, denúncia, deduplicação e reputação | LIB-05, LIB-06 | LIB-002 | done | Publicação exige aprovação e trilha completa |
| PLAN-001 | Ledger, cotas e cortesias atuais | PLAN-01, PLAN-02 | BASE-002 | done | 331 testes Laravel e testes dedicados aprovados |
| PLAN-002 | Generalizar entitlements e contagem por membership | PLAN-03, PLAN-05 | IAM-004, PLAN-001 | pending | Vigência/carência/downgrade coerentes |
| PLAN-003 | Recompensas configuráveis e idempotentes | PLAN-04 | LIB-003, PLAN-002 | pending | Aprovação gera um único crédito com caps |
| APP-001 | Modelo e versões de layout/cabeçalho/cartão | APP-01..03, APP-08 | ASM-001 | pending | Sistema imutável, custom arquivável, histórico preservado |
| APP-002 | Renderizador canônico HTML/CSS e tokens | APP-05..07 | APP-001 | pending | Preview e PDF usam mesmo snapshot |
| APP-003 | Catálogo 10 layouts e 10 cabeçalhos | APP-01..03, APP-09 | APP-002 | pending | Opções distintas, acessíveis e testadas em A4 |
| APP-004 | Editor canvas, galeria, logo e padrões | APP-02, APP-04..06 | APP-003 | pending | Criar/salvar/reabrir/duplicar/versionar/arquivar |
| CARD-001 | Fonte única de geometria e histórico | APP-08, OMR-01, OMR-08 | APP-001 | pending | PDF/Web/Mobile compartilham fixtures |
| CARD-002 | Catálogo de 10 cartões parametrizados | APP-01, APP-09 | CARD-001 | pending | Um motor; 10 organizações funcionais distintas |
| PREV-001 | Previews desktop/tablet/mobile/print | ASM-09, ASM-11, APP-07 | APP-002, ASM-002 | pending | Conteúdo/paginação equivalentes ao resultado final |
| QR-001 | Contrato QR vNext e retrocompatibilidade | QR-01..03 | CARD-001, OMR-P0-001 | pending | v3/v4/v5 preservados; schema e fixtures publicados |
| QR-002 | Regressão raster e homologação física QR | QR-04, APP-09 | QR-001, CARD-002 | pending | Quiet zone, contraste e leitura no envelope |
| OMR-001 | Dataset anotado e baseline reproduzível | OMR-01..06, §9 | CARD-002, QR-002 | pending | Limiares congelados; ground truth e holdout separados |
| OMR-002 | Pipeline real de câmera, geometria e confiança | OMR-02..04, OMR-10, OMR-11 | OMR-001, OMR-P0-003 | pending | Sem métricas simuladas/ajustes normais; ambiguidades revisadas |
| OMR-003 | Sessão multipágina e associação inequívoca | OMR-05..09 | OMR-002, IAM-004 | pending | Todas as páginas consolidam uma vez |
| OMR-004 | Pacote compartilhado e paridade Web/Mobile | OMR-01, OMR-06 | OMR-003 | pending | Golden tests iguais e motores mortos removidos |
| OFF-001 | Fila persistente e dados locais protegidos | OFF-01, OFF-02, OFF-06 | OMR-P0-002 | pending | Estados sobrevivem a reinício; PII/gabarito cifrados |
| OFF-002 | Confirmação final, conflitos e revogação | OFF-03..05 | OFF-001, OMR-003 | pending | 409 estruturado e nenhum last-write-wins silencioso |
| OFF-003 | E2E offline completo | OFF-01..06 | OFF-002, OMR-004 | pending | Modo avião→reinício→reconexão sem perda/duplicação |
| REP-001 | Filtros e KPIs institucionais completos | REP-01..04 | IAM-004, PED-002 | pending | Agregação no banco e escopo tenant |
| UX-001 | Responsividade, estados e acessibilidade AA | ASM-08, ASM-09, §6 | ASM-004, PREV-001, REP-001 | pending | 1440/1280/768/390, teclado, foco e touch 44 px |
| PERF-001 | Índices, paginação, N+1 e budgets | LIB-04, REP-03, §6 | REP-001 | pending | p95/LCP medidos sob baseline documentado |
| QA-001 | Regressão funcional, API, PDF e segurança | Todos | OFF-003, UX-001, PERF-001 | pending | Suítes completas e achados críticos zerados |
| AUDIT-OMR | OMR AUDIT REPORT e parecer de homologação | QR-01..04, OMR-01..11, OFF-01..06, §9 | OMR-004, OFF-003, QA-001 | pending | Todos os limiares, dataset, hardware e limitações explícitos |
| RELEASE-001 | Revisão independente, correções e reteste | Todos | AUDIT-OMR | pending | Parecer aprovado ou pendência humana real |
| RELEASE-002 | Documentação e rollout controlado | Todos | RELEASE-001 | pending | Migração/rollback/observabilidade; deploy só autorizado |

## Baseline registrado

- Laravel: 331 testes, 1.034 asserções.
- JavaScript Web: 11 testes.
- Playwright: 6 cenários em Chromium desktop e Pixel 7.
- Vite build, TypeScript Mobile, Expo Doctor 18/18 e Composer validate aprovados.
- OMR automatizado não equivale a teste com fotografia real; estado inicial continua não homologado.

## Validação da fase 0

- Relatório: `ai-docs/actual-todo/PHASE_0_SECURITY_OMR_2026-08-08.md`.
- Laravel: 352 testes, 1.142 asserções.
- Mobile: 6 testes de contrato/grid, TypeScript e Expo Doctor 18/18.
- Web: 11 testes JavaScript, build Vite e 6 cenários Playwright.
- OMR físico e multipágina Mobile continuam nas tarefas `QR-002`, `OMR-001..004` e `OFF-001..003`; não foram declarados homologados nesta fase.
