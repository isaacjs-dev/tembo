import type { AnswerResult, OmrTemplate, Point, Rect, OmrQualityInfo } from './types';

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
        candidates.sort((a, b) => b.area - a.area);

        if (candidates.length >= 4) {
            // Pegar os 4 maiores que parecem com as caixas predefinidas
            return this.orderCorners(candidates.slice(0, 4));
        }

        return [];
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
        let tUncLow = thresholds?.uncertain_low ?? 0.25;
        let tUncHigh = thresholds?.uncertain_high ?? 0.40;

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

        let lmeta = (template as any).layout_meta;
        if (lmeta && lmeta.g && lmeta.g.length >= 6) {
            startX = W * (lmeta.g[0] / 10000);
            startY = H * (lmeta.g[1] / 10000);
            colSpacing = W * (lmeta.g[2] / 10000);
            rowSpacing = H * (lmeta.g[3] / 10000);
            bubbleSize = W * (lmeta.g[4] / 10000);
            optionSpacing = W * (lmeta.g[5] / 10000);
        }
        if (lmeta && lmeta.rpp) {
            rPC = parseInt(lmeta.rpp);
        }

        for (let q of template.questions) {
            let num = q.question_number;
            let options = q.option_labels_json || [];

            let scores: Record<string, number> = {};
            let boxes: Record<string, Rect> = {};

            // Generate virtual ROIs
            let col = Math.floor((num - 1) / rPC);
            let row = (num - 1) % rPC;

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
            } else if (bestScore >= tUncLow) {
                // Marcação muito fraca / Rasurada
                if (bestScore <= tUncHigh) {
                    status = 'UNCERTAIN';
                    selected = bestOpt ?? null; // Retorna com ressalva
                } else if (secondScore >= tUncLow) {
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
    public assessQuality(cornersFound: number, answersStatus: AnswerResult[]): OmrQualityInfo {
        let issues: string[] = [];
        let needsReview = false;

        if (cornersFound < 4) {
            issues.push(`Cantos incompletos: ${cornersFound}`);
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
        }
        if (numUncertain > 0) {
            issues.push(`${numUncertain} resposta(s) duvidosas/fracas`);
            needsReview = true; // Força revisão se houver incerteza
        }

        return {
            corners_found: cornersFound,
            reprojection_error: 0.0, // Simplificação geométrica direta
            issues: issues,
            needs_review: needsReview
        };
    }
}
