export interface WebImageQuality {
    brightness: number;
    contrast: number;
    laplacianVariance: number;
    acceptable: boolean;
    reasons: string[];
}

export function analyzeRgbaImageQuality(
    rgba: Uint8ClampedArray,
    width: number,
    height: number,
): WebImageQuality {
    if (width < 3 || height < 3 || rgba.length < width * height * 4) {
        return { brightness: 0, contrast: 0, laplacianVariance: 0, acceptable: false, reasons: ['invalid_image'] };
    }
    const gray = new Uint8Array(width * height);
    let sum = 0;
    let squareSum = 0;
    for (let index = 0; index < gray.length; index += 1) {
        const offset = index * 4;
        const value = Math.round(rgba[offset] * 0.299 + rgba[offset + 1] * 0.587 + rgba[offset + 2] * 0.114);
        gray[index] = value;
        sum += value;
        squareSum += value * value;
    }
    const brightness = sum / gray.length;
    const contrast = Math.sqrt(Math.max(0, squareSum / gray.length - brightness * brightness));
    let laplacianSum = 0;
    let laplacianSquareSum = 0;
    let count = 0;
    for (let y = 1; y < height - 1; y += 1) {
        for (let x = 1; x < width - 1; x += 1) {
            const center = gray[y * width + x];
            const laplacian = gray[(y - 1) * width + x] + gray[(y + 1) * width + x]
                + gray[y * width + x - 1] + gray[y * width + x + 1] - 4 * center;
            laplacianSum += laplacian;
            laplacianSquareSum += laplacian * laplacian;
            count += 1;
        }
    }
    const laplacianMean = count ? laplacianSum / count : 0;
    const laplacianVariance = count
        ? Math.max(0, laplacianSquareSum / count - laplacianMean * laplacianMean)
        : 0;
    const reasons: string[] = [];
    if (brightness < 70) reasons.push('too_dark');
    if (brightness > 235) reasons.push('overexposed');
    if (contrast < 18) reasons.push('low_contrast');
    if (laplacianVariance < 80) reasons.push('blurred');
    return { brightness, contrast, laplacianVariance, acceptable: reasons.length === 0, reasons };
}
