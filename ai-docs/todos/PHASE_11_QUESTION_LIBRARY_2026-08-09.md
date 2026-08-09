# Fase 11 — Biblioteca de questões e recursos

**Tarefa:** `LIB-002`  
**Estado:** concluída  
**Parecer independente:** APROVADO  
**Pendência humana:** nenhuma para esta fase

## Resultado

A biblioteca agora separa explicitamente questões e recursos nos escopos **pessoal**, **compartilhado específico**, **institucional** e **público da plataforma**. O nome técnico histórico `org_public` foi preservado para o escopo institucional, sem confundi-lo na interface com o catálogo público global.

Conteúdo `platform_public` pode ser consultado entre organizações, mas permanece somente leitura. Para usar uma questão pública global, o professor cria uma cópia privada e rastreada no próprio workspace; a aplicação não permite vinculá-la diretamente a avaliações ou atividades de outro tenant. Os formulários comuns continuam proibidos de publicar diretamente no catálogo global, pois submissão e moderação pertencem a `LIB-003`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| LIB-01 — quatro escopos de questões | A biblioteca possuía somente pessoal, específico e `org_public`, sempre no tenant atual | `QuestionLibraryService`, `QuestionPolicy` e quatro abas explícitas, incluindo catálogo global | `QuestionLibraryTest` e revisão independente | Revisor independente | aprovado |
| LIB-01 — quatro escopos de recursos | Autorização existia, mas todos os escopos eram misturados na mesma lista | Abas, contadores e query central baseada em `visibleTo` | Testes de owner, share, organização, plataforma e share obsoleto | Revisor independente | aprovado |
| LIB-04 — pesquisa e filtros | Questões buscavam JSON bruto; recursos filtravam apenas título e tipo | Busca por enunciado/conteúdo/autor, tipo, dificuldade, etapa, série e ordenação | Casos com Unicode, combinações de filtros e parâmetro forjado | Revisor independente | aprovado |
| LIB-04 — paginação | Paginação já existia sem cobertura do catálogo global | Paginação de 12 itens preservada e validada em questões e recursos globais | Catálogos com 13 itens | Revisor independente | aprovado |
| Uso auditável de questão pública | Duplicação era limitada ao tenant | Cópia privada com `source_question_id`, evento idempotente de consumo e versões de recursos preservadas | Teste cross-tenant e `usage_events` | Revisor independente | aprovado |
| Segurança e privacidade | Regras estavam repetidas; shares obsoletos podiam ser interpretados fora do escopo formal | Policies, serviço único, leitura pública somente leitura e contagem de uso limitada ao workspace atual | Regressão de segurança/tenancy | Revisor independente | aprovado |

## Alterações técnicas

### Backend e autorização

- `QuestionLibraryService` centraliza visibilidade, escopos, contadores e aliases legados;
- `QuestionPolicy` cobre consulta, criação, alteração, exclusão, compartilhamento e duplicação;
- `QuestionResourcePolicy` torna recursos públicos globais imutáveis fora do futuro fluxo de moderação;
- shares só concedem acesso quando o registro continua formalmente em `shared_specific`;
- questões e recursos públicos globais do próprio autor também permanecem somente leitura;
- conteúdo privado de outro tenant continua invisível, mesmo com share obsoleto.

### Cópia e compatibilidade

- questão pública global é duplicada como `private` no workspace atual;
- `source_question_id` e o ledger de uso preservam origem e auditoria;
- recursos públicos e suas versões fixadas são reutilizados sem copiar blobs;
- IDs de disciplina, área, BNCC e habilidades customizadas de outro tenant não atravessam a fronteira: metadados portáveis permanecem, relações tenant-owned são removidas;
- seletores de avaliação e atividade continuam aceitando somente questões do próprio tenant; a UI orienta duplicar antes de usar.

### Busca, filtros e interface

- quatro abas responsivas em questões e materiais de apoio;
- busca de questões pelo campo JSON `statement`, inclusive texto Unicode;
- filtros de tipo, dificuldade, etapa, ano/série, disciplina local e ordenação;
- o filtro de disciplina é ocultado e ignorado no catálogo global, pois os IDs são tenant-local e não constituem taxonomia canônica cross-tenant;
- recursos podem ser pesquisados por título, corpo ou autor, filtrados por tipo e ordenados;
- contagem de uso de recurso público mostra somente vínculos do workspace autenticado, sem revelar adoção por outras instituições;
- estados vazios, navegação por teclado e layouts desktop/mobile foram preservados.

### Banco

Migrations aditivas e reversíveis:

- `2026_08_09_000800_index_question_library_queries.php`: índices alinhados a proprietário, instituição, plataforma e destinatários de shares;
- `2026_08_09_000810_index_public_question_resources.php`: índice global `(visibility_scope, status, deleted_at, created_at)` para filtro, contagem e ordenação do catálogo público de recursos.

### API e Mobile

N/A. Nenhum endpoint, DTO, QR, snapshot offline ou contrato Mobile foi alterado. Questões públicas precisam ser duplicadas no Web antes de entrar nos fluxos tenant-scoped já consumidos pelo aplicativo.

## Evidências

- Laravel integral: **430 testes, 1.706 asserções**;
- reteste independente final: **62 testes, 381 asserções**;
- reteste focado após as correções do revisor: **58 testes, 352 asserções**;
- JavaScript Web: **11 testes**;
- Playwright da biblioteca: **2/2** em Chromium desktop e Pixel 7;
- Vite build, Blade cache, Pint, Composer validate e `git diff --check`: aprovados;
- migrations locais `000800` e `000810`: aplicadas de forma aditiva, sem exclusão de dados.

## Revisão independente

O revisor encontrou inicialmente:

1. filtro de disciplina tenant-local sem semântica válida no catálogo global;
2. ausência de índice cujo prefixo atendesse a consulta global de recursos.

O backend passou a ignorar o parâmetro de disciplina no escopo `platform`, a interface removeu esse controle nesse escopo e um teste cobre a tentativa de filtro forjado. O índice global foi adicionado em migration própria, com `down()` simétrico. O reteste independente encerrou com parecer **APROVADO**, sem achados críticos, altos ou médios remanescentes.

## Pendências reais

- `LIB-003`: submissão, moderação, aprovação/rejeição, denúncia, deduplicação, direitos de uso e reputação;
- uma taxonomia pública canônica poderá futuramente habilitar filtros disciplinares globais sem reutilizar IDs internos dos tenants;
- `PLAN-003`: recompensas configuráveis e idempotentes somente após o fluxo público moderado.
