# Fase 7 — Wizard recuperável de Avaliações

**Tarefa:** `ASM-002`

**Requisitos:** `ASM-02`, `ASM-03`  
**Parecer:** `APROVADO`

## Resultado

A criação e a edição de Avaliações agora usam uma jornada progressiva de oito etapas: Informações, Questões, Público, Aplicação, Aparência, Cartão-resposta, Pré-visualização e Publicação. O rascunho mantém estado versionado e recuperável em `settings._wizard`, com autosave, controle otimista de concorrência, validação no backend e auditoria dos checkpoints concluídos.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| Oito etapas progressivas | Editor concentrava configurações e ferramentas na mesma superfície | Navegação responsiva com progressive disclosure e controles anterior/próximo | views `exams/create` e `exams/edit` | Feature de renderização e E2E desktop/mobile | Revisor independente Codex | APROVADO |
| Rascunho recuperável | Não havia estado formal de progresso | Contrato `_wizard` versionado com etapa atual, concluídas, revisão e data | `ExamWizardService.php` | criação, recuperação e mesclagem | Revisor independente Codex | APROVADO |
| Autosave seguro | Formulários dependiam de envio manual | Endpoint PATCH restrito a rascunhos, tenant e autor, com lock e revisão otimista | serviço, controller e rota | stale revision, autor alheio e status publicado | Revisor independente Codex | APROVADO |
| Concorrência entre formulários | Autosave e PUT poderiam divergir | PUT tradicional participa do mesmo lock/revisionamento e rejeita aba obsoleta | controller, view e serviço | PUT incrementa revisão e autosave antigo recebe 409 | Revisor independente Codex | APROVADO |
| Validação por etapa | Gate de publicação cobria somente parte do contrato | Payloads tipados; etapas de conteúdo exigem questões pontuadas; publicação revalida aplicação e liberação | serviço e controller | datas, questões vazias, pontuação e contrato persistido inválido | Revisor independente Codex | APROVADO |
| Publicação sem perda | Clique imediato podia competir com debounce | Submit aguarda o checkpoint pendente, sincroniza revisão e somente então envia o formulário | `edit.blade.php` | checkpoint + PUT compartilham a revisão mais recente | Revisor independente Codex | APROVADO |
| Auditoria | Não havia checkpoint formal do wizard | Evento `exam_wizard_step_saved` registra etapa, versão, revisão e primeira conclusão | `ExamWizardService.php` e controller | auditoria persistida | Revisor independente Codex | APROVADO |
| Compatibilidade Mobile | Metadado interno poderia vazar pelo download | `_wizard` é removido do payload de API; configurações funcionais permanecem | `ExamApiController.php` | contrato API e typecheck Mobile | Revisor independente Codex | APROVADO |

## Alterações técnicas

- Novo `ExamWizardService` como fonte de verdade para estado, regras, autosave, revisão e checkpoints.
- Novo endpoint `PATCH /exams/{exam}/draft`, protegido pelo mesmo tenant e autoria da edição.
- Estado armazenado aditivamente no JSON existente, sem migration e sem alterar contratos históricos do banco.
- O envio PUT usa `lockForUpdate`; clientes que informam `wizard_revision` recebem `409` em conflito e clientes históricos continuam compatíveis.
- Autosave é desativado para Avaliações publicadas ou encerradas; alterações deliberadas continuam pelo fluxo tradicional auditado.
- Duplicações recebem um novo estado independente, sem carregar progresso ou revisão da origem.
- A API Mobile omite somente o metadado interno `_wizard` e preserva as demais configurações.

## Evidências

- Baseline focado anterior: 11 testes, 71 assertivas.
- Testes focados do wizard/configuração/público: 21 testes, 141 assertivas na revisão independente.
- Regressão de segurança, policies e tenant: 64 testes, 363 assertivas.
- Regressão Laravel completa: 408 testes, 1.474 assertivas.
- JavaScript Web: 11 testes aprovados.
- E2E do wizard: 2 cenários aprovados, Chromium desktop e mobile.
- Build Vite, compilação/cache Blade, Pint e `git diff --check`: aprovados.
- Mobile: `tsc --noEmit` aprovado.

## Revisão independente

A primeira passagem reprovou quatro pontos: risco de perder campos ao publicar antes do debounce; concorrência incompleta entre autosave e PUT; conclusão de etapas de conteúdo sem validação; e autosave indevido em Avaliações publicadas/encerradas. Todos foram corrigidos na causa e receberam testes dedicados. A segunda passagem confirmou lock e revisão atômicos, flush antes da publicação, gates de conteúdo/publicação, restrição a rascunhos, auditoria e compatibilidade. Parecer final: `APROVADO`, sem achados bloqueantes.

## Pendências reais

- Homologação visual manual em dispositivos físicos continua recomendada; o fluxo foi validado em view compilada e navegadores desktop/mobile automatizados.
- As etapas expõem e organizam os recursos existentes. Modalidades, outputs e cópias versionadas adicionais pertencem a `ASM-003`.
- A jornada completa de introdução e execução do aluno permanece em `ASM-004`.
