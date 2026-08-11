# Fase 25 — dataset OMR e baseline reproduzível

**Tarefa:** `OMR-001` / `OMR-01..06`, §9  
**Estado técnico:** infraestrutura e baseline sintético concluídos; homologação física pendente

## Resultado

O Tembo passa a possuir um contrato versionado para dataset OMR, ground truth,
proveniência, direitos, retenção, tuning/holdout, resultados normalizados e métricas.
O runner executa componentes reais dos classificadores Web e Mobile, valida hashes e
schemas, impede vazamento entre splits e compara a saída com um baseline congelado.
Tuning é o modo padrão; o holdout exige comando explícito e hash do perfil congelado.

O baseline não foi ajustado para parecer bom. Ele demonstrou baixa qualidade no
estado atual e todos os gates de produto reprovaram. Isso estabelece a medida “antes”
para o `OMR-002`, sem chamar simulação de homologação física.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Dataset versionado | imagens soltas, sem contrato | schema, tuning, holdout e hashes v1 | validação AJV e invariantes | concluído |
| Ground truth | inexistente | estados simples/fraco/apagado/duplo/ambíguo/branco, ação esperada e anotação | 12 questões sintéticas | concluído no estrato sintético |
| Proveniência e privacidade | 10 JPGs sem origem e scans de runtime | inventário excluído, direitos, consentimento, PII e retenção fail-closed | 10 hashes conferidos | concluído |
| Tuning/holdout | inexistente | grupos separados e limiares congelados antes do holdout | teste anti-leakage | concluído |
| Web | mocks sem pixels reais | `OmrEngine.readBubbles`/`assessQuality` com OpenCV real | adapter classificador | concluído no componente |
| Mobile | apenas contratos geométricos | `classifyBubble`/`selectAnswer` de produção | adapter classificador | concluído no componente |
| Pipeline completo | não mensurado | cobertura ausente é publicada no relatório, sem simulação enganosa | `pipeline_coverage` | pendente `OMR-002` |
| Métricas | inexistentes | precisão, cobertura, erro confiante, encaminhamento, paridade e IC95 | relatório JSON determinístico | concluído |
| Hardware | indisponível | protocolo e envelope mínimo registrados | 200 capturas/3 classes/3 impressoras | pendência humana |

## Contratos e execução

- `dataset-manifest.schema.json`: amostra, contrato QR/template, captura, proveniência,
  direitos, retenção e anotação/adjudicação.
- `dataset-tuning.v1.json` e `dataset-holdout.v1.json`: grupos distintos, com assets
  sintéticos determinísticos e hashes fixos.
- `dataset-thresholds.v1.json`: perfil congelado antes do holdout.
- `engine-result.schema.json`: saída normalizada e cobertura explícita do adapter.
- `dataset-baseline.expected.json`: impede mudança silenciosa de métricas.
- `excluded-assets.v1.json`: dez imagens históricas fora das métricas; scans de
  runtime não podem ser copiados ao repositório.
- `platform/tests/omr-dataset.mjs`: valida, gera pixels, executa classificadores,
  calcula métricas/intervalos e compara o baseline.

Comando normal, que valida o tuning e o baseline conhecido:

```text
cd platform
npm run test:omr-dataset
```

Abertura explícita do holdout, condicionada ao hash congelado:

```text
npm run test:omr-dataset:holdout
```

Comando de gate, que atualmente encerra com código 3 porque a qualidade reprova:

```text
npm run test:omr-dataset:gates
```

## Baseline antes do OMR-002

| Adapter | Split | Exatidão determinável | Decisão correta | Precisão autoaceite | Cobertura | Erro confiante | Ambiguidade → revisão |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Mobile classificador | tuning | 50,0% (n=2) | 16,7% | 16,7% | 100% | 83,3% | 0% |
| Mobile classificador | holdout | 33,3% (n=3) | 16,7% | 16,7% | 100% | 83,3% | 0% |
| Web classificador | tuning | 100% (n=2) | 16,7% | 0% (n=0) | 0% | N/A (n=0) | 25,0% |
| Web classificador | holdout | 100% (n=3) | 16,7% | 0% (n=0) | 0% | N/A (n=0) | 33,3% |

Paridade exata Web/Mobile: **0/12 (0%)**. Os denominadores são pequenos e os
intervalos de Wilson de 95% são publicados pelo runner; estes números são regressão
sintética, não estimativas de campo.

## OMR AUDIT REPORT — baseline

- associação cartão/cópia/aluno: não exercitada neste estrato; não existe alegação
  de erro zero;
