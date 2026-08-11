# Fase 22 — Catálogo parametrizado de cartões OMR

**Tarefa:** `CARD-002` / `APP-01`, `APP-09`
**Estado:** aprovado após revisão independente

## Resultado

O Tembo passa a oferecer dez cartões-resposta globais, versionados e funcionalmente distintos, todos projetados pelo mesmo `OmrPageGeometryService`. Os dois modelos históricos (`sistema-padrao` e `sistema-detalhado`) conservaram nome, slug e contrato v1; oito organizações A4 retrato foram acrescentadas sem criar outro motor, tabela ou `AnswerSheetType`.

| Modelo | Organização | Capacidade | Alternativas | Finalidade |
| --- | --- | ---: | ---: | --- |
| Padrão | 2 × 20 | 40 | 5 | uso geral |
| Detalhado | 4 × 15 | 60 | 5 | maior densidade |
| Leitura ampliada | 1 × 20 | 20 | 5 | bolhas grandes |
| Conforto | 2 × 15 | 30 | 5 | espaçamento amplo |
| Equilibrado | 2 × 18 | 36 | 5 | densidade intermediária |
| Três colunas | 3 × 15 | 45 | 5 | avaliações médias |
| Estendido | 2 × 25 | 50 | 5 | leitura linear |
| V/F ampliado | 3 × 20 | 60 | 2 | verdadeiro/falso |
| Alta capacidade 72 | 4 × 18 | 72 | 5 | avaliações extensas |
| Alta capacidade 80 | 4 × 20 | 80 | 5 | limite inicial A4 |

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Dez cartões úteis | Existiam dois modelos | Catálogo declarativo com dez geometrias e propósitos distintos | Seeder e teste de catálogo | concluído |
| Um único motor | `AnswerSheetType` ainda era adaptador histórico | Todos os modelos usam `OmrPageGeometryService`; nenhum motor/tabela novo | Pipeline OMR e inspeção independente | concluído |
| Histórico | Fresh install podia divergir de instalações existentes | Layout integral e hash do padrão congelados; detalhado preservado | Teste de hash e versões | concluído |
| A4 e capacidade | Não havia validação do catálogo completo | Cada modelo foi renderizado no máximo declarado em uma página Dompdf A4 | Dez PDFs reais | concluído |
| Seleção segura | Modelo incompatível podia falhar depois de gravar | Preflight verifica versão exata, capacidade, tipos, alternativas, gabarito e geometria | Casos negativos e rollback | concluído |
| Atomicidade | Vínculo/cópias podiam sobreviver a falha lazy do Dompdf | Vínculo, cópias e materialização dos bytes PDF ocorrem na mesma transação | Falha controlada em `output()` | concluído |
| Isolamento | Lote podia seguir vínculo stale estrangeiro | Resolução `visible(user)`, tenant-aware, ativa e fail-closed | Revisão independente | concluído |
| UX | Lista plana e ação editar em modelo global | Agrupamento Sistema/Meus/Instituição, detalhes, incompatíveis desabilitados e sistema read-only | Blade compilado e inspeção | concluído |

## Alterações técnicas

- `OmrSystemTemplateCatalog` centraliza as dez definições declarativas.
- `OmrTemplateSeeder` é idempotente, cria uma versão imutável por modelo e mantém exatamente um default.
- `AnswerSheetGeneratorService::assertCompatible()` executa preflight sem escrita usando a mesma versão que será snapshotada.
- A exportação simples materializa o Dompdf dentro da transação; qualquer falha reverte vínculo e cópias.
- A impressão em lote resolve templates visíveis e percorre candidatos até encontrar um contrato realmente compatível.
- A tela da Avaliação seleciona o primeiro modelo compatível, explica indisponibilidades e impede envio quando nenhum atende.
- A galeria apresenta propósito/capacidade e não oferece edição para templates do sistema.
- Banco/migrations, API, QR e formato offline: `N/A`; contratos existentes foram preservados.

## OMR AUDIT REPORT

- Fonte de geometria: `OmrPageGeometryService`, sem duplicação por template.
- Entrada histórica: `OmrTemplateVersion` e `ExamCopy.template_snapshot`.
- Contratos padrão/detalhado: preservados; hash do layout padrão congelado em regressão.
- Saída: dez PDFs reais no máximo declarado, uma página A4 retrato cada.
- Segurança: templates selecionados e usados em lote respeitam visibilidade, tenant, atividade e versão exata.
- Integridade: incompatibilidades falham antes de escrever; erro de renderização real desfaz toda a operação.
- QR/OMR Web/Mobile: nenhum campo ou versão foi alterado nesta fase.

## Evidências

- Laravel completo: **524 testes / 2.459 assertivas**;
- Laravel focado independente: **38 / 266**;
- Web/Vitest: **22 / 22**;
- Mobile grid/contrato: **11 / 11**;
- Mobile TypeScript, Vite build, Blade cache, Pint e `git diff --check`: aprovados.

## Revisão independente

O revisor encontrou e devolveu para correção: versão divergente entre preflight e persistência, gravação parcial após falha lazy do Dompdf, resolução de template estrangeiro no lote, fallback automático sem testar compatibilidade, contrato histórico incompleto e ausência de tratamento para zero modelos compatíveis. Todos foram corrigidos e retestados.

**Parecer final:** `APROVADO`, sem achados bloqueantes remanescentes.

## Pendências reais

- homologar impressão, marcação e leitura em papéis, impressoras e aparelhos físicos (`QR-002`/`OMR-001`);
- o QR e o cabeçalho permanecem nas posições efetivamente suportadas pelo renderer atual; variações visuais não foram simuladas;
- métricas de câmera/precisão não foram inferidas de PDFs digitais.
