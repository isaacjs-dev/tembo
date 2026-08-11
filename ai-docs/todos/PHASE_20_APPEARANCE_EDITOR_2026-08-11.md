# Fase 20 — Gerenciador e editor visual de aparência

**Tarefa:** `APP-004` / `APP-02`, `APP-04..06`
**Estado:** aprovado após revisão independente

## Resultado

O Tembo agora possui uma área própria para layouts e cabeçalhos de Avaliação. O professor pode partir dos modelos do sistema, criar uma cópia pessoal, criar um template do zero, editar, reabrir, duplicar, renomear, salvar uma nova versão, definir padrões e arquivar sem destruir o histórico.

O editor avançado do cabeçalho usa Konva somente como projeção visual. A fonte persistida é um schema de domínio v2 normalizado, limitado e versionado. O cabeçalho do cartão-resposta não foi exposto: sua posição segura de QR e geometria pertencem a `CARD-001`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Galeria | Catálogo existia apenas dentro do wizard | Área própria, miniaturas derivadas das definições e ações por permissão | Feature + E2E desktop/mobile | concluído |
| Personalização segura | Sistema era imutável, sem fluxo de cópia | Duplicação cria cópia privada e preserva o original | Teste de origem/cópia | concluído |
| Editor simples/avançado | Ausente | Layout por formulário e cabeçalho por canvas + painel estruturado | Vitest + E2E | concluído |
| Elementos | Apenas sequência simples de texto/campo/linha | Texto, campo, imagem, linha e retângulo; mover, redimensionar, alinhar, distribuir, ordenar, duplicar e apagar | Schema, unitário JS e E2E | concluído |
| Logo/imagem | Metadados sem upload/renderização | PNG/JPEG privado, UUID, limites, inspeção real, SHA-256 e data URI verificada no PDF | Testes de upload, adulteração e renderer | concluído |
| Versionamento | Fundação imutável sem UI | `base_version`, lock pessimista, 409 em edição concorrente e histórico append-only | Teste stale save | concluído |
| Templates arquivados | Ocultos do catálogo | Não recebem mutação; seleção exata continua visível apenas na Avaliação que a usa | Testes ativo antigo/arquivado/forjado | concluído |
| Padrões | Serviço sem UI | Pessoal e institucional segundo ownership e papel | Testes de autorização | concluído |
| Segurança | Sem endpoints do domínio | Tenant/owner fail-closed, sistema somente leitura, asset contextual e `nosniff` | Casos IDOR/cross-tenant | concluído |
| Impressão | Renderer conhecia apenas fluxo legado | Canvas normalizado, assets verificados, texto escapado e ajuste de fonte | Renderer HTML/PDF e regressão canônica | concluído |

## Arquitetura e contratos

- `AppearanceDefinitionSchema` aceita o schema legado e o schema v2 `mode=canvas`, rejeitando propriedades, tokens, tipos, cores e coordenadas fora do envelope.
- O JSON interno do Konva nunca é persistido. A definição armazena coordenadas normalizadas em uma grade 1000 × altura configurada.
- `AppearanceAssetService` é a única porta de entrada e leitura de imagens. Somente PNG/JPEG válidos, privados e limitados são aceitos.
- `AppearanceTemplateService` centraliza criação, duplicação, versionamento, defaults, arquivamento, visibilidade e seleção histórica exata.
- `CanonicalPrintDocumentService` resolve tokens e bytes a partir do snapshot; nenhuma URL remota ou path informado pelo cliente é renderizado.
- Versões e arquivos referenciados historicamente não são apagados.

## Pesquisa técnica aplicada

- A documentação oficial do Konva recomenda persistir o estado da aplicação, e não `stage.toJSON`; o editor segue essa orientação: <https://konvajs.org/docs/data_and_serialization/Best_Practices.html>.
- O `Transformer` altera escala, por isso o editor normaliza largura/altura no fim da transformação e aplica limites: <https://konvajs.org/docs/select_and_transform/Resize_Limits.html>.
- As recomendações OWASP de upload foram aplicadas com allowlist, inspeção de conteúdo, nome aleatório, limite de tamanho e armazenamento privado: <https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html>.

## Evidências

- Laravel focado após correções: **53 testes / 436 assertivas**;
- Laravel completo: **513 testes / 2.330 assertivas**, aprovado;
- JavaScript/Vitest: **13 / 13**;
- Playwright dedicado: **2 / 2**, Chromium desktop e Pixel 7;
- Vite build, Blade cache, Pint e `git diff --check`: aprovados;
- Mobile TypeScript: aprovado;
- Mobile grid/QR/snapshot: **8 / 8**.

## Revisão independente

A primeira revisão reprovou o lote por permitir PUT após arquivamento, perder versões antigas ativas no wizard, bloquear o autosave de seleção arquivada, oferecer ação de edição indevida, aceitar contrato de disco contraditório, usar previews genéricos e conter textos com codificação corrompida. Todos os achados foram corrigidos na causa e receberam testes de regressão.

Após as correções, o revisor repetiu os casos de arquivo, versão histórica, colisão de IDs e enumeração cross-tenant. O reteste final dedicado terminou com **8 testes / 49 assertivas PHP** e **2 / 2 Vitest**.

**Parecer final:** `APROVADO`, sem achados bloqueantes remanescentes.

## Pendências reais

- homologar impressão física, logos e títulos extensos em impressoras representativas;
- validar toque/arraste em aparelhos móveis reais; o E2E cobre viewport Pixel 7, não hardware;
- editor do cabeçalho do cartão, posição segura de QR e catálogo de cartões permanecem em `CARD-001/002`;
- paginação da galeria poderá ser adicionada quando templates personalizados atingirem volume que justifique paginação server-side.
