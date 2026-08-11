import { describe, it, expect, beforeEach, vi } from 'vitest';
import { OmrEngine } from '../engine';

describe('OmrEngine Unit Tests', () => {
    let engine: OmrEngine;

    beforeEach(() => {
        engine = new OmrEngine(false);
        // Mock global cv object as it's required by OmrEngine but not loaded in Node.js test environment
        global.cv = {
            Mat: vi.fn().mockImplementation(function() {
                return {
                    clone: vi.fn(),
                    delete: vi.fn(),
                    type: vi.fn().mockReturnValue(16), // CV_8UC3
                    channels: vi.fn().mockReturnValue(3),
                    copyTo: vi.fn(),
                    cols: 1000,
                    rows: 1000,
                    roi: vi.fn().mockImplementation(() => ({ delete: vi.fn() })),
                };
            }),
            MatVector: vi.fn().mockImplementation(function() {
                return {
                    delete: vi.fn(),
                    size: vi.fn().mockReturnValue(0),
                    get: vi.fn()
                };
            }),
            Size: vi.fn(),
            Scalar: vi.fn(),
            Rect: vi.fn(),
            cvtColor: vi.fn(),
            GaussianBlur: vi.fn(),
            adaptiveThreshold: vi.fn(),
            threshold: vi.fn(),
            findContours: vi.fn(),
            contourArea: vi.fn().mockReturnValue(0),
            arcLength: vi.fn().mockReturnValue(0),
            approxPolyDP: vi.fn(),
            boundingRect: vi.fn().mockReturnValue({ width: 10, height: 10 }),
            moments: vi.fn().mockReturnValue({ m00: 0, m10: 0, m01: 0 }),
            matFromArray: vi.fn().mockImplementation(() => ({ delete: vi.fn() })),
            getPerspectiveTransform: vi.fn().mockImplementation(() => ({ delete: vi.fn() })),
            warpPerspective: vi.fn(),
            countNonZero: vi.fn().mockReturnValue(0),
            COLOR_RGBA2GRAY: 11,
            COLOR_RGB2GRAY: 7,
            BORDER_DEFAULT: 4,
            BORDER_CONSTANT: 0,
            ADAPTIVE_THRESH_GAUSSIAN_C: 1,
            THRESH_BINARY_INV: 1,
            THRESH_OTSU: 8,
            RETR_LIST: 0,
            CHAIN_APPROX_SIMPLE: 2,
            INTER_LINEAR: 1,
            CV_32FC2: 13,
            CV_8UC4: 24
        };
    });

    describe('orderCorners', () => {
        it('should correctly order 4 unordered corners into TL, TR, BR, BL', () => {
            const points = [
                { x: 100, y: 900, area: 10 }, // BL
                { x: 100, y: 100, area: 10 }, // TL
                { x: 900, y: 900, area: 10 }, // BR
                { x: 900, y: 100, area: 10 }  // TR
            ];

            const ordered = engine.orderCorners(points);
            
            // Expected: Top-Left, Top-Right, Bottom-Right, Bottom-Left
            expect(ordered[0]).toEqual({ x: 100, y: 100, area: 10 }); // TL
            expect(ordered[1]).toEqual({ x: 900, y: 100, area: 10 }); // TR
            expect(ordered[2]).toEqual({ x: 900, y: 900, area: 10 }); // BR
            expect(ordered[3]).toEqual({ x: 100, y: 900, area: 10 }); // BL
        });

        it('should return original array if points length is not 4', () => {
            const points = [{ x: 10, y: 10 }];
            const ordered = engine.orderCorners(points as any);
            expect(ordered).toEqual(points);
        });
    });

    describe('assessQuality', () => {
        it('should require review if corners found are less than 4', () => {
            const quality = engine.assessQuality(3, []);
            expect(quality.needs_review).toBe(true);
            expect(quality.issues).toContain('Cantos incompletos: 3');
        });

        it('should require review if there are uncertain answers', () => {
            const answers = [
                { q: 1, selected: 'A', status: 'OK', scores: {}, boxes: {} },
                { q: 2, selected: null, status: 'UNCERTAIN', scores: {}, boxes: {} }
            ] as any;
            const quality = engine.assessQuality(4, answers);
            expect(quality.needs_review).toBe(true);
            expect(quality.issues).toContain('1 resposta(s) duvidosas/fracas');
        });

        it('should detect double marked answers but not strictly require review if no uncertain marks exist', () => {
            const answers = [
                { q: 1, selected: null, status: 'DOUBLE', scores: {}, boxes: {} }
            ] as any;
            const quality = engine.assessQuality(4, answers);
            expect(quality.needs_review).toBe(false); // Double marks are often just "student made a mistake", unless we configure strict review.
            expect(quality.issues).toContain('1 dupla(s) marcação');
        });

        it('should pass quality assessment if all 4 corners are found and no answers have issues', () => {
            const answers = [
                { q: 1, selected: 'A', status: 'OK', scores: {}, boxes: {} },
                { q: 2, selected: 'B', status: 'OK', scores: {}, boxes: {} }
            ] as any;
            const quality = engine.assessQuality(4, answers);
            expect(quality.needs_review).toBe(false);
            expect(quality.issues.length).toBe(0);
        });
    });

    describe('readBubbles thresholding logic', () => {
        it('should correctly classify answers based on mocked non-zero pixel counts', () => {
            // We'll mock the countNonZero to simulate bubble fullness
            // A bolha geométrica tem 20x20; o motor mede somente o interior
            // (inset de 15%), resultando em uma ROI de 14x14 = 196 pixels.
            const template = {
                id: 1,
                name: 'Test Template',
                questions: [
                    { id: 1, question_number: 1, type: 'multiple_choice', option_labels_json: ['A', 'B'] }
                ],
                layout_meta: {
                    v: 5,
                    g: [1000, 1000, 2000, 500, 200, 280],
                    rpp: 15,
                    qs: 1,
                    qe: 1,
                    oc: '2',
                    tpl_id: 1,
                    tpl_v: 1,
                } // Proportions scaled to 10000
            } as any;

            // Intercept countNonZero
            let callIndex = 0;
            global.cv.countNonZero = vi.fn().mockImplementation(() => {
                callIndex++;
                // Option A (callIndex 1): full mark (e.g. 50% dark) -> 0.50 score -> > 0.45
                // Option B (callIndex 2): blank mark -> 0.05 score
                // total pixels medidos = 14 * 14 = 196
                if (callIndex === 1) return 118; // 118 / 196 ~= 0.60 (marked)
                if (callIndex === 2) return 10;  // 10 / 196 ~= 0.05 (blank)
                return 0;
            });

            const mockWarped = new global.cv.Mat();
            const thresholds = { mark: 0.45, blank: 0.15, uncertain_low: 0.25, uncertain_high: 0.40 };
            
            const results = engine.readBubbles(mockWarped, template, thresholds);
            
            expect(results.length).toBe(1);
            expect(results[0].q).toBe(1);
            expect(results[0].selected).toBe('A');
            expect(results[0].status).toBe('OK');
            expect(results[0].scores['A']).toBeCloseTo(0.6);
            expect(results[0].scores['B']).toBeCloseTo(0.05);
        });

        it('should classify as UNCERTAIN if non-zero count is within uncertainty threshold', () => {
             const template = {
                questions: [{ question_number: 1, option_labels_json: ['A'] }],
                layout_meta: {
                    v: 5,
                    g: [1000, 1000, 2000, 500, 200, 280],
                    rpp: 15,
                    qs: 1,
                    qe: 1,
                    oc: '1',
                    tpl_id: 1,
                    tpl_v: 1,
                }
            } as any;

            // 59 / 196 ~= 0.30 -> > 0.25 and < 0.40
            global.cv.countNonZero = vi.fn().mockReturnValue(59);

            const mockWarped = new global.cv.Mat();
            const results = engine.readBubbles(mockWarped, template, null); // Using default thresholds
            
            expect(results[0].status).toBe('UNCERTAIN');
            expect(results[0].selected).toBe('A'); // the best guess, even if uncertain
        });

        it('filters a global template to the signed second-page range', () => {
            const template = {
                questions: Array.from({ length: 48 }, (_, index) => ({
                    question_number: index + 1,
                    option_labels_json: ['A'],
                })),
                layout_meta: {
                    v: 5,
                    g: [645, 606, 5000, 606, 226, 306],
                    rpp: 15,
                    qs: 31,
                    qe: 48,
                    oc: '111111111111111111',
                    tpl_id: 20,
                    tpl_v: 7,
                },
            } as any;
            global.cv.countNonZero = vi.fn().mockReturnValue(0);

            const results = engine.readBubbles(new global.cv.Mat(), template, null);

            expect(results).toHaveLength(18);
            expect(results[0].q).toBe(31);
            expect(results.at(-1)?.q).toBe(48);
        });
    });
});
