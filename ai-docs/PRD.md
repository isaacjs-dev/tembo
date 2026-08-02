# PRD — {{Product Name}}

**Document:** Product Requirements Document
**Product:** {{Product Name}} — {{one-line tagline}}
**Version:** {{0.1}}
**Last updated:** {{YYYY-MM-DD}}
**Author:** {{your name}}

> **How to use this file.** Replace every `{{placeholder}}` with real content,
> then run `/create-tasks`. `/create-tasks` refuses to run while any
> `{{placeholder}}` remains — that is the signal the PRD is still a draft.
> Delete sections that don't apply and add any your product needs; the
> headings below are the minimum `task-master-generator` reads to plan the
> build. Write the whole PRD in the language you want the project built in
> (the agents match the PRD's language — English by default).

## 1. Overview

{{2–4 sentences: what the product is, who it's for, and the core value
proposition. Write it so someone unfamiliar with the project understands the
point in 30 seconds.}}

## 2. Goals and non-goals

**Goals**

- {{Primary outcome 1}}
- {{Primary outcome 2}}

**Non-goals (explicitly out of scope for this build)**

- {{Something you are deliberately NOT doing — no task will be created for it}}

## 3. Target users

- **{{Persona / segment}}** — {{who they are, what they need, how they'll use it}}

## 4. Functional requirements

> The heart of the PRD — each requirement becomes one or more tasks. Be
> concrete and testable. Give each a stable ID (`FR-1`, `FR-2`, …) so tasks
> can cite it. Group by feature area.

### 4.1 {{Feature area — e.g. Authentication}}

- **FR-1:** {{The system shall …}}
- **FR-2:** {{…}}

### 4.2 {{Feature area — e.g. Dashboard}}

- **FR-3:** {{…}}

## 5. Non-functional requirements

- **Performance:** {{e.g. pages interactive < 2s on a mid-tier phone}}
- **Security:** {{e.g. all external input validated; authorization on every mutation}}
- **Accessibility:** {{e.g. WCAG 2.1 AA}}
- **Browser / device support:** {{…}}
- **Other:** {{observability, i18n, compliance, SEO — whatever applies}}

## 6. Tech stack

> List exact versions where the stack is already decided. `task-master-generator`
> reads this to avoid creating "set up X" tasks for things already chosen, and
> every agent uses it to pick the right patterns. Keep it consistent with the
> "Project-specific notes" in `CLAUDE.md` and the `## Project configuration`
> block in `.claude/agents/quality-checklist-verifier.md`.

- **Language(s):** {{e.g. TypeScript}}
- **Frontend:** {{e.g. Next.js 16 App Router, React 19, Tailwind, shadcn/ui}}
- **Backend / API:** {{e.g. Next.js Route Handlers / Hono / Express / none}}
- **Database:** {{e.g. Convex / Postgres + Prisma / Supabase}}
- **Auth:** {{e.g. Clerk / Auth.js / custom — name the guard helpers}}
- **Hosting / infra:** {{e.g. Vercel / Cloudflare}}
- **Package manager:** {{npm | pnpm | yarn | bun}}
- **Testing:** {{e.g. Vitest + Playwright}}

## 7. Data model

> Sketch the main entities, their key fields, and how they relate.

- **{{Entity}}** — fields: {{…}}; relates to {{…}}

## 8. Integrations

> Third-party services. Flag the ones that need accounts or API keys the user
> must set up before a task can run.

- **{{Service}}** — {{what it's used for}}; secrets needed: `{{ENV_VAR_NAMES}}`

## 9. Milestones

> Optional. Rough phase ordering. `task-master-generator` sequences tasks with
> explicit dependencies regardless, but phases help it group related work.

1. **{{Phase 0 — Foundation}}:** {{…}}
2. **{{Phase 1 — …}}:** {{…}}

## 10. Open questions

- {{Anything still undecided. Resolve before `/create-tasks` where possible —
  an ambiguous PRD produces ambiguous tasks.}}
