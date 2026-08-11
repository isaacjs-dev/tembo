# OMR — templates, impressão, QR e leitura

Este documento descreve o estado implementado do cartão-resposta Tembo. O contrato
normativo do QR está em `../../contracts/omr/QR_CONTRACT.md`, acompanhado de JSON
Schema e vetores compartilhados por PHP, Web e Mobile.

## Arquitetura

```text
Exam + questões
  → ExamPrintService cria ExamCopy imutável
  → OmrTemplateVersion + ExamCopy.template_snapshot
  → OmrPageGeometryService projeta a página
  → AnswerSheetGeneratorService emite PDF + QR v5
  → Web/Mobile validam estrutura e capturam marcações
  → servidor valida HMAC, tenant, cópia, template e página
  → revisão humana e correção oficial
```

Fontes de verdade:

- `OmrTemplateVersion.layout_config`: entrada versionada de geometria;
- `ExamCopy`: revisão, ordem, alternativas, questões, template e aparência da cópia;
- `OmrPageGeometryService`: única projeção de frame/células/bolhas/contrato `g`;
- `PrintedQrBindingService`: vínculo semântico do QR autenticado à cópia;
- backend Laravel: autorização e resultado oficial.

## Modelo de dados

### `omr_templates` e `omr_template_versions`

O template define capacidade, colunas, linhas, alternativas, bolhas, fiduciais e
frame. Templates do sistema são somente leitura. Cada versão possui snapshot e hash;
cartões históricos não consultam o layout atual.

O catálogo inicial contém dez organizações A4 retrato sobre um único motor. O
`AnswerSheetType` permanece somente como adapter de dados históricos.

### `exam_copies`

Cada cópia preserva:

- `exam_version`, `card_template_id` e `card_template_version`;
- `validation_hash` opaco;
- `questions_map` e `options_map` da ordem impressa;
- `question_snapshot` com conteúdo, gabarito e pontos;
- `template_snapshot` v2 com aparência, cartão e contexto renderizado;
- aluno/turma quando individualizada.

O QR não substitui esses dados e não concede autorização.

## Geração e geometria

`OmrPageGeometryService` recebe layout, descritores das questões, início da página e
identidade exata do template. A saída contém coordenadas absolutas em milímetros para
o PDF e o vetor compacto `g` para os leitores:

```text
g = [startX, startY, columnStep, rowStep, bubbleWidth, optionStep]
```

Os seis valores são frações do frame escaladas por 10.000. `rpp`, `qs`, `qe`, `oc`,
`tpl_id` e `tpl_v` completam o contrato. A posição em páginas posteriores usa índice
local `question_number - qs`; nunca o número global diretamente.

Antes de escrever, `AnswerSheetGeneratorService::assertCompatible()` verifica versão,
capacidade, tipo, alternativas, gabarito e área segura. Exportação, criação das cópias
e materialização do Dompdf são transacionais.

## Contrato QR

O emissor atual usa v5. v3, early-v4 e full-v4 permanecem legíveis segundo a matriz
publicada. Não existe v6 porque nenhuma mudança incompatível foi necessária.

Campos atuais:

| Campo | Semântica |
| --- | --- |
| `e`, `c`, `h` | Avaliação, cópia imutável e token opaco |
| `p`, `pt` | página e total de páginas |
| `qs`, `qe`, `rpp` | faixa impressa e linhas por coluna |
| `v` | versão do wire QR |
| `tpl_id`, `tpl_v` | template OMR e versão imutável |
| `g`, `oc` | geometria e alternativas reais por questão |
| `gab_enc` | gabarito cifrado aceito apenas para cartões históricos |
| `chk` | HMAC do payload completo |

`v` e `tpl_v` são domínios diferentes. `cols`, `tpl` e `pts` são aceitos apenas por
compatibilidade histórica; não são necessários na emissão v5 atual.

O payload não aceita nome, matrícula, CPF, e-mail, tenant informado pelo cliente ou
gabarito em claro. O HMAC v5 usa tag de 128 bits em base64url; v3/v4 e v5 hexadecimal
histórico continuam verificáveis.

## Segurança e vínculo

O servidor deriva a chave por organização usando `omr_hmac_secret` ou `APP_KEY` como
base, sempre incluindo o identificador interno do tenant na derivação. `verifyPayload`
rejeita versão/campo desconhecido, downgrade sem assinatura, tipo inválido e geometria
fora do frame.

