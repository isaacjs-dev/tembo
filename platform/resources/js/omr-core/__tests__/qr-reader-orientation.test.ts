import { beforeAll, describe, expect, it } from 'vitest';
import { QrReader } from '../qr_reader';

beforeAll(() => {
    globalThis.ImageData = class ImageData {
        data: Uint8ClampedArray;
        width: number;
        height: number;
        constructor(data: Uint8ClampedArray, width: number, height: number) {
            this.data = data;
            this.width = width;
            this.height = height;
        }
    } as any;
});

describe('QR orientation', () => {
    it('maps the canonical top-right QR quadrant back from all rotations', () => {
        const location = (x: number, y: number) => ({
            topLeftCorner: { x: x - 2, y: y - 2 }, topRightCorner: { x: x + 2, y: y - 2 },
            bottomRightCorner: { x: x + 2, y: y + 2 }, bottomLeftCorner: { x: x - 2, y: y + 2 },
        });
        const resolver = (QrReader as any).orientationFromLocation.bind(QrReader);
        expect(resolver(location(90, 10), 100, 100)).toBe(0);
        expect(resolver(location(10, 10), 100, 100)).toBe(1);
        expect(resolver(location(10, 90), 100, 100)).toBe(2);
        expect(resolver(location(90, 90), 100, 100)).toBe(3);
        expect(() => resolver(location(50, 50), 100, 100)).toThrow();
    });

    it('rotates RGBA content before fiducial and ROI processing', () => {
        const source = new ImageData(new Uint8ClampedArray([
            1, 1, 1, 255, 2, 2, 2, 255,
            3, 3, 3, 255, 4, 4, 4, 255,
        ]), 2, 2);
        const values = (turns: 0 | 1 | 2 | 3) => {
            const result = QrReader.rotate(source, turns);
            return Array.from({ length: 4 }, (_, index) => result.data[index * 4]);
        };
        expect(values(1)).toEqual([3, 1, 4, 2]);
        expect(values(2)).toEqual([4, 3, 2, 1]);
        expect(values(3)).toEqual([2, 4, 1, 3]);
    });
});
