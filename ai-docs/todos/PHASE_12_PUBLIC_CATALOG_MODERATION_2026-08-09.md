# Fase 12 — Moderação do catálogo público

**Tarefa:** `LIB-003`  
**Estado:** concluída  
**Parecer independente:** APROVADO  
**Pendência humana:** política jurídica dos termos, licenças e retirada de conteúdo

## Resultado

Questões e recursos próprios agora podem ser enviados a uma fila global de moderação sem permitir publicação direta. A submissão preserva um **snapshot imutável** do conteúdo, versões dos apoios, declaração de direitos, versão dos termos, atribuição e referência de evidência. O autor pode continuar editando o original privado; uma aprovação publica um clone separado, somente leitura, rastreado até a origem e até a decisão.

A fila administrativa é exclusiva do papel `global_admin`, proíbe autorrevisão e registra transições append-only. Rejeição exige fundamento, reenvio preserva a submissão anterior, conteúdo idêntico não ocupa duas filas ativas e similaridade apenas sinaliza o item ao moderador. Denúncias só alcançam entradas públicas autorizadas; quando procedentes, suspendem novas consultas/cópias sem apagar snapshots históricos.

Reputação é registrada como fatos idempotentes e configuráveis. Esta fase **não gera créditos nem altera cotas**; a conversão econômica pertence a `PLAN-003`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| LIB-05 — submissão | Formulários bloqueavam autopublicação, mas não havia candidatura | Endpoint owner/tenant-scoped, snapshot, direitos, termos, evidência, limite e idempotência | `PublicCatalogModerationTest` | Revisor independente | aprovado |
| LIB-05 — moderação | Não havia fila, decisão ou histórico | Fila global, autorrevisão proibida, estados transacionais, eventos append-only e clone público | Testes de papel, transição terminal e snapshot obsoleto | Revisor independente | aprovado |
| LIB-05 — direitos e autoria | Ausente | Base declarada, justificativa/evidência, atribuição e versão dos termos persistidas | Validação negativa de licença sem explicação | Revisor independente | aprovado |
| LIB-05 — deduplicação | Hash existia apenas dentro de versões do mesmo recurso | Fingerprint canônico, slot ativo único e sinal conservador de similaridade | Duplicata exata bloqueada e similar aceita com alerta | Revisor independente | aprovado |
| LIB-06 — denúncia | Ausente | Denúncia idempotente de entrada pública, fila global, decisão e suspensão | IDOR privado, self-report, retry e suspensão | Revisor independente | aprovado |
| Reputação sem recompensa prematura | Ausente | Ledger factual append-only, pesos e versão configuráveis, sem tocar `usage_events` | Evento de aprovação e ausência de crédito | Revisor independente | aprovado |
| Compatibilidade histórica | Catálogo dependia somente de `platform_public` | Backfill idempotente `legacy_import` e compatibilidade de leitura | Migration executada duas vezes em teste | Revisor independente | aprovado |
| Dependências de questão | Questão pública podia depender de recurso sem história moderada | Submissão exige recurso público publicado e aprovação revalida versão/hash/estado | Suspensão entre submissão e aprovação bloqueia a transação | Revisor independente | aprovado |

## Alterações técnicas

### Banco e domínio

- `public_catalog_submissions`: projeção da candidatura, snapshot, fingerprint, direitos, idempotência e decisão;
- `public_catalog_submission_events`: história append-only das transições;
- `public_catalog_entries`: vínculo da decisão com o clone público e estado publicado/suspenso;
- `public_catalog_reports`: denúncias contextualizadas à entrada pública;
- `public_catalog_reputation_events`: fatos de reputação versionados, sem efeito financeiro;
- `active_fingerprint` único impede duas candidaturas idênticas ativas mesmo sob concorrência;
- migration de backfill cria submissão, entrada e evento `legacy_imported` para itens públicos anteriores, sem alterar ou apagar o conteúdo original.

### Autorização e segurança

