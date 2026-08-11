import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { parseOmrQrPayload } from '../qr-contract';

const fixturePath = fileURLToPath(
    new URL('../../../../../contracts/omr/qr-contract.vectors.json', import.meta.url)
);
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));

describe('OMR QR wire contract', () => {
    it.each(fixture.vectors)('accepts the published $name vector', (vector) => {
        const parsed = parseOmrQrPayload(vector.payload);

        expect(parsed).not.toBeNull();
        expect(parsed?.v).toBe(vector.payload.v);
    });

    it.each(fixture.invalid_vectors)('rejects $name before the OMR engine', (vector) => {
        const current = fixture.vectors.find((item: { name: string }) => item.name === 'current-v5').payload;

        expect(parseOmrQrPayload({ ...current, ...vector.patch })).toBeNull();
    });

    it('rejects PII and numeric strings instead of coercing them', () => {
        const current = fixture.vectors.find((item: { name: string }) => item.name === 'current-v5').payload;

        expect(parseOmrQrPayload({ ...current, student_name: 'Aluno' })).toBeNull();
        expect(parseOmrQrPayload({ ...current, e: String(current.e) })).toBeNull();
    });
});
