# Fase 21 — Geometria canônica do cartão OMR

**Tarefa:** `CARD-001` / `APP-08`, `OMR-01`, `OMR-08`  
**Estado:** aprovado após revisão independente

## Resultado

O PDF, o OMR Web e o aplicativo Mobile agora obedecem ao mesmo contrato versionado de geometria por página. `OmrPageGeometryService` é a única implementação que transforma o layout imutável do template em frame, fiduciais, células, bolhas e projeção compacta assinada no QR (`g`, `rpp`, `qs`, `qe`, `oc`, `tpl_id`, `tpl_v`).

Não foi criada migration. `OmrTemplateVersion.layout_config` e `ExamCopy.template_snapshot` continuam sendo as entradas históricas fixas; a geometria específica da página é derivada de forma determinística e recebe `geometry_hash`.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Fonte única | Cálculo principal e motor ROI morto coexistiam no gerador | Serviço PHP puro; código geométrico morto removido do gerador | Unitário + pipeline PDF | concluído |
| Contrato versionado | `layout_meta` era informal | Contrato v1 tipado, validado e fail-closed | Golden vectors + casos inválidos | concluído |
| Paridade PDF/Web/Mobile | Cada runtime tinha razões/fallbacks próprios | Fixture neutra consumida pelos três runtimes | PHP, Vitest e Node | concluído |
| Páginas 2+ | Web usava número global para coluna/linha | Índice local `question_number - qs` em engine, worker e revisão | Teste 31..48 com template global 1..48 | concluído |
| Opções reais | Review podia depender de ROIs/labels persistidos | Questões da página reconstruídas de `qs..qe/oc` | Template vazio e global testados | concluído |
| Compatibilidade QR | `tpl_v` podia selecionar registry Mobile | QR v5 exige geometria válida; registry somente legado; early-v4 reconhecido sem captura offline insegura | Testes v3/v4/v5 | concluído |
| Histórico | Risco de consultar layout live | Snapshot/version continuam precedendo template atual | ExamCopyVersioning + OmrPrintPipeline | concluído |

## Alterações técnicas

- `OmrPageGeometryService` recebe layout, descritores de questões, início da página e identidade exata do template.
- A saída inclui A4/frame em mm, bolhas absolutas para o Blade, contrato compacto, base de índice e hash determinístico.
- `AnswerSheetGeneratorService` apenas adapta models para descritores e projeta o contrato no QR; os métodos antigos de cálculo/ROI foram removidos.
- Web possui parser/resolvedor tipado, filtra a página assinada e normaliza questões antes do engine, overlay e reprocessamento.
- Mobile valida o mesmo envelope, usa o resolvedor compartilhado no caminho real de pixels e não interpreta `tpl_v` como versão do registry moderno.
- `contracts/omr/card-page-geometry.v1.json` contém casos essential, detailed/página 2 e placement override.
- Banco, migrations e endpoints: `N/A`.
- Payload QR: campos e assinatura v3/v4/v5 preservados; nenhum campo novo foi adicionado.

## OMR AUDIT REPORT

### Arquitetura e contrato

- Fonte estática histórica: `OmrTemplateVersion.layout_config` e snapshot da cópia.
- Fonte derivada por página: `OmrPageGeometryService`.
- Transporte compacto: QR assinado, sem dados pessoais e sem duplicar todas as células.
- PDF: usa posições absolutas produzidas pelo serviço.
- Web/Mobile: validam `g` e convertem as frações do frame em pixels; não recalculam o layout do template moderno.

### Compatibilidade

- QR v5 inválido falha fechado.
- QR v4 completo continua capturável pelo contrato assinado.
- Early-v4 sem faixa/contagem continua parseável para verificação histórica, mas não habilita correção offline sem dados suficientes.
- QR v3 e registry permanecem como adaptador legado explícito.
- Alterar ou arquivar o template atual não muda a entrada versionada de cópias históricas.

### Evidências

- Laravel completo: **517 testes / 2.365 assertivas**;
- Laravel focado independente: **16 / 106**;
- Web/Vitest dedicado: **17 / 17**;
- Mobile grid/contrato: **11 / 11**;
- Mobile TypeScript: aprovado;
- Vite build, Blade cache, Pint e `git diff --check`: aprovados.

Nenhuma métrica de precisão fotográfica foi inferida nesta fase. O dataset físico, câmeras, papéis e impressoras pertencem a `OMR-001`, `QR-002` e à homologação final.

## Revisão independente

A revisão encontrou e devolveu para correção: incompatibilidade com early-v4, `tpl_v` usado como fallback de registry, índice global na página 2, template global não filtrado, review sem reconstrução para templates vazios e corrida de carregamento entre script inline e módulo Vite. Todos os achados receberam correção na causa e reteste.

**Parecer final:** `APROVADO`, sem achados bloqueantes remanescentes.

## Pendências reais

- `CARD-002`: catálogo inicial de dez cartões parametrizados sobre este único motor;
- `QR-001/002`: contrato publicado, rasterização e homologação física;
- `OMR-001..004`: dataset anotado, métricas, câmera e pacote compartilhado mais amplo;
- validar impressão e leitura em papéis, impressoras e aparelhos reais antes de homologar produção.
