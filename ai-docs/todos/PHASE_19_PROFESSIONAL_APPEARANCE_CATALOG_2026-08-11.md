# Fase 19 — Catálogo profissional de aparência

**Tarefa:** `APP-003` / `APP-01..03`, `APP-09`  
**Estado:** aprovado

## Resultado

O Tembo agora oferece 10 layouts de Avaliação e 10 cabeçalhos de Avaliação prontos. As opções possuem nomes, slugs, hashes e definições distintos; variam funcionalmente margens, densidade, colunas, separadores, altura, ordem e conjunto de campos.

O professor escolhe as duas versões na etapa Aparência. A seleção é salva pelo mesmo controle de revisão do wizard, atualiza o preview canônico e é auditada. Cópias já geradas mantêm integralmente o snapshot anterior.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| 10 layouts | Um layout essencial | Nove novos perfis A4 somados ao baseline | Contagem, unicidade, schema e PDF de cada layout | concluído |
| 10 cabeçalhos | Um cabeçalho essencial | Nove cabeçalhos com finalidades e campos distintos | HTML seguro e assinatura distinta de cada cabeçalho | concluído |
| Catálogo profissional | Nenhuma seleção na interface | Galerias com disclosure progressivo, características e radios nativos | Feature + Playwright desktop/mobile | concluído |
| Persistência | Colunas existiam, mas não eram configuráveis no wizard | Seleção exata de version IDs pelo autosave e update tradicional | Testes de persistência e auditoria | concluído |
| Isolamento | Seleção forjada ainda não tinha fluxo | Serviço valida ator, tenant, visibilidade, existência e kind | ID estrangeiro 404; kind incompatível 422 | concluído |
| Histórico | Base de snapshot existia em APP-001/002 | Trocar catálogo afeta somente preview e cópias futuras | Hash e aparência da cópia antiga permanecem iguais | concluído |
| Compatibilidade OMR | Landscape conflitaria com lote combinado portrait | Catálogo inicial integralmente A4 portrait | PDF exclusivo e combined testados em cada layout | concluído |
| Migration segura | Catálogo inexistente | Migration aditiva falha em colisão e recusa rollback com referências/defaults | Teste de up/down protegido | concluído |

## Alterações técnicas

- migration `2026_08_11_000500_seed_professional_appearance_catalog` cria 9+9 templates sistêmicos versionados e imutáveis;
- o default essencial dos três contextos não foi alterado;
- `AppearanceTemplateService` lista o catálogo visível e aplica versões com validação fail-closed;
- `ExamWizardService` aceita os version IDs aditivamente e preserva clientes antigos que não os enviam;
- `ExamController` fornece catálogo e seleção efetiva ao editor e persiste o formulário tradicional;
- a etapa Aparência usa agrupamento progressivo, labels clicáveis, foco visível e atualiza o iframe após o autosave;
- testes antigos que pressupunham apenas um template sistêmico foram atualizados para validar o catálogo integral.

Cabeçalho do cartão, catálogo de cartões, canvas, upload de logo e galeria de assets não foram incluídos: permanecem em `CARD-001/002` e `APP-004`.

## Banco e compatibilidade

A migration é somente de dados e usa as tabelas criadas em APP-001. Slugs preexistentes fazem a aplicação falhar sem sobrescrever registros. O rollback é recusado se alguma Avaliação ou default referenciar o catálogo.

Todos os layouts iniciais são A4 portrait. Isso preserva a regra única `@page A4 portrait/10mm` do lote combinado e a geometria OMR. Orientação mista exige composição posterior de PDFs e não foi simulada nesta fase.

## Evidências

- suíte Laravel completa: **505 testes / 2.282 assertivas**, aprovada;
- regressão independente focada: **45 / 388**, aprovada;
- catálogo dedicado: **7 / 147**, aprovado;
- Playwright wizard: **2 / 2**, desktop e Pixel 7;
- JavaScript/Vitest: **11 / 11**, aprovado;
- Vite build: aprovado;
- Mobile TypeScript: aprovado;
- Mobile grid/QR/snapshot: **8 / 8**, aprovado;
- Blade `view:cache`, Pint e `git diff --check`: aprovados;
- migration aplicada localmente e exercitada do zero por `RefreshDatabase`.

## Revisão independente

A auditoria impediu layouts landscape incompatíveis com o lote OMR, endureceu colisão/rollback da migration, corrigiu testes que ocultariam itens por `keyBy`, exigiu tokens opcionais como campos rotulados, preservou atores explícitos e apontou um nome que prometia espaço discursivo não implementado. O perfil foi renomeado para **Seções livres**.

**Parecer final:** `APROVADO`, com **45 testes / 388 assertivas**, sem achados bloqueantes remanescentes.

## Pendências reais

- homologar fisicamente os 10 layouts em papéis e impressoras representativos;
- logos, canvas, galeria, duplicação e gestão visual avançada pertencem a `APP-004`;
- cabeçalhos de cartão e 10 organizações de cartão pertencem a `CARD-001/002`;
- orientação landscape deverá aguardar saídas PDF separadas/compostas sem afetar OMR.
