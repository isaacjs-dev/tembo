/**
 * Detecta os 4 marcadores fiduciais (quadrados pretos) nos cantos de um cartão-resposta.
 *
 * Usa BFS flood-fill sobre pixel data para encontrar regiões escuras
 * que sejam aproximadamente quadradas e estejam nos cantos da imagem.
 */

export interface Point2D {
  x: number;
  y: number;
}

export interface FiducialResult {
  corners: Point2D[]; // [TL, TR, BR, BL] — sempre 4, ou vazio se falhou
  confidence: number; // 0..1
  found: number; // quantos dos 4 foram encontrados
  candidates: FiducialCandidate[];
}

interface FiducialCandidate {
  center: Point2D;
  area: number;
  aspectRatio: number;
  fillRatio: number;
  boundingBox: { x: number; y: number; w: number; h: number };
}

// Thresholds
const DARK_THRESHOLD = 100; // Pixel abaixo disso é "escuro"
const MIN_AREA_RATIO = 0.0004; // Mínimo 0.04% da imagem
const MAX_AREA_RATIO = 0.025; // Máximo 2.5% da imagem
const MIN_ASPECT = 0.5;
const MAX_ASPECT = 2.0;
const MIN_FILL = 0.45;

/**
 * Detecta fiduciais a partir de pixel data (grayscale ou RGBA).
 */
export function detectFiducials(
  pixelData: Uint8Array,
  width: number,
  height: number,
  channels: number
): FiducialResult {
  const totalPixels = width * height;
  const minArea = Math.floor(totalPixels * MIN_AREA_RATIO);
  const maxArea = Math.floor(totalPixels * MAX_AREA_RATIO);

  // Converter para grayscale se necessário
  const gray = toGrayscale(pixelData, width, height, channels);

  // Binarizar: 1 = escuro, 0 = claro
  const binary = new Uint8Array(totalPixels);
  for (let i = 0; i < totalPixels; i++) {
    binary[i] = gray[i] < DARK_THRESHOLD ? 1 : 0;
  }

  // BFS para encontrar componentes conexos escuros
  const visited = new Uint8Array(totalPixels);
  const candidates: FiducialCandidate[] = [];

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const idx = y * width + x;
      if (binary[idx] === 0 || visited[idx] === 1) continue;

      // BFS flood-fill
      const component = bfs(binary, visited, width, height, x, y);

      if (component.area < minArea || component.area > maxArea) continue;

      // Verificar aspect ratio
      const bw = component.maxX - component.minX + 1;
      const bh = component.maxY - component.minY + 1;
      const aspect = bw / Math.max(bh, 1);

      if (aspect < MIN_ASPECT || aspect > MAX_ASPECT) continue;

      // Verificar fill ratio (pixels escuros / bounding box area)
      const boxArea = bw * bh;
      const fillRatio = component.area / Math.max(boxArea, 1);

      if (fillRatio < MIN_FILL) continue;

      candidates.push({
        center: {
          x: (component.minX + component.maxX) / 2,
          y: (component.minY + component.maxY) / 2,
        },
        area: component.area,
        aspectRatio: aspect,
        fillRatio,
        boundingBox: {
          x: component.minX,
          y: component.minY,
          w: bw,
          h: bh,
        },
      });
    }
  }

  // Selecionar os 4 mais próximos dos cantos
  const corners = selectFourCorners(candidates, width, height);

  return {
    corners: corners.map((c) => c.center),
    confidence: corners.length === 4 ? computeConfidence(corners, width, height) : 0,
    found: corners.length,
    candidates,
  };
}

interface BFSResult {
  area: number;
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
}

