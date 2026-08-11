# Fase 17 — Versionamento de aparência e cartão OMR

**Tarefa:** `APP-001` / `APP-01..03`, `APP-08`
**Estado:** aprovado

## Resultado

O Tembo agora distingue formalmente quatro contextos que antes estavam misturados: layout da Avaliação, cabeçalho da Avaliação, cabeçalho do cartão-resposta e geometria OMR do cartão. Os três primeiros usam templates de aparência com versões imutáveis; o cartão continua usando o agregado `OmrTemplate`, preservando todos os IDs, versões, QR Codes e consumidores existentes.

Cada cópia impressa recebe um snapshot de aparência schema v2, com versões exatas, hashes, definições, assets, preferências resolvidas e aliases compatíveis com o gerador atual. Alterar um template, logo, padrão ou ROI depois da impressão não modifica a cópia histórica.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Contextos visuais | Layout e cabeçalhos hardcoded ou misturados ao cartão | Agregados distintos para `assessment_layout`, `assessment_header`, `answer_sheet_header` e cartão OMR | Testes dos três tipos + OMR | concluído |
| Imutabilidade | Versões OMR guardavam apenas parte da configuração e podiam ser alteradas | Versões visuais e OMR bloqueiam update/delete; criação usa lock e transação | Testes de mutação direta e concorrência estrutural | concluído |
| Defaults | Flags dispersas e não únicas | Precedência Avaliação explícita → usuário → instituição → sistema | Testes de fallback e troca de versão | concluído |
| Arquivamento | Custom podia ser hard-deleted quando ainda não usado | Custom é arquivado; referências e cópias históricas permanecem | Teste de cartão com cópia histórica | concluído |
| Snapshot | Cabeçalho/logo atuais podiam contaminar versão antiga | Snapshot v2 fixa definição, assets, hash, preferências e cartão | Testes de v1 após edição/default/archive | concluído |
| Geometria/ROIs | `generateRois()` substituía regiões vivas | Cada regeneração cria nova versão completa com ROIs, thresholds, calibração e QR | Testes v1/v2 e versão inexistente | concluído |
| Segurança | Serviço OMR não garantia tenant/owner; ref privada intra-tenant era possível | Serviços validam workspace, membership, owner/gestor, visibilidade e estado | Testes cross-tenant e professor A → B | concluído |
| Compatibilidade | QR/API/Mobile dependem de `card_template_id/version` e layout legado | IDs e contratos mantidos; snapshot v2 conserva aliases top-level | Pipeline PDF/QR, API e Mobile verdes | concluído |

## Alterações técnicas

- `appearance_templates`: identidade, contexto, owner, tenant, visibilidade e arquivamento;
- `appearance_template_versions`: definição/assets/hash imutáveis e versionados;
- `template_defaults`: padrão único por escopo e contexto;
- `exams`: referências opcionais às três versões visuais exatas;
- `omr_template_versions`: schema v2 com definição completa, hash, autor, geometria, thresholds, calibração, região QR e ROIs;
- `AppearanceTemplateService`: duplicação, nova versão, default, arquivamento, precedência e snapshot;
- `OmrTemplateVersionService`: autorização e versionamento atômico do cartão;
- `ExamPrintService`: persistência do snapshot agregado schema v2;
- `OmrTemplateController`: edição/ROIs transacionais e arquivamento sem hard delete;
- `ExamController`: leitura correta de header/logo históricos e override de impressão identificado por `effective_content_hash`;
- seeder OMR idempotente, sem reescrever versões do sistema.

O renderizador HTML/CSS e os previews continuam em `APP-002`. Os catálogos 10×10 e o editor visual continuam em `APP-003` e `APP-004`; esta fase não os declarou concluídos.

## Banco e compatibilidade

A migration `2026_08_11_000400_create_versioned_appearance_templates` é aditiva, foi aplicada no banco local e exercitada do zero por `RefreshDatabase`. O rollback recusa remover templates personalizados ou referências históricas.

Versões OMR anteriores ao schema v2 nunca armazenaram seus ROIs por versão. O backfill preserva integralmente a versão corrente disponível e marca versões anteriores com `questions = null` e `legacy_questions_source = not_versioned_before_schema_v2`. Não há reconstrução artificial. Templates legados sem registro de versão só aceitam fallback para sua versão corrente e o snapshot fica marcado como `live_legacy_current`; qualquer número divergente falha explicitamente.

QR v3/v4/v5, `card_template_id`, `card_template_version`, `AnswerSheetType`, API e formato offline não foram removidos nem renomeados.

## Evidências

- suíte Laravel completa final: **486 testes / 2.062 assertivas**, aprovada;
- regressão focada APP-001/OMR/PDF/workspace: **39 / 243**, aprovada;
- JavaScript/Vitest: **11 / 11**, aprovado;
- Vite build: aprovado;
- Mobile TypeScript: aprovado;
- Mobile grid/QR/snapshot: **8 / 8**, aprovado;
- Blade `view:cache`: aprovado;
- Pint dos arquivos afetados: aprovado;
- `git diff --check`: aprovado;
- migration aplicada localmente; verificação: 3 templates visuais, 3 versões, 3 defaults e nenhuma versão OMR sem hash.

## Revisão independente

O primeiro parecer reprovou a mudança por cinco problemas concretos: serviço OMR sem autorização interna, fallback silencioso de versão inexistente, template OMR de sistema mutável no model, referência privada cruzando ownership no mesmo tenant e override avançado divergente do hash original.

Todos foram corrigidos na causa e receberam regressão dedicada. O reteste independente confirmou workspace/membership/owner, bloqueio de versão ausente, imutabilidade dos templates de sistema, isolamento privado intra-tenant e `print_overrides` com `effective_content_hash` próprio.

**Parecer final:** `APROVADO`, com **39 testes / 243 assertivas**, Pint e diff-check verdes, sem achados bloqueantes remanescentes.

## Pendências reais

- versões OMR pré-v2 sem ROIs históricos devem ser tratadas como legado conhecido e não como reprodução integral;
- validar concorrência de locks no SGBD de produção; a suíte automatizada usa SQLite;
- homologação física de A4, logos, QR e impressoras pertence a `APP-002`, `CARD-001/002` e `QR-002`;
- templates visuais mínimos desta fase existem para compatibilidade estrutural; os 10 layouts e 10 cabeçalhos profissionais serão entregues somente após o renderizador canônico.
