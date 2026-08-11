export interface CardPageGeometryContract {
    g: [number, number, number, number, number, number];
    rpp: number;
    qs: number;
    qe: number;
    oc: string;
    tpl_id: number;
    tpl_v: number;
}

export interface ResolvedGeometry {
    startX: number;
    startY: number;
    columnSpacing: number;
    rowSpacing: number;
    bubbleSize: number;
    optionSpacing: number;
    rowsPerColumn: number;
    questionStart: number;
}

const positiveInteger = (value: unknown): value is number =>
    typeof value === 'number' && Number.isInteger(value) && value > 0;

export function isValidGeometryVector(value: unknown): value is CardPageGeometryContract['g'] {
    if (!Array.isArray(value) || value.length !== 6) return false;
    if (!value.every((item) => Number.isInteger(item) && item >= 0 && item <= 10000)) return false;

    const [startX, startY, columnSpacing, rowSpacing, bubbleSize, optionSpacing] = value;

    return columnSpacing > 0
        && rowSpacing > 0
        && bubbleSize > 0
        && optionSpacing > 0
        && startX + bubbleSize <= 10000
        && startY + bubbleSize <= 10000;
}

export function parseCardPageGeometry(value: unknown): CardPageGeometryContract | null {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const raw = value as Record<string, unknown>;
    if (!isValidGeometryVector(raw.g)) return null;
    if (!positiveInteger(raw.rpp) || !positiveInteger(raw.qs) || !positiveInteger(raw.qe)) return null;
    if (!positiveInteger(raw.tpl_id) || !positiveInteger(raw.tpl_v)) return null;
    if (raw.qe < raw.qs) return null;
    if (typeof raw.oc !== 'string' || !/^(?:0|[2-9])+$/.test(raw.oc)) return null;
    if (raw.oc.length !== raw.qe - raw.qs + 1) return null;

    const g = raw.g;
    const questionCount = raw.qe - raw.qs + 1;
    const columns = Math.ceil(questionCount / raw.rpp);
    const rows = Math.min(questionCount, raw.rpp);
    const maxOptions = Math.max(...raw.oc.split('').map(Number));
    if (g[0] + ((columns - 1) * g[2]) + (Math.max(0, maxOptions - 1) * g[5]) + g[4] > 10000) return null;
    if (g[1] + ((rows - 1) * g[3]) + g[4] > 10000) return null;

    return {
        g: g as CardPageGeometryContract['g'],
        rpp: raw.rpp,
        qs: raw.qs,
        qe: raw.qe,
        oc: raw.oc,
        tpl_id: raw.tpl_id,
        tpl_v: raw.tpl_v,
    };
}

export function resolveCardPageGeometry(
    contract: CardPageGeometryContract,
    width: number,
    height: number
): ResolvedGeometry {
    const [startX, startY, columnSpacing, rowSpacing, bubbleSize, optionSpacing] = contract.g;

    return {
        startX: width * (startX / 10000),
        startY: height * (startY / 10000),
        columnSpacing: width * (columnSpacing / 10000),
        rowSpacing: height * (rowSpacing / 10000),
        bubbleSize: width * (bubbleSize / 10000),
        optionSpacing: width * (optionSpacing / 10000),
        rowsPerColumn: contract.rpp,
        questionStart: contract.qs,
    };
}

export function resolvePageLocalGridPosition(
    printedQuestionNumber: number,
    questionStart: number,
    rowsPerColumn: number
): { column: number; row: number } {
    const localIndex = printedQuestionNumber - questionStart;
    if (!Number.isInteger(localIndex) || localIndex < 0 || !positiveInteger(rowsPerColumn)) {
        throw new Error('Questão fora da geometria assinada desta página.');
    }

    return {
        column: Math.floor(localIndex / rowsPerColumn),
        row: localIndex % rowsPerColumn,
    };
}

export function questionsForCardPage<T extends Record<string, unknown>>(
    existingQuestions: T[],
    contract: CardPageGeometryContract
): Array<T & { id: unknown; question_number: number; type: string; option_labels_json: string[]; rois_json: object }> {
    const byNumber = new Map(existingQuestions.map((question) => [Number(question.question_number), question]));
    const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    return Array.from({ length: contract.qe - contract.qs + 1 }, (_, index) => {
        const questionNumber = contract.qs + index;
        const optionCount = Number(contract.oc[index]);
        const existing = byNumber.get(questionNumber) ?? ({} as T);

        return {
            ...existing,
            id: existing.id ?? questionNumber,
            question_number: questionNumber,
            type: optionCount === 0 ? 'essay' : (optionCount === 2 ? 'true_false' : 'multiple_choice'),
            option_labels_json: labels.slice(0, optionCount),
            rois_json: {},
        };
    });
}
