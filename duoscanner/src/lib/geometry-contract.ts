export interface CardPageGeometryContract {
  g: [number, number, number, number, number, number];
  rpp: number;
  qs: number;
  qe: number;
  oc: string;
  tpl_id: number;
  tpl_v: number;
}

export interface ResolvedGeometryPixels {
  startX: number;
  startY: number;
  columnStep: number;
  rowStep: number;
  bubbleWidth: number;
  optionStep: number;
}

const positiveInteger = (value: unknown): value is number =>
  typeof value === 'number' && Number.isInteger(value) && value > 0;

export function parseCardPageGeometry(value: unknown): CardPageGeometryContract | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  const raw = value as Record<string, unknown>;
  if (!Array.isArray(raw.g) || raw.g.length !== 6) return null;
  if (!raw.g.every((item) => Number.isInteger(item) && item >= 0 && item <= 10000)) return null;
  if (!positiveInteger(raw.rpp) || !positiveInteger(raw.qs) || !positiveInteger(raw.qe)) return null;
  if (!positiveInteger(raw.tpl_id) || !positiveInteger(raw.tpl_v)) return null;
  if (raw.qe < raw.qs) return null;
  if (typeof raw.oc !== 'string' || !/^(?:0|[2-9])+$/.test(raw.oc)) return null;
  if (raw.oc.length !== raw.qe - raw.qs + 1) return null;

  const g = raw.g as number[];
  if (g[2] <= 0 || g[3] <= 0 || g[4] <= 0 || g[5] <= 0) return null;
  if (g[0] + g[4] > 10000 || g[1] + g[4] > 10000) return null;
  const questionCount = raw.qe - raw.qs + 1;
  const columns = Math.ceil(questionCount / raw.rpp);
  const rows = Math.min(questionCount, raw.rpp);
  const maxOptions = Math.max(...raw.oc.split('').map(Number));
  if (g[0] + ((columns - 1) * g[2]) + (Math.max(0, maxOptions - 1) * g[5]) + g[4] > 10000) return null;
  if (g[1] + ((rows - 1) * g[3]) + g[4] > 10000) return null;

  return {
    g: g as CardPageGeometryContract['g'],
    rpp: raw.rpp,
    qs: raw.qs,
    qe: raw.qe,
    oc: raw.oc,
    tpl_id: raw.tpl_id,
    tpl_v: raw.tpl_v,
  };
}

export function resolvePageLocalGridPosition(
  printedQuestionNumber: number,
  questionStart: number,
  rowsPerColumn: number
): { column: number; row: number } {
  const localIndex = printedQuestionNumber - questionStart;
  if (!Number.isInteger(localIndex) || localIndex < 0 || !positiveInteger(rowsPerColumn)) {
    throw new Error('Questão fora da geometria assinada desta página.');
  }

  return { column: Math.floor(localIndex / rowsPerColumn), row: localIndex % rowsPerColumn };
}

export function isValidGeometryVector(value: unknown): value is number[] {
  return Array.isArray(value)
    && value.length === 6
    && value.every((item) => Number.isInteger(item) && item >= 0 && item <= 10000)
    && value[2] > 0
    && value[3] > 0
    && value[4] > 0
    && value[5] > 0;
}

export function resolveCardPageGeometryPixels(
  geometry: number[],
  width: number,
  height: number
): ResolvedGeometryPixels {
  if (!isValidGeometryVector(geometry) || width <= 0 || height <= 0) {
    throw new Error('Geometria OMR inválida.');
  }
  const [startX, startY, columnStep, rowStep, bubbleWidth, optionStep] = geometry;

  return {
    startX: (startX / 10000) * width,
    startY: (startY / 10000) * height,
    columnStep: (columnStep / 10000) * width,
    rowStep: (rowStep / 10000) * height,
    bubbleWidth: (bubbleWidth / 10000) * width,
    optionStep: (optionStep / 10000) * width,
  };
}
