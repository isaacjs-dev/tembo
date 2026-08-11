/**
 * Classificador de bolhas para OMR.
 *
 * Extrai ROIs da imagem warped e calcula fill_ratio para cada bolha,
 * determinando se está preenchida, vazia, ambígua, etc.
 */

export interface BubbleROI {
  x: number;
  y: number;
  w: number;
  h: number;
}

export type BubbleState = 'filled' | 'empty' | 'partial' | 'ambiguous';

export interface BubbleClassification {
  state: BubbleState;
  fillRatio: number;
  confidence: number;
}

export interface BubbleResult extends BubbleClassification {
  label: string;
  roi: BubbleROI;
}

export interface QuestionResult {
  type: 'answered' | 'blank' | 'multiple_marks' | 'ambiguous';
  optionIndex: number | null;
  optionIndices: number[];
  fillRatios: number[];
  confidence: number;
  // Also available for richer API:
  selectedLabel?: string | null;
  selectedIndex?: number | null;
  status?: 'OK' | 'BLANK' | 'DOUBLE' | 'UNCERTAIN';
}

// Thresholds padrão
const DEFAULT_THRESHOLDS = {
  mark: 0.40,
  blank: 0.12,
  uncertainLow: 0.20,
  uncertainHigh: 0.38,
};

/**
 * Extrai uma região (ROI) de um array de pixels grayscale.
 *
 * Overloads:
 *  - extractROI(data, width, roi)          — infere height
 *  - extractROI(data, width, height, roi)  — explícito
 */
export function extractROI(
  grayData: Uint8Array,
  width: number,
  heightOrRoi: number | BubbleROI,
  maybeRoi?: BubbleROI
): Uint8Array {
  let height: number;
  let roi: BubbleROI;

  if (typeof heightOrRoi === 'number') {
    height = heightOrRoi;
    roi = maybeRoi!;
  } else {
    // height inferido do tamanho dos dados
    height = Math.floor(grayData.length / width);
    roi = heightOrRoi;
  }

  const x = Math.max(0, Math.round(roi.x));
  const y = Math.max(0, Math.round(roi.y));
  const w = Math.min(Math.round(roi.w), width - x);
  const h = Math.min(Math.round(roi.h), height - y);

  const extracted = new Uint8Array(w * h);

  for (let row = 0; row < h; row++) {
    for (let col = 0; col < w; col++) {
      const srcIdx = (y + row) * width + (x + col);
      extracted[row * w + col] = grayData[srcIdx] ?? 255;
    }
  }

  return extracted;
}

/**
 * Classifica uma bolha individual a partir de dados de pixel já extraídos.
 *
 * Overloads:
 *  - classifyBubble(roiData)                             — dados já extraídos
 *  - classifyBubble(grayData, width, height, roi, label) — extrai e classifica
 */
export function classifyBubble(
  dataOrGray: Uint8Array,
  widthOrNothing?: number,
  heightOrNothing?: number,
  roiOrNothing?: BubbleROI,
  labelOrNothing?: string,
  darkThreshold: number = 128,
  thresholds = DEFAULT_THRESHOLDS
): BubbleClassification | BubbleResult {
  let roiData: Uint8Array;
  let label: string | undefined;
  let roi: BubbleROI | undefined;

  if (widthOrNothing !== undefined && heightOrNothing !== undefined && roiOrNothing !== undefined) {
    // Full form: classifyBubble(grayData, width, height, roi, label)
    roiData = extractROI(dataOrGray, widthOrNothing, heightOrNothing, roiOrNothing);
    label = labelOrNothing;
    roi = roiOrNothing;
  } else {
    // Short form: classifyBubble(roiData) — data already extracted
    roiData = dataOrGray;
  }

  const totalPixels = roiData.length;

  if (totalPixels === 0) {
    const base: BubbleClassification = { fillRatio: 0, state: 'empty', confidence: 0 };
    if (label !== undefined && roi !== undefined) {
      return { ...base, label, roi } as BubbleResult;
    }
    return base;
  }

  // Calcular Otsu threshold local
  const localThreshold = otsuThreshold(roiData) || darkThreshold;

  // Contar pixels escuros
  let darkCount = 0;
  for (let i = 0; i < totalPixels; i++) {
    if (roiData[i] <= localThreshold) {
      darkCount++;
    }
  }

  const fillRatio = darkCount / totalPixels;

  // Classificar estado
  let state: BubbleState;
  if (fillRatio >= thresholds.mark) {
    state = 'filled';
  } else if (fillRatio <= thresholds.blank) {
    state = 'empty';
  } else if (fillRatio >= thresholds.uncertainLow && fillRatio < thresholds.uncertainHigh) {
    state = 'partial';
  } else {
    state = 'ambiguous';
  }

  const confidence = state === 'filled' ? fillRatio : state === 'empty' ? 1 - fillRatio : 0.5;

  const base: BubbleClassification = { fillRatio, state, confidence };

  if (label !== undefined && roi !== undefined) {
    return { ...base, label, roi } as BubbleResult;
  }
  return base;
}

