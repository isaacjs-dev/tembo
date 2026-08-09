# Lessons Learned

> Log of mistakes, problems, and discoveries made during development.
> Use `/learning` to add a new lesson. Each entry should help avoid
> repeating the same mistake in the future.

---

## How to read this file

Each lesson follows the format:

- **Date:** when the mistake was made / lesson was learned.
- **Context:** what was being done (task, file, feature).
- **What went wrong:** clear description of the problem.
- **Root cause:** why it happened.
- **How to avoid:** an actionable rule for future tasks.
- **Tags:** keywords for fast search (e.g., `auth`, `forms`, `database`, `styling`).

---

## Lessons

<!-- New lessons will be added below this comment by the /learning command -->

### Contagem de progresso em tabelas Markdown

- **Date:** 2026-08-09
- **Context:** estimativa de progresso do roadmap em `task-master.md` durante `LIB-001`.
- **What went wrong:** a expressão usada para contar IDs também aceitou a linha separadora `| --- |`, inflando o total em uma tarefa.
- **Root cause:** o padrão `[A-Z0-9-]+` aceitava uma sequência composta apenas por hífens.
- **How to avoid:** validar IDs com ao menos uma letra ou número e conferir se `done + pending + in_progress + blocked = total` antes de informar percentuais.
- **Tags:** roadmap, métricas, markdown, comunicação
