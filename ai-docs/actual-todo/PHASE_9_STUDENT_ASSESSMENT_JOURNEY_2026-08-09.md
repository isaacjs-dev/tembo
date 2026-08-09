# Fase 9 — Jornada de Avaliações do aluno

**Tarefa:** `ASM-004`

**Requisitos:** `ASM-08`, parcela de jornada responsiva de `ASM-09`  
**Parecer:** `APROVADO`

## Resultado

O Portal do Aluno agora apresenta, de forma consistente e responsiva, professor, instituição, disciplina, modalidade, quantidade de questões, duração, tentativas, abertura, prazo e estado da Avaliação. A introdução permanece informativa antes da abertura e depois do encerramento, sem liberar início, execução, autosave ou envio fora da janela. O histórico preserva tentativas anteriores e mantém acesso a uma correção já liberada mesmo quando há nova tentativa em andamento.

Nota, respostas e feedback continuam condicionados à liberação configurada e à janela formal de resultados. A apresentação canônica completa de impressão/desktop/tablet/celular não foi absorvida nesta fase e continua rastreada em `PREV-001`/`APP-002`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| ASM-08 — identificação | Introdução mostrava título e dados operacionais, sem professor, instituição ou disciplina | Metadados acadêmicos no painel, introdução, execução e resultado, com fallback explícito | controller e quatro views do aluno | Feature e E2E | Revisor independente Codex | APROVADO |
| ASM-08 — janela e estado | Overview era bloqueado junto com o início; somente o fechamento era exibido | Overview informativo para `published`/`closed`, abertura e prazo no fuso da aplicação, estados e mensagens coerentes | `ExamAccessService.php`, controller e views | janela futura, encerrada e reagendada | Revisor independente Codex | APROVADO |
| ASM-08 — tentativas | Painel considerava apenas a tentativa mais recente | Última tentativa e última corrigida são separadas; histórico mostra início, conclusão e prazo sem ocultar resultado anterior | controller, dashboard e introdução | múltiplas tentativas e resultado anterior | Revisor independente Codex | APROVADO |
| ASM-08 — liberação | Rota de resultado possuía gate, mas o novo overview precisava reutilizá-lo | `resultsCanBeViewed()` centraliza status, janela e release; nota/CTA/histórico respeitam o mesmo gate | `ExamAccessService.php`, controller e views | score/CTA negados em overview futuro | Revisor independente Codex | APROVADO |
| ASM-08 — resultado | Resultado não repetia o contexto acadêmico | Resumo responsivo com tentativa, professor, instituição, disciplina, modalidade, conclusão e prazo | `exam_results.blade.php` | Feature, segurança e E2E | Revisor independente Codex | APROVADO |
| ASM-09 — jornada responsiva | Views eram mobile-first, sem cenário E2E da Avaliação do aluno | Smoke E2E do painel e resultado em desktop e Pixel 7, incluindo verificação de overflow | `demo-flows.spec.ts` | Playwright 2 viewports | Revisor independente Codex | APROVADO |

## Alterações técnicas

- `ExamAccessService` distingue overview informativo da janela ativa e expõe rótulo central de modalidade e gate de resultados.
- `StudentPortalController` eager-loads autor, organização e disciplina, evitando N+1 nos cartões; também separa a tentativa corrente da última corrigida.
- `canStart` exige status publicado, janela aberta, modalidade digital, tentativas disponíveis e nenhuma tentativa em andamento.
- Datas de abertura, encerramento, conclusão e liberação são renderizadas em `config('app.timezone')`.
- A fonte temporal canônica da entrega é `finished_at`; não foi criado campo ou contrato duplicado.
- Banco/migrations: N/A.
- API, DTO, QR, OMR e formato offline: N/A.
- Mobile nativo: N/A; a validação mobile desta fase refere-se ao viewport Web Pixel 7.

## Evidências

- Baseline: 24 testes Laravel, 151 asserções.
- Testes focados finais de portal, configuração, guardian e segurança: 49 testes, 309 asserções.
- Regressão Laravel completa final: 420 testes, 1.591 asserções.
- JavaScript Web: 11 testes aprovados.
- Playwright: 10 cenários aprovados, incluindo o novo fluxo do aluno em Chromium desktop e Pixel 7, sem overflow horizontal.
- Build Vite, cache/compilação Blade, Pint focado e `git diff --check`: aprovados.

Uma primeira tentativa de regressão completa falhou porque o build Vite foi executado em paralelo e removeu temporariamente `public/build/manifest.json`. A validação foi reorganizada sequencialmente; após o build, a mesma suíte passou integralmente. A falha não foi atribuída ao produto nem ocultada.

## Revisão independente

A revisão encontrou três problemas objetivos:

1. as telas novas liam `submitted_at`, mas o modelo persiste a conclusão em `finished_at`;
2. uma Avaliação reagendada para o futuro podia exibir score/CTA no overview, divergindo da rota protegida de resultados;
3. uma tentativa em andamento reagendada dizia incorretamente que a janela estava encerrada.

As correções passaram a usar `finished_at`, centralizaram `resultsCanBeViewed()` e diferenciaram janela futura de encerrada. O reteste independente aprovou 58 testes/341 asserções, build, JavaScript e diff-check. Parecer final: `APROVADO`, sem achados bloqueantes remanescentes.

## Pendências reais

- `PREV-001` e `APP-002`: previews canônicos de impressão, desktop, tablet e celular, com equivalência ao resultado final/PDF.
- Homologação visual humana adicional em navegadores e dispositivos físicos continua recomendada para acessibilidade e variações de fonte, mas não há bloqueio automatizado conhecido nesta fase.
- O escopo desta fase não altera a execução OMR, QR, sincronização offline ou contratos Mobile.
