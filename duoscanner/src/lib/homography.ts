/**
 * Homografia e transformação perspectiva em JavaScript puro.
 *
 * Implementa DLT (Direct Linear Transform) para calcular a matriz 3x3
 * de transformação perspectiva entre 4 pontos de origem e 4 de destino.
 */

export interface Point2D {
  x: number;
  y: number;
}

/**
 * Calcula a matriz de homografia 3x3 usando DLT.
 * src e dst devem ter exatamente 4 pontos cada.
 *
 * Retorna array 9 elementos [h00,h01,h02,h10,h11,h12,h20,h21,h22]
 */
export function computeHomography(src: Point2D[], dst: Point2D[]): number[] {
  if (src.length !== 4 || dst.length !== 4) {
    throw new Error('Homography requires exactly 4 point pairs');
  }

  // Montar sistema Ah = 0 (8x9)
  const A: number[][] = [];

  for (let i = 0; i < 4; i++) {
    const sx = src[i].x,
      sy = src[i].y;
    const dx = dst[i].x,
      dy = dst[i].y;

    A.push([-sx, -sy, -1, 0, 0, 0, dx * sx, dx * sy, dx]);
    A.push([0, 0, 0, -sx, -sy, -1, dy * sx, dy * sy, dy]);
  }

  // Resolver usando SVD simplificada (para 8x9 podemos usar eliminação gaussiana)
  const h = solveHomogeneous(A);

  return h;
}

/**
 * Inverte uma homografia 3x3.
 */
export function invertHomography(H: number[]): number[] {
  // Inversão de matriz 3x3
  const [a, b, c, d, e, f, g, h, i] = H;

  const det = a * (e * i - f * h) - b * (d * i - f * g) + c * (d * h - e * g);

  if (Math.abs(det) < 1e-10) {
    throw new Error('Homography matrix is singular');
  }

  const invDet = 1 / det;

  return [
    (e * i - f * h) * invDet,
    (c * h - b * i) * invDet,
    (b * f - c * e) * invDet,
    (f * g - d * i) * invDet,
    (a * i - c * g) * invDet,
    (c * d - a * f) * invDet,
    (d * h - e * g) * invDet,
    (b * g - a * h) * invDet,
    (a * e - b * d) * invDet,
  ];
}

/**
 * Aplica a transformação perspectiva a um ponto.
 */
export function transformPoint(H: number[], p: Point2D): Point2D {
  const w = H[6] * p.x + H[7] * p.y + H[8];
  if (Math.abs(w) < 1e-10) return { x: 0, y: 0 };

  return {
    x: (H[0] * p.x + H[1] * p.y + H[2]) / w,
    y: (H[3] * p.x + H[4] * p.y + H[5]) / w,
  };
}

/**
 * Aplica warp perspectiva a um array de pixels (grayscale).
 * Gera uma nova imagem de tamanho dstW x dstH.
 */
export function warpPerspective(
  srcData: Uint8Array,
  srcW: number,
  srcH: number,
  channels: number,
  H: number[],
  dstW: number,
  dstH: number
): Uint8Array {
  const Hinv = invertHomography(H);
  const dst = new Uint8Array(dstW * dstH * channels);

  for (let y = 0; y < dstH; y++) {
    for (let x = 0; x < dstW; x++) {
      // Mapear pixel destino para fonte
      const srcPt = transformPoint(Hinv, { x, y });
      const sx = Math.round(srcPt.x);
      const sy = Math.round(srcPt.y);

      const dstIdx = (y * dstW + x) * channels;

      if (sx >= 0 && sx < srcW && sy >= 0 && sy < srcH) {
        const srcIdx = (sy * srcW + sx) * channels;
        for (let c = 0; c < channels; c++) {
          dst[dstIdx + c] = srcData[srcIdx + c];
        }
      } else {
        // Fora da imagem: branco
        for (let c = 0; c < channels; c++) {
          dst[dstIdx + c] = 255;
        }
      }
    }
  }

  return dst;
}

/**
 * Ordena 4 pontos como [TL, TR, BR, BL] baseado nas coordenadas.
 */
