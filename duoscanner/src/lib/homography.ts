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

  // Fix h33=1 and solve the resulting 8x8 system directly. Solving A^T*A
  // as a homogeneous system is singular by construction and was numerically
  // unstable even for a simple translated rectangle.
  const A: number[][] = [];
  const b: number[] = [];

  for (let i = 0; i < 4; i++) {
    const sx = src[i].x,
      sy = src[i].y;
    const dx = dst[i].x,
      dy = dst[i].y;

    A.push([sx, sy, 1, 0, 0, 0, -dx * sx, -dx * sy]);
    b.push(dx);
    A.push([0, 0, 0, sx, sy, 1, -dy * sx, -dy * sy]);
    b.push(dy);
  }

  const h = solveLinearSystem(A, b);
  if (!h) throw new Error('Homography point configuration is singular');
  return [...h, 1];
}

export function reprojectionError(H: number[], src: Point2D[], dst: Point2D[]): { rms: number; max: number } {
  if (src.length !== dst.length || src.length === 0) throw new Error('Reprojection needs matching points');
  const errors = src.map((point, index) => {
    const projected = transformPoint(H, point);
    return Math.hypot(projected.x - dst[index].x, projected.y - dst[index].y);
  });
  return {
    rms: Math.sqrt(errors.reduce((sum, value) => sum + value * value, 0) / errors.length),
    max: Math.max(...errors),
  };
}

export function validateFiducialQuadrilateral(
  ordered: Point2D[],
  imageWidth: number,
  imageHeight: number,
): { valid: boolean; reason?: string; areaRatio: number } {
  if (ordered.length !== 4 || imageWidth <= 0 || imageHeight <= 0) {
    return { valid: false, reason: 'fiducial_count', areaRatio: 0 };
  }

  const expectedRegions = [
    (point: Point2D) => point.x < imageWidth * 0.45 && point.y < imageHeight * 0.45,
    (point: Point2D) => point.x > imageWidth * 0.55 && point.y < imageHeight * 0.45,
    (point: Point2D) => point.x > imageWidth * 0.55 && point.y > imageHeight * 0.55,
    (point: Point2D) => point.x < imageWidth * 0.45 && point.y > imageHeight * 0.55,
  ];
  if (!ordered.every((point, index) => expectedRegions[index](point))) {
    return { valid: false, reason: 'fiducial_position', areaRatio: 0 };
  }

  const signedCrossProducts = ordered.map((point, index) => {
    const next = ordered[(index + 1) % ordered.length];
    const after = ordered[(index + 2) % ordered.length];
    return (next.x - point.x) * (after.y - next.y) - (next.y - point.y) * (after.x - next.x);
  });
  const isConvex = signedCrossProducts.every((value) => value > 0)
    || signedCrossProducts.every((value) => value < 0);
  if (!isConvex) return { valid: false, reason: 'fiducial_quad_not_convex', areaRatio: 0 };

  const twiceArea = ordered.reduce((sum, point, index) => {
    const next = ordered[(index + 1) % ordered.length];
    return sum + point.x * next.y - next.x * point.y;
  }, 0);
  const areaRatio = Math.abs(twiceArea) / 2 / (imageWidth * imageHeight);
  if (areaRatio < 0.20 || areaRatio > 0.98) {
    return { valid: false, reason: 'fiducial_quad_area', areaRatio };
  }

  return { valid: true, areaRatio };
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

function solveLinearSystem(matrix: number[][], values: number[]): number[] | null {
  const n = matrix.length;
  const m = matrix.map((row, index) => [...row, values[index]]);

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

    if (maxVal < 1e-10) return null;

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
    if (Math.abs(m[i][i]) < 1e-10) return null;
    x[i] = m[i][n];
    for (let j = i + 1; j < n; j++) {
      x[i] -= m[i][j] * x[j];
    }
    x[i] /= m[i][i];
  }

  return x;
}
