import type { AnswerResult, OmrTemplate, Point, Rect, OmrQualityInfo } from './types';
import {
    parseCardPageGeometry,
    resolveCardPageGeometry,
    resolvePageLocalGridPosition,
} from './geometry-contract';

// OMR Core Engine — Algoritmo Principal

// Declarar cv globally (OpenCV.js vai providenciar isso no enviroment que carregar)
declare var cv: any;

export class OmrEngine {
    private debugMode: boolean = false;
    private debugImages: Record<string, any> = {};

    constructor(debug: boolean = false) {
        this.debugMode = debug;
    }

    public getDebugImages() {
        return this.debugImages;
    }

    private saveDebug(name: string, mat: any) {
        if (this.debugMode && mat) {
            this.debugImages[name] = mat.clone();
        }
    }

    /**
     * Pré-processamento: Gray -> Blur -> Threshold
     */
    public preprocess(mat: any): any {
        let gray = new cv.Mat();
        if (mat.type() === cv.CV_8UC4 || mat.channels() === 4) {
            cv.cvtColor(mat, gray, cv.COLOR_RGBA2GRAY);
        } else if (mat.channels() === 3) {
            cv.cvtColor(mat, gray, cv.COLOR_RGB2GRAY);
        } else {
            mat.copyTo(gray);
        }

        this.saveDebug('01_gray', gray);

        let blurred = new cv.Mat();
        let ksize = new cv.Size(5, 5);
        cv.GaussianBlur(gray, blurred, ksize, 0, 0, cv.BORDER_DEFAULT);

        let thresh = new cv.Mat();
        // Adaptive threshold funciona melhor em condições variáveis de iluminação
        cv.adaptiveThreshold(blurred, thresh, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY_INV, 51, 15);
        this.saveDebug('02_thresh', thresh);

        blurred.delete();
        return { gray, thresh };
    }