export function sortCorners(points: Point2D[]): Point2D[] {
  if (points.length !== 4) return points;

  // Centroide
  const cx = points.reduce((s, p) => s + p.x, 0) / 4;
  const cy = points.reduce((s, p) => s + p.y, 0) / 4;

  // Classificar por quadrante relativo ao centroide
  const topLeft = points.filter((p) => p.x < cx && p.y < cy);
  const topRight = points.filter((p) => p.x >= cx && p.y < cy);
  const bottomRight = points.filter((p) => p.x >= cx && p.y >= cy);
  const bottomLeft = points.filter((p) => p.x < cx && p.y >= cy);

  // Se algum quadrante ficou vazio, usar distância aos cantos
  if (
    topLeft.length !== 1 ||
    topRight.length !== 1 ||
    bottomRight.length !== 1 ||
    bottomLeft.length !== 1
  ) {
    return sortCornersByDistance(points);
  }

  return [topLeft[0], topRight[0], bottomRight[0], bottomLeft[0]];
}

function sortCornersByDistance(points: Point2D[]): Point2D[] {
  // Ordenar por soma x+y (TL tem menor soma)
  const sorted = [...points].sort((a, b) => a.x + a.y - (b.x + b.y));
  const tl = sorted[0];
  const br = sorted[3];

  // Dos 2 restantes, o de maior x é TR, o de menor x é BL
  const remaining = sorted.slice(1, 3);
  remaining.sort((a, b) => a.x - b.x);
  const bl = remaining[0];
  const tr = remaining[1];

  return [tl, tr, br, bl];
}

// =============================================================================
// Resolvedor de sistema homogêneo (SVD simplificado para 8x9)
// =============================================================================

function solveHomogeneous(A: number[][]): number[] {
  const rows = A.length;
  const cols = A[0].length;

  // Calcular A^T * A (9x9)
  const ATA: number[][] = [];
  for (let i = 0; i < cols; i++) {
    ATA[i] = [];
    for (let j = 0; j < cols; j++) {
      let sum = 0;
      for (let k = 0; k < rows; k++) {
        sum += A[k][i] * A[k][j];
      }
      ATA[i][j] = sum;
    }
  }

  // Encontrar autovetor com menor autovalor usando iteração inversa
  // Começar com iteração de potência no inverso de ATA
  const n = cols;
  let v = new Array(n).fill(1 / Math.sqrt(n));

  // Usar Gauss-Jordan para resolver (ATA)x = v iterativamente
  for (let iter = 0; iter < 30; iter++) {
    const augmented = ATA.map((row, i) => [...row, v[i]]);
    const sol = gaussianElimination(augmented, n);
    if (!sol) break;

    // Normalizar
    const norm = Math.sqrt(sol.reduce((s, x) => s + x * x, 0));
    if (norm < 1e-10) break;
    v = sol.map((x) => x / norm);
  }

  return v;
}

function gaussianElimination(augmented: number[][], n: number): number[] | null {
  const m = augmented.map((row) => [...row]);

  // Forward elimination com pivoteamento parcial
  for (let col = 0; col < n; col++) {
    // Encontrar pivot
    let maxVal = Math.abs(m[col][col]);
    let maxRow = col;
    for (let row = col + 1; row < n; row++) {
      if (Math.abs(m[row][col]) > maxVal) {
        maxVal = Math.abs(m[row][col]);
        maxRow = row;
      }
    }

    if (maxVal < 1e-12) continue;

    // Trocar linhas
    if (maxRow !== col) {
      [m[col], m[maxRow]] = [m[maxRow], m[col]];
    }

    // Eliminar
    for (let row = col + 1; row < n; row++) {
      const factor = m[row][col] / m[col][col];
      for (let j = col; j <= n; j++) {
        m[row][j] -= factor * m[col][j];
      }
    }
  }

  // Back substitution
  const x = new Array(n).fill(0);
  for (let i = n - 1; i >= 0; i--) {
    if (Math.abs(m[i][i]) < 1e-12) {
      x[i] = 0;
      continue;
    }
    x[i] = m[i][n];
    for (let j = i + 1; j < n; j++) {
      x[i] -= m[i][j] * x[j];
    }
    x[i] /= m[i][i];
  }

  return x;
}
