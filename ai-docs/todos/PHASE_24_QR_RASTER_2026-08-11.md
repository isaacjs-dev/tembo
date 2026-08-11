# Fase 24 — QR OMR físico e regressão raster

**Tarefa:** `QR-002` / `QR-04`, `APP-09`  
**Estado:** aprovado tecnicamente, com homologação física humana pendente

## Resultado

O Tembo passa a emitir novos QRs OMR por um perfil físico único e mensurável. Cada
página recebe seu próprio QR de 30 mm, preto sobre branco, correção `M`, quiet zone de
quatro módulos e passo modular mínimo de 0,35 mm. Um payload que não caiba nesse
envelope falha antes da impressão.

O gabarito redundante deixou de ser emitido em novos cartões, pois a fonte oficial já
é o snapshot imutável da cópia. Isso reduz o pior QR suportado de versão 19 para
versão 13. `gab_enc` continua aceito e validado em cartões históricos; não houve nova
versão wire nem quebra de v3/v4/v5.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Quiet zone | Emissor usava 2 módulos | Perfil único usa 4 módulos | perfil e raster | concluído |
| Densidade | Tamanho divergente e gabarito redundante | 30 mm, passo ≥ 0,35 mm e emissão sem `gab_enc` | pior caso de 80 questões é v13 | concluído |
| Multipágina | PDF avançado só mostrava QR na página 1 | QR assinado distinto em todas as páginas e views | teste HTML/PDF multipágina | concluído |
| Contraste | Configuração espalhada | preto puro sobre branco, EC `M` | serviço canônico | concluído |
| Raster | Não havia PDF→imagem→decode | view real Dompdf rasterizada e decodificada por jsQR | 40/40 cenários | concluído |
| Compatibilidade | Cartões históricos não integravam a matriz física | v3, early/full-v4 e v5 entram nas mesmas fixtures | payload exato recuperado | concluído |
| Falha segura | Payload denso podia degradar silenciosamente | área superior a 30 mm lança erro antes da geração | teste negativo | concluído |
| Hardware real | Sem laboratório físico nesta execução | envelope e protocolo documentados | impressão/câmera real | pendência humana |

## Alterações técnicas

- `OmrQrRendererService`: fonte única do SVG e do perfil físico, incluindo hash,
  versão, módulos, quiet zone, tamanho e passo modular.
- `AnswerSheetGeneratorService`: usa o renderer único e mantém metadados físicos por
  página; novos QRs não repetem o gabarito.
- Views `answer-sheet-essential`, `answer-sheet-detailed` e `pdf_advanced`: tamanho
  único e QR em cada página, com espaço de cabeçalho ajustado.
- `OmrQrRasterFixturesCommand`: produz fixtures históricas/atuais, pior caso de 80
  questões e PDF A4 usando a view real de produção.
- `tests/qr-raster.mjs`: rasteriza SVG com rotação, blur, contraste/sombra e o PDF
  Dompdf em 150, 200 e 300 dpi; exige correspondência exata do payload.
- Contrato e documentação OMR: perfil, comando, compatibilidade e limite de captura
  explicitados.
- Banco, migrations, API e formato wire: `N/A`.

## Envelope automatizado

| Condição | Casos | Resultado |
| --- | ---: | --- |
| SVG fonte e captura mínima de 300 px | 10 | 10 aprovados |
| rotação de 7°, blur e baixo contraste/sombra | 15 | 15 aprovados |
| view real Dompdf em 150/200/300 dpi | 15 | 15 aprovados |
| total raster | 40 | 40 aprovados |

O limite de 300 px é a largura mínima automatizada do símbolo no quadro para o pior
payload de 80 questões. Integrar essa métrica à orientação de captura do aplicativo
permanece para `OMR-002`; nesta fase ela é um gate de teste, não uma função da UI.

## Evidências

- Laravel completo: **531 testes / 2.541 assertivas**;
- regressão QR/cartão focada: **29 / 277** após o último ajuste;
- rodada ampliada anterior: **37 / 355**;
- raster SVG + PDF de produção: **40 / 40**;
- Pint, Blade cache e `git diff --check`: aprovados.

## OMR AUDIT REPORT — QR físico

- contrato: v5 permanece atual; v3/v4/v5 históricos continuam legíveis;
- privacidade: novos QRs carregam identidade, geometria e contagem de opções, não PII
  nem gabarito;
- associação: cada página possui payload próprio com página/faixa/template assinados;
- geometria: o QR não altera fiduciais nem o contrato `g` do cartão;
- densidade: pior caso automatizado é 80 questões em uma página, versão QR 13;
- render: SVG vetorial é incorporado na view real e rasterizado em três resoluções;
- erro silencioso: payload acima do envelope físico é rejeitado;
- limitação: simulação e raster não substituem papel, impressora, iluminação e câmera
  reais.

## Revisão independente

O revisor inicialmente reprovou o baseline por quiet zone insuficiente, ausência de
QR nas páginas 2+, densidade sem limite e falta de PDF rasterizado. Na primeira
implementação, exigiu ainda que o pior caso representasse 80 questões na mesma página
e que o PDF viesse de uma view real, não de HTML genérico. Todos os pontos foram
corrigidos e retestados.

Evidência própria do revisor: raster **40/40**, `OmrPrintPipelineTest` **14/96**,
`QrPayloadContractTest` **3/48**, Web **13/13**, Mobile **13/13** e diff-check verde.

**Parecer final:** `APROVADO COM PENDÊNCIA HUMANA`, sem achados altos ou médios
remanescentes.

## Referências técnicas

- DENSO WAVE — quiet zone de quatro módulos:
  https://www.qrcode.com/en/howto/code.html
- DENSO WAVE — tamanho de módulo e resolução de impressão:
  https://www.qrcode.com/en/howto/cell.html
- DENSO WAVE — versões e capacidade:
  https://www.qrcode.com/en/about/version.html
- jsQR — decoder utilizado pela regressão:
  https://github.com/cozmo/jsQR/blob/master/README.md

## Pendência humana obrigatória

Imprimir amostras de v3/v4/v5 e do pior caso v13 em impressoras 300/600 dpi, papéis e
escalas suportados; fotografar com aparelhos Android/iOS representativos, incluindo
câmera inferior, sombra, inclinação e desgaste. Registrar taxa de leitura, distância,
tempo e falhas. Até essa rodada física, o parecer é `APROVADO COM PENDÊNCIA HUMANA`,
não uma garantia comercial de compatibilidade universal.
