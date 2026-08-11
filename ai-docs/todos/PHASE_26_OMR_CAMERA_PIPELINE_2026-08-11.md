# Fase 26 — pipeline OMR de câmera, geometria e confiança

**Tarefa:** `OMR-002` / `OMR-02..04`, `OMR-10`, `OMR-11`
**Estado técnico:** implementação concluída; homologação física permanece pendente

## Resultado

O pipeline Mobile deixou de inferir respostas a partir de bytes RGBA incorretos,
homografia instável, foto sem quatro fiduciais ou tamanho do JPEG. A captura agora
revalida o QR na foto final, normaliza 0/90/180/270 graus, mede qualidade e geometria,
retifica a perspectiva e somente então classifica as ROIs. Falhas estruturais exigem
nova captura; marca fraca, apagada, dupla ou ambígua exige revisão humana.

Web e Mobile publicam evidência normalizada com ação, motivos, qualidade, orientação,
escala, homografia e ROI por questão. O backend valida essas invariantes antes de
permitir correção automática e preserva a evidência no upload, consolidação e sync.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| OMR-02 | RGBA indexado como grayscale; homografia inválida; leitura sem 4 fiduciais | conversão explícita, solver 8×8 com pivotamento, quad/escala/orientação/reprojeção e hard gates | 11 testes Mobile + smoke integral | concluído no estrato automatizado |
| OMR-03 | confiança inflada e ambiguidades autoaceitas | zeros entram na média; fraca/apagada/dupla/ambígua sempre `review`; structural sempre `rescan` | gates sintéticos 4/4 em tuning e holdout | concluído no baseline sintético |
| OMR-04 | revisão sem ROI/motivo e confirmação cega | overlay da região, motivo por questão, resolução explícita e caminho híbrido auditável | teste API/job e typecheck | concluído |
| OMR-10 | contratos Web/Mobile divergentes | evidência `action/reasons/imageQuality/geometry/questions/roi` nos dois adapters | Vitest e build | concluído aditivamente |
| OMR-11 | autocaptura com métricas simuladas e crop obrigatório | simulação removida; captura manual neutra; processamento automático; ajuste apenas fallback | inspeção estática e teste de produção | concluído |
| Hardware | sem dispositivos reais nesta execução | protocolo preservado e gates não mensurados continuam pendentes | `test:omr-dataset:gates` sai 3 | pendência humana |

## Alterações técnicas

### Mobile

- `omr-pixel-pipeline.ts`: função pura de pixels com RGBA→grayscale, fiduciais,
  homografia, warp, ROIs, confiança, evidência e decisões fail-closed.
- `homography.ts`: sistema direto 8×8 com pivotamento, inversão e erro de reprojeção.
- `image-quality.ts`: brilho, contraste e variância Laplaciana reais.
- `qr-orientation.ts`: QR assimétrico no canto superior direito resolve os quatro
  giros; a foto final é reescaneada antes do processamento.
- câmera: removidos métricas perfeitas e autocaptura simulada; foto normal segue
  diretamente para leitura/revisão, sem crop obrigatório.
- revisão: ROI e motivo por questão; itens incertos precisam de decisão explícita.
  Questões aceitas pela máquina preservam a proveniência; somente itens tocados são
  marcados `manual_confirmation` no caminho `hybrid`.
- sincronização: evidência sobrevive a reinício e também acompanha scans QR v3; o QR
  legado pode ser descartado sem apagar a evidência OMR.

### Web

- qualidade de imagem real antes do OpenCV;
- um fiducial por quadrante, área mínima da quad, orientação, escala e reprojeção;
- QR da imagem determina e normaliza orientação antes das ROIs;
- `DOUBLE` e `UNCERTAIN` sempre exigem revisão;
- contrato normalizado por questão, incluindo fill ratios e ROI;
- timeouts explícitos de inicialização/processamento do worker.

### Backend e banco

- migration aditiva `processing_evidence` em `omr_scan_pages`;
- API valida schema e invariantes semânticas: autoaceite de máquina exige qualidade,
  quatro fiduciais, reprojeção, orientação, escala e todas as questões aceitas;
