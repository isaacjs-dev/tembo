# Contrato do dataset OMR

Este diretório contém o contrato versionado usado para medir o OMR do Tembo. O
baseline v1 é deliberadamente pequeno e sintético: ele valida infraestrutura,
ground truth, separação entre tuning/holdout, execução dos classificadores reais e
detecção de regressões. Ele **não** homologa câmera, impressão, QR, perspectiva,
associação de aluno ou o pipeline completo.

## Arquivos

- `dataset-manifest.schema.json`: schema de amostras, proveniência, direitos,
  retenção, contrato impresso, captura e ground truth.
- `dataset-tuning.v1.json`: amostras usadas para desenvolvimento de limiares.
- `dataset-holdout.v1.json`: grupo separado e selado, nunca usado para ajuste.
- `dataset-thresholds.v1.json`: limiares congelados antes da abertura do holdout.
- `engine-result.schema.json`: saída normalizada dos adapters.
- `dataset-baseline.expected.json`: métricas sintéticas esperadas. Mudá-lo exige
  revisão explícita; o runner falha quando as métricas mudam silenciosamente.
- `excluded-assets.v1.json`: inventário de imagens que não entram em nenhuma
  métrica por falta de proveniência, direitos, consentimento ou ground truth.

## Execução

Na pasta `platform/`:

```text
npm run test:omr-dataset
```

O comando padrão executa somente `tuning`. Ele valida schemas e invariantes
semânticas, confere hashes, impede vazamento de grupos, executa o classificador Web
com OpenCV real e o classificador Mobile real, compara com o ground truth e publica
métricas com intervalo de Wilson de 95%.

O holdout exige abertura explícita e confirma que o hash do perfil continua igual ao
baseline congelado:

```text
npm run test:omr-dataset:holdout
```

Para exigir que todos os gates de qualidade estejam atendidos:

```text
npm run test:omr-dataset:gates
```

O baseline v1 deve falhar nesse modo. Essa falha é evidência do estado atual, não
falha da infraestrutura de avaliação.

O runner v1 recusa uma amostra `physical` em vez de gerar pixels sintéticos em seu
lugar. O adapter físico de imagem/câmera pertence ao `OMR-002`; até ele existir,
nenhuma métrica sintética pode ser publicada como se viesse de fotografia.

## Regras para dados físicos

- Uma folha, burst ou sessão física inteira pertence a um único `group_id`; grupos
  nunca atravessam tuning/holdout.
- Toda amostra física precisa de consentimento documentado, anonimização, política
  de retenção, referência de direitos, hash, metadados do aparelho/impressora e
  dupla anotação com adjudicação.
- Imagens de runtime em `platform/storage/` não podem ser copiadas para fixtures.
- O holdout físico é congelado antes de sua execução; limiares só podem ser
  ajustados no conjunto de tuning.
- Sintético, raster, simulação e teste físico são reportados separadamente.

## Envelope de homologação pendente

O mínimo operacional definido no PRD é 200 capturas, três classes de celular e três
impressoras. Esse número inicia a validação física, mas não prova sozinho erro menor
que 0,1% no nível de cartões. Todo relatório deve mostrar denominador e intervalo de
confiança; com zero erros, a aproximação superior de 95% é cerca de `3/n`.

Até a coleta autorizada e a execução do pipeline completo, o estado correto é
`APROVADO COM PENDÊNCIA HUMANA`, nunca “OMR homologado”.