Depois do HMAC, `PrintedQrBindingService` confronta:

- tenant autenticado e organização da Avaliação;
- `e`, `c` e `h` com a `ExamCopy` consultada no escopo autorizado;
- `tpl_id` e `tpl_v` com a versão fixada na cópia/snapshot;
- página, faixa, `rpp` e `oc` com o snapshot imutável;
- posições respondidas com `qs..qe`.

Cópias anteriores aos campos de template têm fallback legado explícito: identidade e
faixa continuam autenticadas, mas o resultado é tratado como vínculo histórico. QR
jamais serve como permissão de leitura ou sincronização.

## Web e Mobile

- Web: `qr-contract.ts` valida objeto, versão, allowlist, tipos e geometria antes do
  worker/OpenCV; o servidor repete estrutura, HMAC e vínculo.
- Mobile: `qr-parser.ts` aplica o mesmo envelope e preserva o objeto assinado original.
  A chave HMAC/AES não é enviada ao dispositivo.
- v3 e early-v4 não habilitam captura offline segura.
- full-v4/v5 permitem capturar geometria e marcações offline; autenticidade e nota
  oficial são concluídas no sync, ou calculadas provisoriamente apenas com cache
  previamente autorizado.
- O build Mobile atual continua single-page; multipágina pertence a `OMR-003`.

Não existe correção autônoma baseada em `gab` plaintext nem provisionamento de segredo
da organização ao aplicativo.

## Embaralhamento e correção

`questions_map` converte posição impressa em ID da questão. `options_map` converte a
bolha visual para a alternativa original. O gabarito oficial vem do snapshot da cópia;
`gab_enc`, quando presente em cartão histórico, é apenas uma verificação adicional
contra divergência e leva a revisão humana se não coincidir. Novos cartões não o
emitem: o snapshot imutável da cópia é a fonte oficial e o payload menor melhora a
leitura física do QR.

Scans Web entram em revisão. No Mobile, marcações são confirmadas, persistidas e
enviadas com o payload QR original; o servidor valida novamente antes de consolidar.

## Perfil físico do QR

Novos cartões usam um único perfil controlado por `OmrQrRendererService`:

- 30 mm, preto sobre branco, correção de erro `M`;
- quiet zone de 4 módulos;
- passo modular mínimo de 0,35 mm; payload maior falha antes da impressão;
- um QR distinto em cada página do cartão;
- gabarito fora do QR novo, reduzindo o pior caso de versão 19 para versão 13.

`npm run test:qr-raster` valida v3, early/full-v4, v5 atual e uma página de 80
questões. O teste usa a view real `answer-sheet-essential`, rasteriza o PDF Dompdf em
150, 200 e 300 dpi e também aplica rotação, blur, baixo contraste e sombra ao SVG.
No envelope fotográfico automatizado, o QR precisa ocupar ao menos 300 px da imagem.
A orientação automática de aproximação no aplicativo ainda pertence a `OMR-002`;
este valor é atualmente um gate de teste, não uma função da UI.

## Limites e tarefas futuras

- papel, impressoras e câmeras reais: homologação humana pendente de `QR-002`;
- dataset fotográfico, confiança e condições adversas: `OMR-001/002`;
- sessão multipágina e associação completa: `OMR-003`;
- pacote puro mais amplo entre Web/Mobile: `OMR-004`;
- fila protegida, conflitos e E2E offline: `OFF-001..003`.

Nenhum resultado de teste digital deve ser apresentado como homologação de papel,
impressora ou câmera real.

## Arquivos principais

| Responsabilidade | Arquivo |
| --- | --- |
| contrato publicado | `contracts/omr/QR_CONTRACT.md` e JSONs adjacentes |
| geometria | `app/Services/OmrPageGeometryService.php` |
| assinatura/cifra | `app/Services/QrCodeSigningService.php` |
| vínculo semântico | `app/Services/PrintedQrBindingService.php` |
| perfil físico/raster | `app/Services/OmrQrRendererService.php` e `tests/qr-raster.mjs` |
| geração | `app/Services/AnswerSheetGeneratorService.php` |
| cópias | `app/Services/ExamPrintService.php` |
| Web OMR | `resources/js/omr-core/` e `OmrController` |
| Mobile | `duoscanner/src/lib/qr-parser.ts` e fluxo `app/scan/` |
