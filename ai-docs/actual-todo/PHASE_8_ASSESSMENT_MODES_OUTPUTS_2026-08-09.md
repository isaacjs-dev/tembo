# Fase 8 — Modalidades, outputs e cópias versionadas

**Tarefa:** `ASM-003`

**Requisitos:** `ASM-01`, `ASM-05`, `ASM-06`, `ASM-07`, `ASM-10`, `ASM-11`  
**Parecer:** `APROVADO COM PENDÊNCIA HUMANA`

## Resultado

As Avaliações agora distinguem aplicação 100% online, impressa com resposta digital, impressa com cartão OMR e OMR offline, preservando os modos legados. A geração permite Avaliação, cartão-resposta, ambos ou gabarito do professor como saídas explícitas. Cópias podem ser individualizadas por aluno e preservam versão, lote, template e snapshot imutável de questões, pontos e gabarito. A execução digital suporta Avaliação inteira, quantidade fixa por tela e agrupamento automático.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| ASM-01 — modalidades | `online`, `paper` e `hybrid` misturavam fluxos distintos | Resolvedor central de capacidades com quatro modos explícitos e leitura dos legados | `ExamApplicationModeService.php`, controller e views | unitários, configuração, portal e E2E | Revisor independente Codex | APROVADO |
| ASM-05 — regras existentes | Horários, tentativas, códigos e liberação já existiam | Contratos foram preservados e revalidados com as novas modalidades | `ExamAccessService.php`, wizard e controller | configuração, wizard e portal | Revisor independente Codex | APROVADO |
| ASM-06 — cópias por aluno | Cópia guardava somente número e mapas | Aluno, lote UUID, versão sequencial, numeração estável e maps reversíveis | migration, `ExamCopy.php`, `ExamPrintService.php` | individualização, lotes e API | Revisor independente Codex | APROVADO |
| ASM-07 — outputs explícitos | Lote avançado sempre anexava o gabarito | `exam`, `answer_sheet`, `both` e `answer_key`; gabarito nunca é implícito | controller, modal e `pdf_advanced.blade.php` | isolamento das seções PDF | Revisor independente Codex | APROVADO |
| ASM-10 — histórico | Gabarito e template dependiam do estado vivo | Snapshot de questões/pontos/gabarito e template/versão; hard delete de template usado é bloqueado | migration, impressão, grading, template controller e API | alteração posterior, correção histórica e exclusão | Revisor independente Codex | APROVADO |
| ASM-11 — apresentação digital | Todas as questões apareciam em uma tela | Modos `full`, `paginated` e `auto`, mantendo um único formulário/autosave | `ExamPresentationService.php`, portal e view de execução | unitário, Feature e E2E | Revisor independente Codex | APROVADO |
| Associação OMR | Cópia não possuía aluno autoritativo | Upload/confirm rejeitam divergência e preenchem o aluno da cópia individualizada | controllers OMR | segurança/API | Revisor independente Codex | APROVADO |
| Contrato Mobile | Cache confundia versão da Avaliação com layout | API aditiva expõe versão e snapshots; Mobile usa a chave histórica para corrigir | API, tipos, store e grading Mobile | typecheck e teste de correção histórica | Revisor independente Codex | APROVADO |

## Alterações técnicas

- Migration aditiva em `exam_copies`, preservando registros e IDs históricos.
- Snapshots JSON guardam conteúdo, tipo, alternativas, gabarito, pontos, ordem e disciplina; maps de questões e alternativas continuam compatíveis.
- A versão da Avaliação avança sob lock quando uma nova geração detecta mudança no snapshot acadêmico.
- O template usado é registrado por ID, versão e snapshot de layout/cabeçalho/logo; templates históricos usados deixam de admitir exclusão física.
- A API mantém os campos v1 existentes e acrescenta versão e metadados opcionais; cópias são ordenadas deterministicamente.
- O Mobile prefere `question_snapshot` na correção e conserva fallback para cópias antigas.
- Turmas de impressão precisam pertencer ao público da Avaliação; individualização usa o público deduplicado e limita cada PDF a 200 alunos.
- A apresentação em blocos não altera IDs, submissão, autosave, embaralhamento ou contrato de respostas.

## Evidências

- Baseline focado: 37 testes, 233 assertivas.
- Regressão Laravel completa: 417 testes, 1.543 assertivas.
- JavaScript Web: 11 testes aprovados.
- Mobile: typecheck e 8 testes aprovados.
- E2E: 8 cenários aprovados em Chromium desktop e mobile.
- Build Vite, cache/compilação Blade, Pint nos arquivos da fase e `git diff --check`: aprovados.
- PDFs reais continuam cobertos pela regressão OMR, incluindo multipágina e separação de seções.

## Revisão independente

A primeira passagem encontrou dois problemas: alunos diretos com membership revogada ainda podiam permanecer no público efetivo; e o Mobile conhecia `copy.student_id`, mas ainda permitia seleção divergente ou ausência de aluno. A correção revalida membership ativa no público e no upload, vincula automaticamente o aluno da cópia e bloqueia troca/“pular” no fluxo Mobile individualizado.

O reteste independente aprovou 35 testes Laravel/206 assertivas, typecheck e 8 testes Mobile, além de confrontar modalidades legadas/novas, outputs, tenant, snapshots, QR/OMR, API aditiva, apresentação digital e migration. Parecer final: `APROVADO COM PENDÊNCIA HUMANA`, sem achados críticos, altos ou médios remanescentes.

## Pendências reais

- O cache Mobile ainda mantém uma linha corrente por `exam_id`. Scans pendentes conservam seus IDs/payloads e o servidor corrige pelo snapshot da cópia, mas a coexistência explícita de múltiplos pacotes locais até a sincronização pertence a `ASM-008` (`OFF-01..05`).
- Impressão e leitura OMR física em diferentes papéis, impressoras e aparelhos exigem homologação humana e o dataset das fases `ASM-006`/`ASM-007`.
- Previews completos de impressão, desktop, tablet e celular permanecem em `ASM-004`/`ASM-005`; esta fase entrega o mecanismo de apresentação digital.
- A execução global de Pint ainda aponta um import não usado preexistente em `MonthlyUsageAndCourtesyTest.php`, arquivo fora do diff desta fase; a formatação focada das alterações está aprovada.