/**
 * Seleciona a resposta para uma questão baseado nos resultados das bolhas.
 *
 * Aceita tanto BubbleClassification[] (simples) quanto BubbleResult[] (com label/roi).
 * Retorna QuestionResult com campos compatíveis com omr-processor.ts.
 */
export function selectAnswer(
  bubbles: (BubbleClassification | BubbleResult)[],
  thresholds = DEFAULT_THRESHOLDS
): QuestionResult {
  const fillRatios = bubbles.map((b) => b.fillRatio);

  const filledIndices: number[] = [];
  const uncertainIndices: number[] = [];

  for (let i = 0; i < bubbles.length; i++) {
    if (bubbles[i].fillRatio >= thresholds.mark) {
      filledIndices.push(i);
    } else if (bubbles[i].fillRatio > thresholds.blank) {
      uncertainIndices.push(i);
    }
  }

  if (filledIndices.length === 0) {
    if (uncertainIndices.length > 0) {
      return {
        type: 'ambiguous',
        optionIndex: null,
        optionIndices: uncertainIndices,
        fillRatios,
        confidence: 0.3,
        selectedLabel: null,
        selectedIndex: null,
        status: 'UNCERTAIN',
      };
    }
    return {
      type: 'blank',
      optionIndex: null,
      optionIndices: [],
      fillRatios,
      confidence: 1.0,
      selectedLabel: null,
      selectedIndex: null,
      status: 'BLANK',
    };
  }

  if (filledIndices.length === 1) {
    const idx = filledIndices[0];
    const selected = bubbles[idx];
    let confidence = selected.fillRatio;
    let type: 'answered' | 'ambiguous' = 'answered';
    let status: 'OK' | 'UNCERTAIN' = 'OK';

    for (const uIdx of uncertainIndices) {
      if (bubbles[uIdx].fillRatio > selected.fillRatio * 0.6) {
        type = 'ambiguous';
        status = 'UNCERTAIN';
        confidence *= 0.7;
        break;
      }
    }

    const label = 'label' in selected ? (selected as BubbleResult).label : null;

    return {
      type,
      optionIndex: idx,
      optionIndices: filledIndices,
      fillRatios,
      confidence: Math.min(1, confidence),
      selectedLabel: label,
      selectedIndex: idx,
      status,
    };
  }

  // Mais de 1 preenchida: DOUBLE / multiple_marks
  return {
    type: 'multiple_marks',
    optionIndex: null,
    optionIndices: filledIndices,
    fillRatios,
    confidence: 0.1,
    selectedLabel: null,
    selectedIndex: null,
    status: 'DOUBLE',
  };
}

/**
 * Calcula threshold Otsu para um array de pixels grayscale.
 */
function otsuThreshold(data: Uint8Array): number {
  const hist = new Array(256).fill(0);
  for (let i = 0; i < data.length; i++) {
    hist[data[i]]++;
  }

  const total = data.length;
  let sumAll = 0;
  for (let i = 0; i < 256; i++) sumAll += i * hist[i];

  let sumBg = 0;
  let weightBg = 0;
  let maxVariance = 0;
  let threshold = 0;

  for (let t = 0; t < 256; t++) {
    weightBg += hist[t];
    if (weightBg === 0) continue;

    const weightFg = total - weightBg;
    if (weightFg === 0) break;

    sumBg += t * hist[t];

    const meanBg = sumBg / weightBg;
    const meanFg = (sumAll - sumBg) / weightFg;

    const variance = weightBg * weightFg * (meanBg - meanFg) ** 2;

    if (variance > maxVariance) {
      maxVariance = variance;
      threshold = t;
    }
  }

  return threshold;
}
