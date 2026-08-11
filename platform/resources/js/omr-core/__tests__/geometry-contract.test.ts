import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import {
    parseCardPageGeometry,
    resolveCardPageGeometry,
    resolvePageLocalGridPosition,
    questionsForCardPage,
} from '../geometry-contract';

const fixturePath = fileURLToPath(
    new URL('../../../../../contracts/omr/card-page-geometry.v1.json', import.meta.url)
);
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));

describe('CardPageGeometry contract', () => {
    it.each(fixture.cases)('reads the PHP golden vector $name', (testCase) => {
        const contract = parseCardPageGeometry({
            g: testCase.expected.g,
            rpp: testCase.expected.rpp,
            qs: testCase.q_start,
            qe: testCase.expected.q_end,
            oc: testCase.option_counts,
            tpl_id: testCase.template_id,
            tpl_v: testCase.template_version,
        });

        expect(contract).not.toBeNull();
        const resolved = resolveCardPageGeometry(contract!, 1000, 1000);
        expect(resolved.startX).toBeCloseTo(testCase.expected.g[0] / 10, 8);
        expect(resolved.rowsPerColumn).toBe(testCase.expected.rpp);
        expect(resolvePageLocalGridPosition(testCase.q_start, testCase.q_start, contract!.rpp))
            .toEqual({ column: 0, row: 0 });
    });

    it('maps page two using a page-local index', () => {
        expect(resolvePageLocalGridPosition(31, 31, 15)).toEqual({ column: 0, row: 0 });
        expect(resolvePageLocalGridPosition(46, 31, 15)).toEqual({ column: 1, row: 0 });
    });

    it('rebuilds the signed page from empty or global template questions', () => {
        const contract = parseCardPageGeometry({
            g: [645, 606, 5000, 606, 226, 306],
            rpp: 15,
            qs: 31,
            qe: 33,
            oc: '520',
            tpl_id: 20,
            tpl_v: 7,
        })!;
        const global = Array.from({ length: 48 }, (_, index) => ({
            id: index + 100,
            question_number: index + 1,
            option_labels_json: ['legacy'],
        }));

        expect(questionsForCardPage([], contract).map((question) => question.question_number))
            .toEqual([31, 32, 33]);
        const normalized = questionsForCardPage(global, contract);
        expect(normalized.map((question) => question.option_labels_json.length)).toEqual([5, 2, 0]);
        expect(normalized[0].id).toBe(130);
        expect(normalized[2].type).toBe('essay');
    });

    it.each([
        { g: [1, 2, 3], rpp: 20, qs: 1, qe: 1, oc: '5', tpl_id: 1, tpl_v: 1 },
        { g: [1, 2, 3, 4, 5, 6], rpp: 0, qs: 1, qe: 1, oc: '5', tpl_id: 1, tpl_v: 1 },
        { g: [1, 2, 3, 4, 5, 6], rpp: 20, qs: 1, qe: 2, oc: '5', tpl_id: 1, tpl_v: 1 },
    ])('rejects malformed modern geometry %#', (value) => {
        expect(parseCardPageGeometry(value)).toBeNull();
    });
});
