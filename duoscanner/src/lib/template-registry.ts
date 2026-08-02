/**
 * Template Registry — OMR Layout Version Management
 *
 * Defines the physical dimensions (in mm) of each answer sheet layout version.
 * When the PDF template changes, increment layout_version and add a new entry here.
 * Old cards keep working because we always look up by their encoded version.
 *
 * Physical measurements based on pdf_advanced.blade.php:
 *   @page { size: A4; margin: 10mm; }
 *   A4 = 210mm × 297mm
 *   Usable area = 190mm × 277mm (after 10mm margins each side)
 *
 * Fiducials: 18px black squares in DOMPDF at 96dpi ≈ 4.76mm per side
 * The wrapper table is: max-width 700px centered in 906px (A4 @96dpi - margins)
 * 700px / 96 * 25.4 = 185.2mm wrapper width
 */

export interface BubbleSlot {
    /** Horizontal offset from left edge of bubble area in mm */
    xMm: number;
    /** Horizontal size of bubble circle in mm */
    widthMm: number;
}

export interface LayoutTemplate {
    version: number;

    /** Physical width of the answer-area bounding box (between fiducials), in mm */
    areaWidthMm: number;
    /** Physical height of the answer-area bounding box (between fiducials), in mm */
    areaHeightMm: number;

    /** Number of answer columns */
    numCols: number;
    /** Maximum question rows per column */
    rowsPerCol: number;

    /** Bubble diameter in mm */
    bubbleDiameterMm: number;

    /**
     * Column definitions: left edge of each column's bubble region, in mm from
     * the left edge of areaWidthMm.
     * Measured from pdf_advanced.blade.php proportions:
     *   wrapper=700px, marker-td=24px, td padding=5px each side
     *
     * ASSUNÇÃO A3: Valores medidos do PDF renderizado. Ajustar com régua física.
     */
    colBubbleStartMm: number[];
    colBubbleEndMm: number[];

    /** Y position of the first question row line from the top of the answer area, in mm */
    gridTopOffsetMm: number;
    /** Y position of the last question row line from the top of the answer area, in mm */
    gridBottomOffsetMm: number;

    /** Estimated extra height (mm) each discipline header row adds */
    disciplineHeaderHeightMm: number;

    /** Number of options per row (max; actual count from QR/template) */
    maxOptions: number;
}

/**
 * ASSUNÇÃO A3: Medidas derivadas do PDF com DOMPDF @96dpi para A4 com margin 10mm.
 * Fiduciais estão na borda da wrapper table:
 *   - Top row dos fiduciais: ~15.5% da altura da folha A4 visible
 *   - Bottom row: ~95% da altura visible
 *
 * Para confirmar: imprimir um cartão e medir com régua as posições dos 4 marcadores
 * e o espaçamento entre bolhas A–E de uma questão.
 */
const LAYOUT_V0: LayoutTemplate = {
    version: 0,
    areaWidthMm: 184.5,
    areaHeightMm: 153.4, // Base height, but varies up to 227.2mm based on questions

    numCols: 4,
    rowsPerCol: 15,
    maxOptions: 5,

    bubbleDiameterMm: 4.2,

    colBubbleStartMm: [18.9, 62.4, 106.0, 149.5],
    colBubbleEndMm: [43.5, 87.0, 130.6, 174.1],

    gridTopOffsetMm: 15.8,    // 15.8mm normal, or 22.6mm with discipline header
    gridBottomOffsetMm: 153.4, // Using the base bottom

    disciplineHeaderHeightMm: 6.8, // 22.6 - 15.8
};

/** v1: Same geometry as v0 — added metadata fields (chk, cols, rpp) in QR. */
const LAYOUT_V1: LayoutTemplate = {
    ...LAYOUT_V0,
    version: 1,
};

const REGISTRY: Record<number, LayoutTemplate> = {
    0: LAYOUT_V0,
    1: LAYOUT_V1,
};

/** Returns the layout template for a given version. Falls back to v0. */
export function getTemplate(version: number): LayoutTemplate {
    return REGISTRY[version] ?? REGISTRY[0];
}

/** Returns all registered layout versions */
export function getAllVersions(): number[] {
    return Object.keys(REGISTRY).map(Number).sort();
}