- resolução de origem exige `organization_id + owner_id`; IDs estrangeiros retornam 404;
- leitura/denúncia usa os serviços tenant-aware do catálogo e não aceita um ID privado adivinhado;
- moderação depende de role global no middleware e é revalidada no serviço;
- moderador não pode revisar a própria submissão, a própria denúncia ou denúncia contra conteúdo que publicou;
- histórico e retirada de submissões são limitados ao workspace ativo e revalidados sob lock;
- transições usam `DB::transaction`, `lockForUpdate`, constraints únicas e retries de deadlock;
- arquivos comprobatórios são privados, baixados apenas por admin, com `Content-Disposition`, `nosniff` e conferência SHA-256;
- logs unificados guardam IDs, estado, códigos e flags mínimas; motivos e resoluções textuais permanecem apenas no histórico restrito do domínio, sem copiar snapshot ou arquivo.

### Publicação, versões e compatibilidade

- aprovação publica clone `platform_public`; original continua privado/editável;
- `source_question_id` ou `source_resource_id` preserva linhagem;
- versões de recursos e relações da questão são fixadas no snapshot;
- recursos obrigatórios são revalidados imediatamente antes da publicação para fechar TOCTOU;
- suspensão remove a entrada das consultas globais e do workspace do publicador para novos usos, mas não apaga clone, origem, vínculo preexistente, avaliação ou revisão histórica;
- registros públicos legados continuam disponíveis via entry importada; não há quebra das cópias já existentes.

### Web e UX

- páginas responsivas para nova submissão, histórico do autor, denúncia, fila e decisão administrativa;
- ações “Enviar ao catálogo” e “Denunciar” integradas às bibliotecas;
- navegação lateral separa contribuições do professor e moderação global;
- estados de fila, decisão, duplicata provável, retirada e reputação são visíveis;
- E2E cobre desktop e Pixel 7 sem overflow horizontal.

### API e Mobile

N/A. Nenhum endpoint mobile, QR, OMR, DTO ou formato offline foi alterado. O Mobile continua recebendo somente conteúdo já incorporado aos contratos tenant-scoped existentes.

## Pesquisa técnica registrada

As decisões foram confrontadas com fontes primárias atuais:

- [Laravel 12 — Database Transactions](https://laravel.com/docs/12.x/database#database-transactions): rollback automático e retry de deadlock para as decisões atômicas;
- [Laravel 12 — Rate Limiting](https://laravel.com/docs/12.x/rate-limiting): throttle HTTP como camada adicional aos limites persistidos no serviço;
- [Laravel 12 — Validation](https://laravel.com/docs/12.x/validation): validação server-side e regra `accepted` para declarações versionadas;
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): deny-by-default, validação em toda requisição e prevenção de IDOR.

## Evidências atuais

- Laravel integral: **445 testes, 1.790 assertivas**;
- regressão independente ampliada: **81 testes, 469 assertivas**;
- testes novos de moderação: **15 cenários**;
- JavaScript Web: **11 testes**;
- Vite build de produção: aprovado;
- E2E completo: **12/12** em Chromium desktop e Pixel 7;
- migrations locais `000900` e `000910`: aplicadas de forma aditiva; backfill idempotente testado;
- Composer validate, Blade cache, Pint e `git diff --check`: aprovados.

## Revisão independente

**APROVADO.** A primeira passagem encontrou quatro problemas objetivos: falta de independência no julgamento de denúncias, reutilização de conteúdo suspenso pelo publicador, fingerprint de arquivo dependente do caminho físico e ações de autor sem escopo do workspace. Todos foram corrigidos na causa e retestados. O revisor executou 81 testes/469 assertivas, Pint, Blade e `git diff --check`, sem achados críticos, altos ou médios remanescentes.

## Pendências reais

- a redação jurídica dos termos de contribuição, licenças aceitas, política de retirada e tratamento de alegações de direito autoral precisa ser aprovada pelo responsável de produto/jurídico; a implementação apenas armazena versão, declaração e evidência, sem inventar licença;
- `PLAN-003` deverá consumir o fato de aprovação com uma chave como `public-approval:{submission_id}:{rule_version}`, caps e configuração administrativa; nenhum crédito foi concedido nesta fase;
- similaridade semântica futura pode auxiliar moderação, mas não deve rejeitar automaticamente nem ser confundida com violação autoral;
- entregas históricas permanecem preservadas após suspensão. Uma ordem jurídica que exija bloquear também downloads históricos demandará regra específica de produto/jurídico.
