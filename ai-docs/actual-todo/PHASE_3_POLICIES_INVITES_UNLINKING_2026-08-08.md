# Fase 3 — Policies, convites e desvinculação

**Data:** 2026-08-08
**Tarefa:** `IAM-004`
**Parecer:** APROVADO COM PENDÊNCIA HUMANA

## Resultado

A autorização institucional passou a usar uma matriz central e contextual. Diretor, Coordenador e Pedagogo possuem limites explícitos; cargos customizados só concedem permissões quando pertencem ao workspace atual e estão ativos. As rotas de professores, alunos, responsáveis, turmas, convites, relatórios, taxonomia e OMR aplicam os gates correspondentes no backend.

Convites agora enviam uma notificação com link de ativação. Uma pessoa sem conta define a própria senha, recebe o membership do convite e confirma o e-mail. Contas existentes não são duplicadas. Cadastros administrativos legados de professor/aluno passaram a usar senha explicitamente provisória, verificação de e-mail e troca obrigatória.

## Matriz de permissões nativa

| Papel | Gestão acadêmica | Convites permitidos | Cargos/configurações/plano | OMR institucional |
| --- | --- | --- | --- | --- |
| Conta institucional / admin | Completa no tenant | Diretor, Coordenador, Pedagogo, Professor e Aluno | Permitido; billing ainda exige proprietário | Web permitido; Mobile bloqueado |
| Diretor | Professores, alunos, turmas, conteúdo, relatórios e OMR | Coordenador, Pedagogo, Professor e Aluno | Bloqueado | Web permitido; Mobile bloqueado |
| Coordenador | Professores, alunos, turmas, conteýo, relatórios e OMR | Professor e Aluno | Bloqueado | Web permitido; Mobile bloqueado |
| Pedagogo | Leitura acadêmica, relatórios, revisões e OMR | Nenhum | Bloqueado | Web conforme leitura; Mobile bloqueado |
| Professor | Autoria e escopo próprio; turma pessoal quando aplicável | Matrícula dentro do fluxo autorizado | Bloqueado | Web/Mobile no próprio escopo e plano |
| Aluno | Portal e dados próprios | Nenhum | Bloqueado | Bloqueado |

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| IAM-05 — ativação pelo titular | Convites não enviavam e-mail e não havia ativação para conta nova | Notificação enfileirável, tela pública, senha definida pelo titular, membership e verificação | Conta nova, conta existente e e-mail normalizado | Revisão local independente | APROVADO |
| IAM-06 — conta global preservada | `unlink` não validava o ator | Desvinculação exige permissão contextual, inativa apenas o membership e preserva conta/outros workspaces | Desvinculação e regressão tenant | Revisão local independente | APROVADO |
| IAM-08 — matriz formal | Cargos customizados existiam, mas o middleware não protegia as rotas administrativas | `InstitutionPermissionService`, papéis nativos, cargos customizados ativos e navegação condicional | Diretor, Coordenador, Pedagogo, cargo ativo/inativo | Revisão local independente | APROVADO |
| IAM-10 — correção institucional Web | O mesmo middleware OMR servia Web e API Mobile | Gestores autorizados corrigem na Web; API do scanner exige papel contextual Professor | Web 200 e API 403 com erro contratual | Revisão local independente | APROVADO |
| IAM-11 — IDs contextualizados | Atribuição de cargo e cancelamento de convite aceitavam IDs externos | `Rule::exists` por tenant e validação novamente nos serviços/controllers | Usuário, cargo e convite cross-tenant rejeitados | Revisão local independente | APROVADO |
| IAM-12 — auditoria sensível | Atribuição/desvinculação tinham contexto incompleto | Payload com organização, ator e before/after nas operações desta fase | Asserções de banco e regressão de auditoria | Revisão local independente | APROVADO PARCIAL; infraestrutura unificada segue em `AUD-001` |

## Alterações técnicas

- Backend: serviço central de permissão institucional e integração nos middlewares Web/OMR.
- Rotas: leitura e gestão separadas para professores, alunos, responsáveis e convites; configuração, cargos e billing continuam exclusivos de gestores proprietários.
- Convites: papéis convidáveis dependem do papel do ator; Diretor não promove Diretor e Coordenador não promove cargos superiores.
- Identidade: ativação pública por token, senha do titular, verificação obrigatória e reutilização segura de conta existente.
- E-mail: normalizado em minúsculas no envio e comparado sem sensibilidade a caixa no aceite/recusa.
- UI: menus renderizados pelas permissões contextuais; formulários deixam claro quando uma senha administrativa é provisória.
- Mobile/API: gestores institucionais recebem bloqueio explícito no scanner; o fluxo institucional permanece somente Web.
- Banco/migrations: N/A.

## Evidências

- Regressão Laravel completa final: `384 passed (1303 assertions)`.
- Policies/cargos/convites/verificação: `37 passed (139 assertions)`.
- Segurança/OMR/matriz: `37 passed (177 assertions)`.
- JavaScript/Vitest: `11 passed` em 2 arquivos.
- E2E/Playwright: `6 passed` nos perfis desktop e mobile.
- Build de produção/Vite: `133 modules transformed`, concluído sem erro.
- Composer: `composer.json` válido.
- Blade: cache/compilação aprovado.
- Pint dos arquivos em escopo: aprovado nas rodadas focadas.

## Revisão independente

A passagem separada de revisão encontrou e corrigiu:

1. cargos institucionais gravados no banco, mas sem enforcement real nas rotas;
2. cargo inativo ainda capaz de aparentar permissão;
3. atribuição de cargo e cancelamento de convite por IDs de outro tenant;
4. Diretor/Coordenador capazes de receber opção de escalada incompatível;
5. convite sem e-mail ou fluxo de ativação para pessoa sem conta;
6. comparação de e-mail sensível a maiúsculas/minúsculas;
7. gestor institucional autorizado indevidamente a usar a API Mobile de correção;
8. senha criada por terceiro sem marcação obrigatória de troca.

Os achados receberam correção e testes de regressão. Parecer: **APROVADO COM PENDÊNCIA HUMANA**.

## Pendências reais

- Configurar e monitorar worker de fila e provedor de e-mail em staging; homologar entrega, spam, expiração e links externos.
- Fazer inspeção visual dos menus de Diretor, Coordenador e Pedagogo em desktop/mobile.
- A infraestrutura completa de auditoria com colunas normalizadas para request ID, origem e filtros segue deliberadamente em `AUD-001`.
- A generalização das policies de cada módulo pedagógico continuará nas tarefas `PED-001`, `LIB-001` e `ASM-001`, sem ampliar acesso por inferência nesta fase.