function bfs(
  binary: Uint8Array,
  visited: Uint8Array,
  width: number,
  height: number,
  startX: number,
  startY: number
): BFSResult {
  const queue: number[] = [];
  const startIdx = startY * width + startX;
  queue.push(startIdx);
  visited[startIdx] = 1;

  let area = 0;
  let minX = startX,
    maxX = startX,
    minY = startY,
    maxY = startY;

  const dx = [0, 1, 0, -1];
  const dy = [1, 0, -1, 0];

  let head = 0;
  while (head < queue.length) {
    const idx = queue[head++];
    const cx = idx % width;
    const cy = Math.floor(idx / width);
    area++;

    minX = Math.min(minX, cx);
    maxX = Math.max(maxX, cx);
    minY = Math.min(minY, cy);
    maxY = Math.max(maxY, cy);

    for (let d = 0; d < 4; d++) {
      const nx = cx + dx[d];
      const ny = cy + dy[d];
      if (nx < 0 || nx >= width || ny < 0 || ny >= height) continue;
      const nIdx = ny * width + nx;
      if (visited[nIdx] === 1 || binary[nIdx] === 0) continue;
      visited[nIdx] = 1;
      queue.push(nIdx);
    }
  }

  return { area, minX, maxX, minY, maxY };
}

function selectFourCorners(
  candidates: FiducialCandidate[],
  imgW: number,
  imgH: number
): FiducialCandidate[] {
  if (candidates.length < 4) return candidates;

  const targetCorners: Point2D[] = [
    { x: 0, y: 0 }, // TL
    { x: imgW, y: 0 }, // TR
    { x: imgW, y: imgH }, // BR
    { x: 0, y: imgH }, // BL
  ];

  const selected: FiducialCandidate[] = [];
  const used = new Set<number>();

  for (const target of targetCorners) {
    let bestIdx = -1;
    let bestDist = Infinity;

    for (let i = 0; i < candidates.length; i++) {
      if (used.has(i)) continue;
      const c = candidates[i].center;
      const dist = Math.sqrt((c.x - target.x) ** 2 + (c.y - target.y) ** 2);
      if (dist < bestDist) {
        bestDist = dist;
        bestIdx = i;
      }
    }

    if (bestIdx >= 0) {
      used.add(bestIdx);
      selected.push(candidates[bestIdx]);
    }
  }

  return selected;
}

function computeConfidence(
  corners: FiducialCandidate[],
  imgW: number,
  imgH: number
): number {
  // Confiança baseada em:
  // 1. Todos os 4 encontrados
  // 2. Aspect ratios próximos de 1
  // 3. Fill ratios altos
  // 4. Áreas similares entre si

  if (corners.length !== 4) return 0;

  const areas = corners.map((c) => c.area);
  const meanArea = areas.reduce((a, b) => a + b, 0) / 4;
  const areaVariance = areas.reduce((s, a) => s + (a - meanArea) ** 2, 0) / 4;
  const areaCv = Math.sqrt(areaVariance) / Math.max(meanArea, 1); // coeficiente de variação

  const avgFill = corners.reduce((s, c) => s + c.fillRatio, 0) / 4;
  const avgAspect = corners.reduce((s, c) => s + Math.abs(1 - c.aspectRatio), 0) / 4;

  let conf = 1.0;
  conf -= areaCv * 0.5; // Penalizar variação de área
  conf -= (1 - avgFill) * 0.3; // Penalizar fill baixo
  conf -= avgAspect * 0.2; // Penalizar aspect ratio não-quadrado

  return Math.max(0, Math.min(1, conf));
}

function toGrayscale(
  data: Uint8Array,
  width: number,
  height: number,
  channels: number
): Uint8Array {
  const totalPixels = width * height;

  if (channels === 1) return data;

  const gray = new Uint8Array(totalPixels);

  for (let i = 0; i < totalPixels; i++) {
    const offset = i * channels;
    if (channels >= 3) {
      // Luminance: 0.299R + 0.587G + 0.114B
      gray[i] = Math.round(
        data[offset] * 0.299 + data[offset + 1] * 0.587 + data[offset + 2] * 0.114
      );
    } else if (channels === 2) {
      // Grayscale + alpha
      gray[i] = data[offset];
    }
  }

  return gray;
}
