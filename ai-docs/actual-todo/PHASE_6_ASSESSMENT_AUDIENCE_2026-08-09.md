# Fase 6 — Disciplina e público de Avaliações

**Tarefa:** `ASM-001`

**Requisitos:** `ASM-04`, `IAM-07`
**Parecer:** `APROVADO`

## Resultado

Avaliações agora aceitam disciplina opcional e público composto por turmas, alunos específicos, ambos ou nenhum vínculo imediato. A implementação é aditiva: mantém `exam_school_class`, códigos de acesso, submissões existentes e os contratos históricos, acrescentando o pivot tenant-aware `exam_student`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| Disciplina opcional | Avaliação não tinha disciplina própria | `exams.discipline_id` nullable, relação Eloquent e exibição Web/API | migration, `Exam.php`, controllers e views | criação, persistência, limpeza e API | Revisor independente Codex | APROVADO |
| Turmas e alunos específicos | Somente `exam_school_class` | Novo `exam_student`, sincronização combinada e deduplicação | `ExamAudienceService.php`, `ExamController.php` | público direto, turma e união offline | Revisor independente Codex | APROVADO |
| Nenhum público imediato | Não formalizado na interface | Sincronização vazia válida em rascunho | serviço, view e teste | `test_draft_can_intentionally_have_no_discipline_or_audience` | Revisor independente Codex | APROVADO |
| Isolamento e escopo acadêmico | Endpoint de turmas validava apenas tenant | Opções e gravação limitadas às relações persistentes do professor; administradores permanecem no contexto do workspace | `ExamAudienceService.php` | aluno sem vínculo e disciplina cross-tenant rejeitados atomicamente | Revisor independente Codex | APROVADO |
| Compatibilidade histórica | Turmas e código já concediam acesso | Rota `syncClasses`, pivot antigo, código, submissão própria e acesso por turma preservados | rotas, `ExamAccessService.php`, portal | turma histórica e código de acesso | Revisor independente Codex | APROVADO |
| Contrato Mobile | Download retornava apenas alunos das turmas | União deduplicada de turma + aluno direto e disciplina opcional tipada | `ExamApiController.php`, `duoscanner/src/types/exam.ts` | API, TypeScript e grid mapping | Revisor independente Codex | APROVADO |
| Duplicação segura | Origem era filtrada apenas por workspace | Duplicação exige autoria e preserva disciplina somente da própria Avaliação | `ExamController.php` | IDOR entre professores do mesmo tenant retorna 404 | Revisor independente Codex | APROVADO |

## Alterações técnicas

- Migration aditiva e reversível `2026_08_09_000500_add_exam_discipline_and_direct_audience.php`.
- Serviço `ExamAudienceService` como fonte única para opções autorizadas, validação, sincronização auditada e resolução deduplicada de alunos.
- Evento de auditoria `exam_audience_updated` com estado anterior e posterior.
- Portal do aluno reconhece vínculo direto além de turma, grant por código e submissão própria.
- Resultados do professor unem público atual e autores de submissões históricas.
- API v1/v2 mantém os campos existentes e adiciona `discipline` opcional; o tipo Mobile aceita payloads antigos sem esse campo.
- Duplicação preserva a disciplina, mas continua sem copiar público, mantendo o comportamento seguro anterior.

## Evidências

- Baseline antes da mudança: 16 testes focados, 104 assertivas.
- Nova suíte `ExamAudienceTest`: 7 testes, 45 assertivas.
- Regressão focada final: 23 testes, 149 assertivas.
- Regressão Laravel completa: 398 testes, 1.404 assertivas.
- JavaScript Web: 11 testes aprovados.
- Build Vite de produção aprovado.
- Mobile: `tsc --noEmit` aprovado; 6 testes de grid/contrato aprovados.
- Blade: cache/compilação de todas as views aprovado.
- Pint: aprovado nos arquivos PHP alterados.
- Migration SQLite: `fresh → rollback da fase → migrate` aprovado.

## Revisão independente

O revisor independente executou testes focados de relações acadêmicas, portal, isolamento, segurança e policies; validou rotas nova e legada, Blade, Pint e o diff. Na primeira passagem, pediu o alinhamento opcional do contrato TypeScript do Mobile. Na passagem incremental, reprovou a duplicação por um IDOR preexistente entre autores do mesmo tenant que passaria a copiar disciplina fora do escopo. A origem passou a exigir autoria, recebeu teste negativo dedicado e foi retestada. O parecer final foi `APROVADO`, sem achados bloqueantes.

## Pendências reais

- Homologação visual manual da nova área de público em tamanhos de tela reais é recomendada, embora a view compile e use os componentes responsivos existentes.
- Cópias individualizadas por aluno e a jornada completa de introdução permanecem nas tarefas dependentes `ASM-003` e `ASM-004`.
- O wizard recuperável de oito etapas permanece em `ASM-002`; esta fase não ampliou o escopo para esse fluxo.