- confirmação manual e híbrida possuem regras distintas e auditáveis;
- `rescan` é rejeitado antes de persistir;
- `ConsolidateAnswersJob` repete a validação como defesa em profundidade e mantém
  score nulo quando houver revisão pendente;
- payloads Mobile antigos sem o campo permanecem compatíveis.

## Métricas antes/depois — baseline sintético

| Adapter/split | Exatidão antes → depois | Erro confiante antes → depois | Ambiguidade→revisão antes → depois |
| --- | ---: | ---: | ---: |
| Mobile tuning | 50% → 100% | 83,3% → 0% | 0% → 100% |
| Mobile holdout | 33,3% → 100% | 83,3% → 0% | 0% → 100% |
| Web tuning | 100% → 100% | 0% → 0% | 25% → 100% |
| Web holdout | 100% → 100% | 0% → 0% | 33,3% → 100% |

O autoaceite sintético teve precisão 100% e cobertura conservadora de 16,7% nos
dois adapters/splits. Os denominadores continuam pequenos; estes números são testes
de regressão determinísticos, não estimativa de precisão em campo.

## Evidências automatizadas

- Mobile: typecheck; pipeline **12/12**; contratos/grid **13/13**.
- Web: testes OMR focados **14/14**; suíte JS completa **40/40** e Vite build.
- Laravel: segurança/API, QR/impressão e versionamento de cópias,
  **44 testes / 276 asserções**.
- dataset tuning: saída `0`; holdout congelado: saída `0`.
- gate físico: saída esperada `3`, porque QR físico/associação e hardware não foram
  homologados.
- caminho híbrido: duas questões, uma preservada da máquina e uma confirmada
  manualmente, atravessam API e consolidação e produzem um único score correto.

## Revisão independente

O primeiro parecer reprovou a fase por aceitar evidência contraditória, perder a
evidência em sync v3, cobrir apenas um smoke ideal e manter o contrato Web antigo.
As correções adicionaram invariantes no controller/job, caminho híbrido, benchmark
adverso, schema normalizado e geometria Web.

Na segunda rodada, o revisor encontrou que a orientação dependia apenas do QR visto
antes da foto e que o overlay usava coordenadas da imagem rotacionada. A foto final
passou a ser reescaneada e os pontos são remapeados ao frame capturado. O reteste
independente confirmou Mobile 12/12, grid 13/13, Web 14/14, tuning/holdout `0`, gate
físico `3` esperado e diff limpo.

**Parecer final:** `APROVADO`, sem achados altos ou médios. A homologação física é
uma pendência humana real e não bloqueia a infraestrutura fail-closed entregue.

## OMR AUDIT REPORT

- identidade/tenant/QR continuam validados pelo backend; evidência do cliente nunca
  concede autorização;
- nenhuma resposta é produzida sem decode, orientação, quatro fiduciais, quad,
  homografia, qualidade e ROI válidos;
- não existe mais fallback por tamanho do JPEG;
- a foto final precisa conter o mesmo QR lido na câmera; mudança entre leitura e
  captura falha fechada;
- Web e Mobile ainda usam implementações distintas; a remoção de motores mortos e o
  pacote algorítmico comum pertencem ao `OMR-004`;
- multipágina e associação/consolidação completas pertencem ao `OMR-003`;
- o dataset físico autorizado continua inexistente. Não há alegação de homologação
  comercial nem de precisão absoluta.

## Pendências reais

1. Executar Expo em Android/iOS reais, inclusive aparelho de entrada.
2. Coletar ao menos 200 capturas autorizadas, três classes de celular e três
   impressoras, com tuning e holdout independentes.
3. Medir sombras, reflexo, blur por movimento, folha curva/amassada, escala de
   impressão e troca de orientação durante a captura.
4. Manter `AUDIT-OMR` bloqueado até os denominadores físicos, IC95% e erro silencioso
   de associação zero serem demonstrados.

## Referências técnicas

- OpenCV — transformações geométricas:
  https://docs.opencv.org/4.x/da/d54/group__imgproc__transform.html
- OpenCV — thresholding:
  https://docs.opencv.org/4.x/d7/d4d/tutorial_py_thresholding.html
- OpenCV — operador Laplaciano:
  https://docs.opencv.org/4.x/d5/db5/tutorial_laplace_operator.html