    /**
     * Encontrar 4 marcadores fiduciais pretos. `threshMat` (binário) localiza os contornos
     * quadrados; `grayMat` (escala de cinza, opcional) confirma que o quadrado é SÓLIDO
     * escuro — assim o QR Code e seus finder patterns (muito branco interno) são descartados.
     */
    public findCornerMarkers(threshMat: any, grayMat?: any): Point[] {
        let contours = new cv.MatVector();
        let hierarchy = new cv.Mat();
        cv.findContours(threshMat, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

        let imgArea = threshMat.rows * threshMat.cols;
        let candidates = [];

        for (let i = 0; i < contours.size(); ++i) {
            let cnt = contours.get(i);
            let area = cv.contourArea(cnt);

            // Ignorar muito pequenos ou muito grandes
            if (area > imgArea * 0.05 || area < imgArea * 0.0001) {
                continue;
            }

            let peri = cv.arcLength(cnt, true);
            let approx = new cv.Mat();
            cv.approxPolyDP(cnt, approx, 0.04 * peri, true);

            if (approx.rows === 4) {
                let rect = cv.boundingRect(approx);
                let aspect = rect.width / rect.height;
                // Quadrados têm aspect ratio próximo de 1
                if (aspect >= 0.7 && aspect <= 1.3) {
                    // SOLIDEZ medida na escala de CINZA (não no thresh adaptativo, que mostra
                    // quadrados sólidos só como CONTORNO). O marcador é um quadrado quase todo
                    // ESCURO; o QR Code e seus finder patterns têm muito branco interno
                    // (fração escura ~0.5–0.7), então são descartados. Sem isto, a região do
                    // QR (grande e quadrada) vira "marcador" e destrói a homografia/warp.
                    let accept = true;
                    if (grayMat) {
                        let grayRoi = grayMat.roi(rect);
                        let binRoi = new cv.Mat();
                        cv.threshold(grayRoi, binRoi, 100, 255, cv.THRESH_BINARY_INV); // escuro -> 255
                        let darkFrac = cv.countNonZero(binRoi) / (rect.width * rect.height);
                        grayRoi.delete();
                        binRoi.delete();
                        accept = darkFrac >= 0.70;
                    }
                    if (accept) {
                        let M = cv.moments(cnt);
                        if (M.m00 !== 0) {
                            candidates.push({ x: M.m10 / M.m00, y: M.m01 / M.m00, area: area });
                        }
                    }
                }
            }
            approx.delete();
        }

        contours.delete();
        hierarchy.delete();

        // Ordenar candidatos por área decrescente
        return this.selectCornerMarkers(candidates, threshMat.cols, threshMat.rows);
    }

    public selectCornerMarkers(candidates: Array<Point & { area?: number }>, width: number, height: number): Point[] {
        const zones = [
            (point: Point) => point.x < width * 0.45 && point.y < height * 0.45,
            (point: Point) => point.x > width * 0.55 && point.y < height * 0.45,
            (point: Point) => point.x > width * 0.55 && point.y > height * 0.55,
            (point: Point) => point.x < width * 0.45 && point.y > height * 0.55,
        ];
        const selected = zones.map((matches) => candidates
            .filter(matches)
            .sort((left, right) => (right.area ?? 0) - (left.area ?? 0))[0]);
        if (selected.some((point) => !point)) return [];
        const points = selected as Point[];
        let doubleArea = 0;
        for (let index = 0; index < points.length; index++) {
            const current = points[index];
            const next = points[(index + 1) % points.length];
            doubleArea += current.x * next.y - next.x * current.y;
        }
        return Math.abs(doubleArea) / 2 >= width * height * 0.20 ? points : [];
    }

    public measureGeometry(corners: Point[], imageWidth: number): { orientationDegrees: number, scaleRatio: number } {
        const topWidth = Math.hypot(corners[1].x - corners[0].x, corners[1].y - corners[0].y);
        const bottomWidth = Math.hypot(corners[2].x - corners[3].x, corners[2].y - corners[3].y);
        return {
            orientationDegrees: Math.atan2(corners[1].y - corners[0].y, corners[1].x - corners[0].x) * 180 / Math.PI,
            scaleRatio: ((topWidth + bottomWidth) / 2) / imageWidth,
        };
    }

    public reprojectionError(sourceCorners: Point[], targetWidth: number, targetHeight: number): number {
        const src = cv.matFromArray(4, 1, cv.CV_32FC2, sourceCorners.flatMap((point) => [point.x, point.y]));
        const destinations = [
            { x: 0, y: 0 }, { x: targetWidth - 1, y: 0 },
            { x: targetWidth - 1, y: targetHeight - 1 }, { x: 0, y: targetHeight - 1 },
        ];
        const dst = cv.matFromArray(4, 1, cv.CV_32FC2, destinations.flatMap((point) => [point.x, point.y]));
        const matrix = cv.getPerspectiveTransform(src, dst);
        const values = matrix.data64F?.length >= 9 ? matrix.data64F : matrix.data32F;
        let error = Number.POSITIVE_INFINITY;
        if (values?.length >= 9) {
            error = Math.max(...sourceCorners.map((point, index) => {
                const denominator = values[6] * point.x + values[7] * point.y + values[8];
                const x = (values[0] * point.x + values[1] * point.y + values[2]) / denominator;
                const y = (values[3] * point.x + values[4] * point.y + values[5]) / denominator;
                return Math.hypot(x - destinations[index].x, y - destinations[index].y);
            }));
        }
        src.delete();
        dst.delete();
        matrix.delete();
        return error;
    }

    /**
     * Ordenar pontos em: Top-Left, Top-Right, Bottom-Right, Bottom-Left
     */
    public orderCorners(pts: Point[]): Point[] {
        if (pts.length !== 4) return pts;

        // Sort by Y first
        let sortedY = [...pts].sort((a, b) => a.y - b.y);
        let top = sortedY.slice(0, 2);
        let bottom = sortedY.slice(2, 4);

        // Sort Top by X
        top.sort((a, b) => a.x - b.x);
        let tl = top[0];
        let tr = top[1];

        // Sort Bottom by X
        bottom.sort((a, b) => a.x - b.x);
        let bl = bottom[0];
        let br = bottom[1];

        return [tl, tr, br, bl];
    }

    /**
     * Extrair o gabarito transformado para o espaço geométrico do Template usando homografia
     */
    public warp(mat: any, sourceCorners: { x: number, y: number }[], tWidth: number, tHeight: number, dbCorners?: any): any {
        if (!sourceCorners || sourceCorners.length !== 4) throw new Error("Four corners required for warping");

        // Safely check undefined points
        for (let i = 0; i < 4; i++) {
            let pt = sourceCorners[i];
            if (!pt || typeof pt.x === 'undefined' || typeof pt.y === 'undefined') {
                throw new Error(`Corner ${i} is missing or has no X/Y`);
            }
        }

        // Obter os 4 cantos arrastados pelo usuário
        let p0 = sourceCorners[0];
        let p1 = sourceCorners[1];
        let p2 = sourceCorners[2];
        let p3 = sourceCorners[3];

        let srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            p0.x, p0.y,
            p1.x, p1.y,
            p2.x, p2.y,
            p3.x, p3.y
        ]);

