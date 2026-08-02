# Como Calibrar o OMR

## Pré-requisitos

1. Template criado no sistema (via `/omr/templates/create`)
2. Python 3.10+ com dependências: `pip install -r platform/omr-engine/requirements.txt`
3. 20-50 imagens de amostra (scans do cartão-resposta com o template alvo)

## Passo 1 — Preparar amostras

Coloque 20-50 imagens escaneadas/fotografadas no storage:

```bash
mkdir -p platform/storage/app/public/calibration-samples/
# Copie as imagens para lá (PNG ou JPG)
```

As imagens devem ter:
- Variedade de preenchimento (bolhas marcadas e não marcadas)
- Diferentes condições (iluminação, ângulo, qualidade)
- Pelo menos metade com respostas marcadas

## Passo 2 — Rodar calibração

```bash
cd platform
php artisan omr:calibrate {TEMPLATE_ID} --images-dir=calibration-samples/ --sample-count=20
```

### Parâmetros

| Parâmetro | Descrição |
|-----------|-----------|
| `{TEMPLATE_ID}` | ID do template no banco (consulte `/omr/templates`) |
| `--images-dir` | Diretório relativo ao `storage/app/public/` |
| `--sample-count` | Mínimo de imagens (padrão: 20) |

## Passo 3 — Interpretar resultados

O comando mostra:

1. **Taxa de detecção de cantos**: quantas imagens tiveram os 4 marcadores detectados
   - Ideal: > 90%
   - Se < 70%: verificar qualidade das imagens ou posição dos marcadores no template

2. **Distribuição de fill scores**: percentis P5 a P95
   - P5-P25: scores de bolhas vazias
   - P75-P95: scores de bolhas preenchidas
   - Se P25 e P75 estão muito próximos: separação ruim (melhorar imagens ou marcadores)

3. **Thresholds sugeridos**:
   - `blank`: abaixo disso = definitivamente vazia
   - `uncertain_low` / `uncertain_high`: zona cinza (marca parcial, rasura)
   - `mark`: acima disso = definitivamente marcada

## Passo 4 — Ajustar manualmente (se necessário)

Se os thresholds sugeridos não forem bons:

1. Editar via `/omr/templates/{id}/edit` na interface web
2. Ou via JSON:

```bash
# Exportar template atual
curl http://localhost:8000/omr/templates/{id}/export > template.json

# Testar com uma imagem específica
cd platform/omr-engine
python omr_read.py --image /caminho/scan.png --template template.json --debug ./debug_output

# Verificar debug_output/06_overlay_rois.png para ver as detecções
# Verificar debug_output/result.json para ver os scores
```

3. Ajustar thresholds no template e repetir até que a leitura fique consistente.

## Passo 5 — Validar

Após calibrar, testar com imagens novas (não usadas na calibração):

```bash
# Rodar engine em uma imagem de teste
python platform/omr-engine/omr_read.py \
  --image storage/app/public/omr-scans/teste.png \
  --template template_exportado.json \
  --debug ./debug_validacao
```

Verificar:
- `result.json` → `quality.needs_review` deve ser `false` para scans bons
- `06_overlay_rois.png` → ROIs devem estar sobre as bolhas reais
- `04_corners.png` → Cantos devem estar sobre os marcadores pretos

## Thresholds Recomendados (ponto de partida)

| Threshold | Valor típico | Descrição |
|-----------|:----------:|-----------|
| `blank` | 0.12 | Bolha completamente vazia |
| `uncertain_low` | 0.20 | Início da zona incerta |
| `uncertain_high` | 0.38 | Fim da zona incerta |
| `mark` | 0.45 | Bolha claramente preenchida |

## Dicas

- **Caneta vs Lápis**: caneta geralmente gera scores mais altos (0.6-0.9) vs lápis (0.4-0.7)
- **Rasura**: se o aluno rasurou, o score fica na zona 0.2-0.4 → cai em UNCERTAIN
- **Sombra na foto**: pode aumentar scores de bolhas vazias para 0.1-0.2 → ajustar `blank` para 0.18
- **Se muitas questões caem em UNCERTAIN**: reduzir `uncertain_high` ou aumentar `mark`
- **Se bolhas preenchidas não são detectadas**: reduzir `mark` (ex: de 0.45 para 0.35)

## Ajuste fino por scan (emergencial)

Na tela de revisão web, existe ajuste de offset (dx, dy) por scan individual.
Isso resolve pequenos desalinhamentos sem precisar recalibrar o template inteiro.

## Debug

Todos os scans processados salvam debug outputs em `storage/app/public/omr-debug/{scan_id}/`:

| Arquivo | Descrição |
|---------|-----------|
| `01_original.png` | Imagem como recebida |
| `02_gray.png` | Convertida para cinza |
| `03_thresh.png` | Binarizada (threshold adaptativo) |
| `04_corners.png` | Marcadores detectados (círculos vermelhos) |
| `05_warped.png` | Imagem planificada (após homografia) |
| `06_overlay_rois.png` | ROIs das bolhas sobrepostas (verde=marcada, cinza=vazia) |
| `result.json` | Resultado completo com scores por bolha |
