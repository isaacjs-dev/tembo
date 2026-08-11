export interface ImageQualityMetrics {
  brightness: number;
  contrast: number;
  laplacianVariance: number;
  acceptable: boolean;
  reasons: string[];
}

export function rgbaToGrayscale(
  data: Uint8Array | Uint8ClampedArray,
  width: number,
  height: number,
  channels = 4,
): Uint8Array {
  if (![1, 2, 3, 4].includes(channels) || data.length < width * height * channels) {
    throw new Error('Invalid pixel buffer dimensions');
  }
  if (channels === 1) return new Uint8Array(data.slice(0, width * height));
  const gray = new Uint8Array(width * height);
  for (let index = 0; index < gray.length; index += 1) {
    const offset = index * channels;
    gray[index] = channels === 2
      ? data[offset]
      : Math.round(data[offset] * 0.299 + data[offset + 1] * 0.587 + data[offset + 2] * 0.114);
  }
  return gray;
}

export function analyzeImageQuality(gray: Uint8Array, width: number, height: number): ImageQualityMetrics {
  if (gray.length < width * height || width < 3 || height < 3) {
    return { brightness: 0, contrast: 0, laplacianVariance: 0, acceptable: false, reasons: ['invalid_image'] };
  }
  let sum = 0;
  let squareSum = 0;
  for (const value of gray) {
    sum += value;
    squareSum += value * value;
  }
  const brightness = sum / gray.length;
  const contrast = Math.sqrt(Math.max(0, squareSum / gray.length - brightness * brightness));
  let lapSum = 0;
  let lapSquareSum = 0;
  let count = 0;
  for (let y = 1; y < height - 1; y += 1) {
    for (let x = 1; x < width - 1; x += 1) {
      const center = gray[y * width + x];
      const laplacian = gray[(y - 1) * width + x] + gray[(y + 1) * width + x]
        + gray[y * width + x - 1] + gray[y * width + x + 1] - 4 * center;
      lapSum += laplacian;
      lapSquareSum += laplacian * laplacian;
      count += 1;
    }
  }
  const lapMean = count ? lapSum / count : 0;
  const laplacianVariance = count ? Math.max(0, lapSquareSum / count - lapMean * lapMean) : 0;
  const reasons: string[] = [];
  if (brightness < 70) reasons.push('too_dark');
  if (brightness > 235) reasons.push('overexposed');
  if (contrast < 18) reasons.push('low_contrast');
  if (laplacianVariance < 80) reasons.push('blurred');
  return { brightness, contrast, laplacianVariance, acceptable: reasons.length === 0, reasons };
}
