# Fase 2 — Relações acadêmicas tenant-aware

**Data:** 2026-08-08
**Tarefa:** `IAM-003`
**Parecer:** APROVADO COM PENDÊNCIA HUMANA

## Resultado

As relações Professor ↔ Turma, Professor ↔ Aluno, Professor ↔ Disciplina e Turma ↔ Disciplina agora possuem persistência explícita e contextual por workspace. A administração institucional pode configurá-las nas telas de professor e turma; em workspace pessoal, o proprietário da turma é reconhecido como professor responsável.

A matrícula de um aluno, tanto pelo cadastro administrativo quanto pelo aceite de convite, materializa de forma idempotente os vínculos com professores ativos da turma. Relações derivadas ignoram membros inativos e registros de outro tenant, mesmo diante de pivôs históricos inconsistentes.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| IAM-03 — professor em várias relações | Somente `class_teacher` existia; não havia relação persistente com aluno ou disciplina | Novos pivôs `teacher_student` e `discipline_teacher`, com organização, autoria e unicidade | migration, models, `AcademicRelationshipService.php` | `AcademicRelationshipsTest` | Revisão local independente | APROVADO |
| IAM-07 — turma e disciplina | Não havia vínculo Turma ↔ Disciplina | Pivot `class_discipline` tenant-aware e formulários de criação/edição | migration, `SchoolClassController.php`, views de turma | Atualização HTTP e persistência | Revisão local independente | APROVADO |
| Matrícula sem duplicação | `class_student` não possuía índice único | Preflight contra dados duplicados e índice único por turma/aluno | migration | Inserção idempotente e regressão completa | Revisão local independente | APROVADO |
| Propagação Professor ↔ Aluno | Matrícula não criava a relação acadêmica explícita | Cadastro, edição e aceite de convite derivam somente professores/alunos ativos do tenant | `StudentController.php`, `UserLinkerService.php`, serviço acadêmico | Convite, workspace divergente, membership inativo e pivô corrompido | Revisão local independente | APROVADO |
| Autorização no backend | Sincronização era feita diretamente pelo controller | Serviço central valida ator, workspace e cada ID antes de alterar; operações são transacionais | `AcademicRelationshipService.php`, controllers | Ator comum recebe 403; IDs externos não alteram relações existentes | Revisão local independente | APROVADO |
| Conta global preservada | Risco de confundir relação acadêmica com tipo global | Edição altera somente membership/contexto e pivôs acadêmicos | `TeacherController.php`, models | Conta multi-workspace mantém senha e segundo membership | Revisão local independente | APROVADO |

## Alterações técnicas

- Banco: migration aditiva cria `teacher_student`, `discipline_teacher` e `class_discipline`; adiciona unicidade a `class_student` somente depois de verificar que não há duplicatas.
- Backend: `AcademicRelationshipService` centraliza validação tenant-aware, sincronização transacional, idempotência, derivação por turma e auditoria before/after.
- Models: relações Eloquent adicionadas a `User`, `SchoolClass` e `Discipline`.
- Web: telas de turma permitem disciplinas e professores; tela de professor agrupa turmas, alunos diretos e disciplinas com estados vazios.
- Matrículas: cadastro/edição administrativa e aceite de convite atualizam a relação professor–aluno sem duplicar linhas.
- Segurança: cada ID é revalidado no backend; convite cuja organização diverge da turma é revertido; membros inativos/externos são ignorados na derivação.
- Auditoria: sincronizações registram ator implícito da sessão, organização e estado before/after.
- API, Mobile, QR, OMR e formatos offline: N/A; nenhum contrato desses domínios foi alterado nesta fase.

## Evidências

- Testes focados finais: `32 passed (133 assertions)` para relações acadêmicas, matrícula/propriedade de turma e isolamento tenant.
- Regressão Laravel completa final: `375 passed (1248 assertions)`.
- Web JavaScript: `11 passed`.
- Playwright: `6 passed` em desktop e mobile.
- Vite: build de produção aprovado.
- Composer: manifesto válido.
- Pint dos arquivos em escopo: aprovado.
- `git diff --check`: aprovado antes da documentação final.
- O comando `npm test` não existe no manifesto; os comandos reais `npm run test:js` e `npm run test:e2e` foram executados.

## Revisão independente

Os agentes delegados de backend e UX permaneceram indisponíveis por limite externo de uso. Uma passagem local separada, realizada depois da implementação inicial, encontrou um risco concreto: a derivação Professor ↔ Aluno confiava nos pivôs de turma sem reconfirmar membership ativo e tenant de cada participante.

A correção passou a filtrar professores e alunos pelo membership ativo da organização da turma. Foram adicionados cenários com professor inativo, professor externo, aluno externo e ator comum tentando executar a materialização. O reteste focado foi aprovado. Parecer técnico: **APROVADO COM PENDÊNCIA HUMANA**.

## Pendências reais

- Antes do deploy, consultar duplicatas em `class_student` no staging. A migration interrompe de forma segura se encontrar alguma, exigindo consolidação auditada em vez de exclusão automática.
- Executar a migration em staging com backup e validar `up`/rollback no SGBD de produção.
- Fazer smoke test humano das listas de seleção com volume real de turmas, alunos e disciplinas, incluindo teclado e dispositivos móveis.
- A matriz completa de policies, cargos customizados, convites sem senha provisória e regras formais de desvinculação segue em `IAM-004`.
