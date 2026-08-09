# Fase 10 — Recursos reutilizáveis de questões

**Tarefa:** `LIB-001`  
**Estado:** concluída  
**Parecer independente:** APROVADO  
**Pendência humana:** homologação física de imagens/documentos nos PDFs e impressoras suportadas

## Resultado

Foi implementado o conceito de **Recurso de Questão** separado de `LearningMaterial`, reutilizável por várias questões e fixado a uma versão imutável no pivot. O recurso suporta texto, imagem, gráfico, tabela, fórmula, diagrama e documento, com arquivo privado opcional, conteúdo estruturado e URL externa.

Os quatro escopos de domínio foram modelados: privado, compartilhado com professores específicos, institucional e público da plataforma. A publicação direta no escopo público da plataforma permanece bloqueada; o fluxo de moderação pertence a `LIB-002/LIB-003`.

Questões, avaliações impressas, execução/resultado digital e revisões preservam a versão vinculada. Editar um recurso cria nova versão sem alterar consumidores históricos; duplicar uma questão reutiliza o mesmo recurso/versão/arquivo, sem copiar blobs.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| LIB-01 — escopos pessoal, específico, institucional e público | `Question` possuía visibilidade parcial; não havia recurso reutilizável | Escopos formais, policies tenant-aware, compartilhamentos específicos e publicação pública reservada à moderação | `QuestionResourceTest` e regressão de segurança | Revisor independente | aprovado |
| LIB-02 — recurso versionado e N:M | Conteúdo ficava duplicado no JSON da questão | `question_resources`, versões imutáveis, shares e pivot com versão fixada | Reuso por três questões, v1/v2 e duplicação sem blob | Revisor independente | aprovado |
| LIB-03 — visibilidade do recurso cobre a questão | Sem invariável entre questão e material | Serviço valida privado, conjunto específico, institucional e `platform_public` | Casos positivos/negativos e cross-tenant | Revisor independente | aprovado |
| Histórico de avaliação e revisão | Snapshot não continha recursos | Snapshot inclui conteúdo, versão, hash e referência privada fixa; editor/importador não altera a referência | `ExamPrintService`, `RevisionBuilderService` e testes de arquivo | Revisor independente | aprovado |
| Entrega segura ao aluno | Não existia rota de arquivos do recurso | Rotas contextuais exigem avaliação/tentativa/resultados ou revisão/item/tentativa, vínculo, tenant, path e hash | Testes Web e Feature | Revisor independente | aprovado |

## Alterações técnicas

### Banco

Migration aditiva `2026_08_09_000700_create_question_resources.php`:

- `question_resources`;
- `question_resource_versions`;
- `question_resource_shares`;
- `question_question_resource`.

As versões não são atualizáveis nem removíveis individualmente. Os pivots usam `restrictOnDelete` para impedir perda do histórico. O arquivo permanece no disco privado `local` e registra MIME, tamanho e SHA-256.

### Backend e segurança

- models, policy e serviços dedicados;
- cobertura integral de destinatários no compartilhamento específico;
- isolamento por organização e proteção contra IDOR;
- `platform_public` exige recurso igualmente público;
- upload limitado a JPEG/PNG/WebP/PDF, 10 MB e armazenamento privado;
- resposta de arquivo com `nosniff`, imagem inline e documento como download;
- editor/importador de revisão não podem injetar ou substituir snapshots de arquivos;
- caminhos privados nunca são enviados ao Mobile/API do scanner.

### Web, impressão e revisões

- gerenciador responsivo de materiais com criação, edição, histórico e arquivamento;
- seleção de materiais na criação/edição da questão;
- renderização na execução e no resultado do aluno;
- renderização de texto/imagem em PDF simples e avançado;
- recursos aparecem nas revisões geradas, inclusive imagem/documento/URL autorizados;
- vínculos arquivados ou revogados são preservados e não desaparecem ao salvar a questão.

### Mobile/API

N/A para payload funcional. O scanner não precisa do material para corrigir OMR; nenhum campo foi adicionado ao contrato Mobile. Typecheck e golden tests foram executados para provar não regressão.

## Evidências

- Laravel integral: **426 testes, 1.649 asserções**;
- testes independentes focados: **50 testes, 364 asserções**;
- módulo `QuestionResourceTest`: **6 testes** com cenários compostos;
- JavaScript Web: **11 testes**;
- Playwright existente: **10/10** em Chromium desktop e Pixel 7;
- Playwright específico do gerenciador: **2/2** em desktop e Pixel 7;
- Mobile: TypeScript aprovado e **8/8** testes de contrato/grid;
- Vite build, Blade cache, Pint, Composer validate e `git diff --check`: aprovados;
- migrations locais `000600` e `000700`: aplicadas de forma aditiva para o E2E.

O primeiro E2E após a implementação encontrou `question_question_resource` ausente no banco local. Não era defeito da aplicação: as migrations pendentes foram identificadas por `migrate:status`, aplicadas sem exclusão de dados e o mesmo conjunto passou 10/10 no reteste.

## Revisão independente

O revisor inicialmente encontrou:

1. import ausente de `ValidationException` na duplicação;
2. arquivos que não eram entregues nas revisões;
3. falta de caso explícito para questão pública da plataforma;
4. conflito de compilação Blade entre diretivas `@php`.

Todos os achados foram corrigidos na causa e retestados. O parecer final foi **APROVADO**, sem achados críticos, altos ou médios remanescentes.

## Pendências reais

- validar PDFs com imagens/documentos em papel e impressoras reais;
- implementar filtros/catálogo público completo em `LIB-002`;
- implementar moderação, denúncia, deduplicação e reputação em `LIB-003`;
- recompensas configuráveis continuam dependentes de `LIB-003` e `PLAN-002/003`.