        // Compute dynamic target dimensions based on distance between points
        let w1 = Math.hypot(p0.x - p1.x, p0.y - p1.y);
        let w2 = Math.hypot(p3.x - p2.x, p3.y - p2.y);
        let targetW = Math.max(w1, w2);

        let h1 = Math.hypot(p0.x - p3.x, p0.y - p3.y);
        let h2 = Math.hypot(p1.x - p2.x, p1.y - p2.y);
        let targetH = Math.max(h1, h2);

        // Destination points are literally the corners of the new image itself
        let dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            0, 0,
            targetW, 0,
            targetW, targetH,
            0, targetH
        ]);

        let M = cv.getPerspectiveTransform(srcTri, dstTri);
        let warped = new cv.Mat();
        let dsize = new cv.Size(targetW, targetH);

        // Use BORDER_CONSTANT with white background padding if needed
        let scalar = new cv.Scalar(255, 255, 255, 255);
        cv.warpPerspective(mat, warped, M, dsize, cv.INTER_LINEAR, cv.BORDER_CONSTANT, scalar);

        srcTri.delete();
        dstTri.delete();
        M.delete();

        this.saveDebug('05_warped', warped);
        return warped;
    }

    /**
     * Ler as bolhas a partir da imagem alinhada e transformada em Preto e Branco
     */
    public readBubbles(warped: any, template: OmrTemplate, thresholds: any): AnswerResult[] {
        // Garantir que a imagem warpada seja binária invertida
        let gray = new cv.Mat();
        if (warped.channels() > 1) {
            cv.cvtColor(warped, gray, cv.COLOR_RGBA2GRAY);
        } else {
            warped.copyTo(gray);
        }

        let thresh = new cv.Mat();
        // Threshold GLOBAL (Otsu) para MEDIR PREENCHIMENTO: caneta escura -> 255,
        // papel branco -> 0. (adaptiveThreshold realça bordas e não áreas sólidas,
        // dando leitura instável — bolha cheia podia ler como vazia.)
        cv.threshold(gray, thresh, 0, 255, cv.THRESH_BINARY_INV | cv.THRESH_OTSU);
        this.saveDebug('06_warped_thresh', thresh);

        let results: AnswerResult[] = [];
        let tMark = thresholds?.mark ?? 0.45;
        let tBlank = thresholds?.blank ?? 0.15;

        // Dynamic proportions mapped entirely to the internal 700px Marker bounding box
        let W = thresh.cols;
        let H = thresh.rows;

        let startX = W * 0.0868;
        let startY = H * 0.1190;
        let colSpacing = W * 0.2390;
        let rowSpacing = H * 0.0538;
        let bubbleSize = W * 0.0212;
        let optionSpacing = W * 0.0285;

        let rPC = 15; // DOMPDF physically groups in tr/td of 15 items always

        const lmeta = template.layout_meta as Record<string, unknown> | undefined;
        const signedGeometry = parseCardPageGeometry(lmeta);
        if (signedGeometry) {
            const resolved = resolveCardPageGeometry(signedGeometry, W, H);
            startX = resolved.startX;
            startY = resolved.startY;
            colSpacing = resolved.columnSpacing;
            rowSpacing = resolved.rowSpacing;
            bubbleSize = resolved.bubbleSize;
            optionSpacing = resolved.optionSpacing;
            rPC = resolved.rowsPerColumn;
        } else if (lmeta && (lmeta.v === 4 || lmeta.v === 5)) {
            throw new Error('QR moderno sem geometria de página válida.');
        }

        for (let q of template.questions) {
            let num = q.question_number;
            if (signedGeometry && (num < signedGeometry.qs || num > signedGeometry.qe)) {
                continue;
            }
            let options = q.option_labels_json || [];

            let scores: Record<string, number> = {};
            let boxes: Record<string, Rect> = {};

            // Generate virtual ROIs
            const position = signedGeometry
                ? resolvePageLocalGridPosition(num, signedGeometry.qs, rPC)
                : { column: Math.floor((num - 1) / rPC), row: (num - 1) % rPC };
            let col = position.column;
            let row = position.row;

            let bX = startX + (col * colSpacing);
            let bY = startY + (row * rowSpacing);

            // Para cada alternativa da questão
            for (let i = 0; i < options.length; i++) {
                let opt = options[i];

                // Amostra o INTERIOR da bolha (encolhe ~15%) p/ não pegar o anel da borda:
                // bolha vazia -> interior branco (~0); bolha cheia -> escuro (~1).
                let inset = bubbleSize * 0.15;
                let rX = Math.round(bX + (i * optionSpacing) + inset);
                let rY = Math.round(bY + inset);
                let rwVal = Math.round(bubbleSize - 2 * inset);
                let rhVal = Math.round(bubbleSize - 2 * inset);

                boxes[opt] = { x: rX, y: rY, width: rwVal, height: rhVal } as Rect;

                // Prevenir access violations
                rX = Math.max(0, rX);
                rY = Math.max(0, rY);

                let rW = Math.min(thresh.cols - rX, rwVal);
                let rH = Math.min(thresh.rows - rY, rhVal);

                if (rW <= 0 || rH <= 0) {
                    scores[opt] = 0;
                    continue;
                }

                let roiRect = new cv.Rect(rX, rY, rW, rH);
                let roiMat = thresh.roi(roiRect);
                let nonZero = cv.countNonZero(roiMat);
                let totalPixels = rW * rH;

                scores[opt] = nonZero / totalPixels;
                roiMat.delete();
            }

            // Classificar essa questão
            let sortedOpts = Object.keys(scores).sort((a, b) => scores[b] - scores[a]);
            let bestOpt = sortedOpts[0] ?? null;
            let bestScore = bestOpt ? (scores[bestOpt] ?? 0) : 0;
            let secondOpt = sortedOpts.length > 1 ? sortedOpts[1] : null;
            let secondScore = secondOpt ? (scores[secondOpt] ?? 0) : 0;

            let selected: string | null = null;
            let status: AnswerResult['status'] = 'BLANK';

            if (bestScore >= tMark) {
                // Ao menos um marcado forte
                if (secondScore >= tMark) {
                    status = 'DOUBLE';
                } else {
                    selected = bestOpt ?? null;
                    status = 'OK';
                }
            } else if (bestScore > tBlank) {
                // Marcação muito fraca / Rasurada
                if (secondScore <= tBlank) {
                    status = 'UNCERTAIN';
                    selected = bestOpt ?? null; // Retorna com ressalva
                } else if (secondScore > tBlank) {
                    status = 'DOUBLE'; // Rasura borradas em 2
                } else {
                    status = 'OK'; // Aceita como fraca mas única
                    selected = bestOpt ?? null;
                }
            } else {
                status = 'BLANK';
            }

            results.push({
                q: num,
                selected: selected,
                status: status,
                scores: scores,
                boxes: boxes
            });
        }

        gray.delete();
        thresh.delete();

        return results;
    }

    /**
     * Calcula métricas de qualidade globais e define se precisa de revisão manual
     */
    public assessQuality(cornersFound: number, answersStatus: AnswerResult[], reprojectionError: number): OmrQualityInfo {
        let issues: string[] = [];
        let needsReview = false;

        if (cornersFound < 4) {
            issues.push(`Cantos incompletos: ${cornersFound}`);
            needsReview = true;
        }
        if (!Number.isFinite(reprojectionError) || reprojectionError > 2) {
            issues.push('Homografia fora da tolerância');
            needsReview = true;
        }

        let numDouble = 0;
        let numUncertain = 0;

        for (let a of answersStatus) {
            if (a.status === 'DOUBLE') numDouble++;
            if (a.status === 'UNCERTAIN') numUncertain++;
        }

        if (numDouble > 0) {
            issues.push(`${numDouble} dupla(s) marcação`);
            needsReview = true;
        }
        if (numUncertain > 0) {
            issues.push(`${numUncertain} resposta(s) duvidosas/fracas`);
            needsReview = true; // Força revisão se houver incerteza
        }

        return {
            corners_found: cornersFound,
            reprojection_error: reprojectionError,
            issues: issues,
            needs_review: needsReview
        };
    }
}