- QR e geometria: contrato está no manifest, mas leitura física pertence ao QR-002 e
  pipeline completo ao OMR-002;
- Web: classificador real e OpenCV real, sem detecção de fiduciais/perspectiva;
- Mobile: classificador puro real, sem captura Expo/decode/homografia;
- política: ambos recebem thresholds do mesmo perfil e a decisão comum usa
  `auto_accept=0,8`; nenhum `n=0` satisfaz gate por vacuidade;
- falha conhecida: o processador Mobile completo trata buffer RGBA como um byte por
  pixel em parte do caminho; correção e medição pertencem ao OMR-002;
- fallback conhecido: heurística por tamanho de JPEG pode produzir confiança alta
  sem leitura geométrica; deve ser removida ou forçar revisão no OMR-002;
- calibrador legado: não é considerado avaliador nem fonte de ground truth;
- dados: nenhuma imagem física ou dado pessoal foi adicionado ao Git;
- resultado: todos os gates de qualidade reprovaram, sem mascaramento.
- QR em primeira/segunda tentativa e erro de associação são publicados como
  `pending`, nunca omitidos ou tratados como aprovados.

## Segurança, banco e compatibilidade

- Banco/migrations/API/QR wire/PDF: `N/A`; nenhuma alteração.
- Assets desconhecidos são somente inventariados por hash e excluídos.
- Amostra física com PII não anonimizada, consentimento ausente ou sem dupla anotação
  falha antes da avaliação.
- A dependência transitiva vulnerável `nanoid 3.3.16` foi atualizada no lockfile para
  `3.3.18`; `npm audit` passou sem vulnerabilidades.

## Evidências automatizadas

- dataset OMR: **2 amostras / 12 questões / 2 classificadores**, baseline estável;
- JavaScript Web: **35/35**;
- Mobile: **13/13** e TypeScript aprovado;
- JSON parse, schemas, hashes, anti-leakage e `git diff --check`: aprovados;
- `npm audit` completo e produção: **0 vulnerabilidades**.

## Revisão independente

O primeiro parecer foi `REPROVADO`: o comando de gate não era portátil no npm do
Windows; holdout e tuning rodavam juntos; limiares Web/autoaceite não vinham
integralmente do perfil; gates de QR/associação não apareciam; amostras físicas
poderiam ser sintetizadas; e as invariantes semânticas eram insuficientes.

Após as correções, o revisor ainda encontrou duas regressões: `rpp` foi inicialmente
interpretado como questões por página, embora represente linhas por coluna, e a
exatidão por questão duplicava o encaminhamento à revisão. Ambas foram corrigidas na
causa e retestadas.

Evidência independente final:

- `test:omr-dataset`: saída `0`;
- `test:omr-dataset:holdout`: saída `0`;
- `test:omr-dataset:gates`: saída esperada `3`;
- holdout sem confirmação: saída esperada `1`;
- `git diff --check`: aprovado.

**Parecer final:** `APROVADO COM PENDÊNCIA HUMANA`, sem achados técnicos altos ou
médios remanescentes. A pendência é o pipeline físico e o holdout autorizado, não uma
falha ocultada no baseline.

## Pendências reais

1. `OMR-002`: corrigir RGBA, eliminar autoaceite por fallback frágil, medir câmera,
   fiduciais, perspectiva, luz, blur e ROIs no pipeline real.
2. Coletar, com autorização, tuning e holdout físicos: mínimo operacional de 200
   capturas, três classes de aparelho e três impressoras.
3. Cobrir templates atuais/históricos, 2–6 alternativas, 5–60 e borda de 80 questões,
   multipágina, escala 95/100/fit, sombras, blur, rasura, marca dupla e fotocópia.
4. Não aprovar comercialmente até os gates físicos e a associação silenciosa zero
   serem demonstrados com denominadores e intervalos de confiança.

## Referências técnicas

- OpenCV — threshold simples/adaptativo:
  https://docs.opencv.org/4.x/d7/d4d/tutorial_py_thresholding.html
- OpenCV — suavização e filtros:
  https://docs.opencv.org/4.x/dc/dd3/tutorial_gausian_median_blur_bilateral_filter.html
- OpenCV — Laplacian/qualidade de bordas:
  https://docs.opencv.org/4.x/d5/db5/tutorial_laplace_operator.html

O mínimo de 200 capturas é um gate operacional do PRD, não prova estatística isolada
de erro ≤0,1%. Com zero eventos observados, a aproximação do limite superior de 95%
é cerca de `3/n`; o relatório final deve sempre mostrar `n` e intervalo de confiança.
