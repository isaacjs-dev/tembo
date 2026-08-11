import { OmrEngine } from '../../resources/js/omr-core/engine';

declare const cv: any;

type BrowserSample = {
    gray_base64: string;
    width: number;
    height: number;
    contract: { qs: number; qe: number; rpp: number; oc: string; g: number[]; template_version: number };
};

function decode(base64: string): Uint8Array {
    const binary = atob(base64);
    return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

(globalThis as any).TemboOmrDatasetWeb = {
    run(sample: BrowserSample, thresholds: Record<string, number>) {
        const gray = decode(sample.gray_base64);
        const rgba = new Uint8Array(sample.width * sample.height * 4);
        for (let index = 0; index < gray.length; index += 1) {
            const offset = index * 4;
            rgba[offset] = gray[index];
            rgba[offset + 1] = gray[index];
            rgba[offset + 2] = gray[index];
            rgba[offset + 3] = 255;
        }

        const mat = cv.matFromArray(sample.height, sample.width, cv.CV_8UC4, rgba);
        const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        const template = {
            questions: Array.from({ length: sample.contract.qe - sample.contract.qs + 1 }, (_, index) => ({
                question_number: sample.contract.qs + index,
                option_labels_json: labels.slice(0, Number(sample.contract.oc[index])),
            })),
            layout_meta: {
                v: 5,
                g: sample.contract.g,
                rpp: sample.contract.rpp,
                qs: sample.contract.qs,
                qe: sample.contract.qe,
                oc: sample.contract.oc,
                tpl_id: 1,
                tpl_v: sample.contract.template_version,
            },
        };

        try {
            const engine = new OmrEngine(false);
            const answers = engine.readBubbles(mat, template as any, thresholds);

            return answers.map((answer) => {
                const quality = engine.assessQuality(4, [answer]);
                const sorted = Object.entries(answer.scores).sort((left, right) => right[1] - left[1]);
                const marked = sorted.filter((entry) => entry[1] >= (thresholds.mark ?? 0.45));

                return {
                    position: answer.q,
                    status: answer.status === 'OK' ? 'answered' : answer.status === 'BLANK' ? 'blank' : answer.status === 'DOUBLE' ? 'multiple_marks' : 'ambiguous',
                    selected_index: answer.selected ? labels.indexOf(answer.selected) : null,
                    marked_indices: marked.map((entry) => labels.indexOf(entry[0])),
                    confidence: sorted[0]?.[1] ?? 0,
                    fill_ratios: labels.slice(0, Object.keys(answer.scores).length).map((label) => answer.scores[label] ?? 0),
                    action: quality.needs_review ? 'review' : 'accept',
                };
            });
        } finally {
            mat.delete();
        }
    },
};
