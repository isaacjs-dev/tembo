import { describe, expect, it } from 'vitest';
import { analyzeRgbaImageQuality } from '../image-quality';

function rgba(values: number[]): Uint8ClampedArray {
    return new Uint8ClampedArray(values.flatMap((value) => [value, value, value, 255]));
}

describe('analyzeRgbaImageQuality', () => {
    it('rejects flat overexposed images', () => {
        const quality = analyzeRgbaImageQuality(rgba(new Array(25).fill(255)), 5, 5);
        expect(quality.acceptable).toBe(false);
        expect(quality.reasons).toContain('overexposed');
        expect(quality.reasons).toContain('low_contrast');
    });

    it('accepts a sharp high-contrast pattern', () => {
        const values = Array.from({ length: 25 }, (_, index) => {
            const x = index % 5;
            const y = Math.floor(index / 5);
            return (x + y) % 2 ? 220 : 30;
        });
        expect(analyzeRgbaImageQuality(rgba(values), 5, 5).acceptable).toBe(true);
    });
});
