import { OmrEngine } from './engine';
import { openCvUnavailableMessage, resolveOpenCvUrls } from './opencv-loader';
import { QrReader } from './qr_reader';
import { parseOmrQrPayload } from './qr-contract';
import type { AnswerResult, OmrProcessingEvidence, OmrQualityInfo, OmrTemplate } from './types';
import { parseCardPageGeometry, questionsForCardPage } from './geometry-contract';
import { analyzeRgbaImageQuality } from './image-quality';

// The global `cv` object will be populated by importScripts
declare var cv: any;

let engine: OmrEngine | null = null;
let cvInitialized = false;

// We use a Promise to wait for OpenCV to be ready
let cvReadyPromise: Promise<void> | null = null;

const workerScope = self as any;

function normalizedEvidence(
    answers: AnswerResult[],
    quality: OmrQualityInfo,
    geometry: { orientationDegrees: number, scaleRatio: number },
    reprojectionError: number,
): OmrProcessingEvidence {
    const questions = Object.fromEntries(answers.map((answer) => {
        const reasons = answer.status === 'DOUBLE'
            ? ['multiple_marks']
            : answer.status === 'UNCERTAIN' ? ['ambiguous_mark'] : [];
        const boxes = Object.values(answer.boxes ?? {});
        const left = boxes.length ? Math.min(...boxes.map((box) => box.x)) : 0;
        const top = boxes.length ? Math.min(...boxes.map((box) => box.y)) : 0;
        const right = boxes.length ? Math.max(...boxes.map((box) => box.x + box.width)) : 0;
        const bottom = boxes.length ? Math.max(...boxes.map((box) => box.y + box.height)) : 0;
        const ratios = Object.values(answer.scores);
        const sorted = [...ratios].sort((a, b) => b - a);
        const confidence = answer.status === 'OK'
            ? sorted[0] ?? 0
            : answer.status === 'BLANK' ? 1 - (sorted[0] ?? 0) : 0;
        return [String(answer.q), {
            action: reasons.length ? 'review' as const : 'accept' as const,
            reasons,
            fillRatios: ratios,
            confidence,
            roi: { x: left, y: top, w: Math.max(0, right - left), h: Math.max(0, bottom - top) },
        }];
    }));
    return {
        pipelineVersion: 'web-v2',
        processingPath: 'homography',
        action: quality.needs_review ? 'review' : 'accept',
        reasons: quality.needs_review ? ['question_review_required'] : [],
        imageQuality: quality.image_quality,
        geometry: {
            fiducialCount: quality.corners_found,
            reprojectionError: { rms: reprojectionError, max: reprojectionError },
            orientationDegrees: geometry.orientationDegrees,
            scaleRatio: geometry.scaleRatio,
        },
        questions,
    };
}

function isOpenCvReady(candidate: any): boolean {
    return Boolean(candidate && typeof candidate.Mat === 'function' && typeof candidate.matFromImageData === 'function');
}

async function waitForOpenCvRuntime(candidate: any): Promise<void> {
    if (isOpenCvReady(candidate)) return;

    await new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('OpenCV runtime initialization timed out')), 20_000);
        const previousCallback = candidate?.onRuntimeInitialized;

        candidate.onRuntimeInitialized = () => {
            try {
                if (typeof previousCallback === 'function') previousCallback();
                if (!isOpenCvReady(candidate)) {
                    throw new Error('OpenCV runtime initialized without the required APIs');
                }
                clearTimeout(timeout);
                resolve();
            } catch (error) {
                clearTimeout(timeout);
                reject(error);
            }
        };
    });
}

async function loadOpenCv(opencvUrl?: string, fallbackUrls: string[] = []): Promise<void> {
    if (isOpenCvReady(workerScope.cv)) return;

    const urls = resolveOpenCvUrls(opencvUrl, fallbackUrls, workerScope.location.href);
    const failures: string[] = [];

    for (const url of urls) {
        try {
            importScripts(url);
            await waitForOpenCvRuntime(workerScope.cv);
            return;
        } catch (error) {
            failures.push(`${url}: ${error instanceof Error ? error.message : String(error)}`);
        }
    }

    console.error('[OMR] OpenCV runtime could not be loaded', failures);
    throw new Error(openCvUnavailableMessage());
}

// Initialize the worker
self.onmessage = async (e: MessageEvent) => {
    const { type, payload, id } = e.data;

    try {
        if (type === 'INIT') {
            const { opencvUrl, opencvFallbackUrls, debug } = payload;

            if (!cvReadyPromise) {
                cvReadyPromise = new Promise((resolve, reject) => {
                    loadOpenCv(opencvUrl, opencvFallbackUrls || [])
                        .then(() => {
                            cvInitialized = true;
                            engine = new OmrEngine(debug || false);
                            resolve();
                        })
                        .catch(reject);
                });
            }

            await cvReadyPromise;
            
            self.postMessage({ type: 'INIT_DONE', id });
            return;
        }

        if (type === 'PROCESS') {
            if (!cvInitialized || !engine) {
                await cvReadyPromise;
                if (!cvInitialized || !engine) {
                    throw new Error("OpenCV not initialized");
                }
            }

            const { imageData: capturedImageData, templateData } = payload;
            const qrRead = QrReader.read(capturedImageData);
            if (!qrRead) {
                throw new Error("QR Code not found, unreadable, or without orientation");
            }
            const qrPayload = parseOmrQrPayload(qrRead.payload);
            if (!qrPayload) {
                throw new Error("QR Code not found or unreadable");
            }
            const imageData = QrReader.rotate(capturedImageData, qrRead.orientationQuarterTurns);
            const imageQuality = analyzeRgbaImageQuality(imageData.data, imageData.width, imageData.height);
            if (!imageQuality.acceptable) {
                throw new Error(`Image quality outside supported envelope: ${imageQuality.reasons.join(', ')}`);
            }

            // The QR payload acts as the layout_meta
            const template: OmrTemplate = templateData ? {
                ...templateData,
                layout_meta: qrPayload,
            } : {
                template_id: qrPayload.tpl || 'auto',
                width: imageData.width,
                height: imageData.height,
                corners_json: { TL: {x:0,y:0}, TR: {x:0,y:0}, BR: {x:0,y:0}, BL: {x:0,y:0} }, // fake corners
                layout_meta: qrPayload, // We inject this for the engine to use dynamically
                questions: [] // Engine will rely on grid logic
            };
            
            const signedGeometry = parseCardPageGeometry(qrPayload);
            if (qrPayload.v === 5 && !signedGeometry) {
                throw new Error('QR moderno sem geometria de página válida');
            }
            if (signedGeometry) {
                template.questions = questionsForCardPage(template.questions || [], signedGeometry);
            }

            // Reconstruct questions array from qrPayload so the engine knows what to grade.
            // `oc` traz a contagem REAL de opções por questão (V/F=2, dissertativa=0); assim o
            // motor lê só as bolhas existentes (V/F = só A e B) e não confunde C/D/E com ruído.
            if ((!template.questions || template.questions.length === 0) && qrPayload.qs && qrPayload.qe) {
                const totalQ = qrPayload.qe - qrPayload.qs + 1;
                const oc: string = typeof qrPayload.oc === 'string' ? qrPayload.oc : '';
                const ALL = ['A', 'B', 'C', 'D', 'E'];
                for (let i = 0; i < totalQ; i++) {
                    let n = 5; // legado (QR sem `oc`): assume 5 opções
                    if (oc && i < oc.length) {
                        const d = parseInt(oc[i], 10);
                        if (!isNaN(d)) n = d;
                    }
                    template.questions.push({
                        question_number: qrPayload.qs + i,
                        option_labels_json: ALL.slice(0, Math.max(0, Math.min(5, n))),
                        rois_json: {} // Engine uses dynamic geometric mapping
                    });
                }
            }

            // 2. Convert ImageData to cv.Mat
            const mat = cv.matFromImageData(imageData);

            const startTime = performance.now();

            // 3. Preprocess (gray = escala de cinza; thresh = binário p/ detectar marcadores)
            const { gray, thresh } = engine.preprocess(mat);

            // 4. Find corners (binário p/ contornos + cinza p/ confirmar quadrado sólido)
            const corners = engine.findCornerMarkers(thresh, gray);
            if (corners.length < 4) {
                mat.delete();
                gray.delete();
                thresh.delete();
                throw new Error("Could not find 4 fiducial markers. Found: " + corners.length);
            }

            // 5. Warp Perspective — usa a imagem em CINZA (não o binário), pois o
            // readBubbles binariza uma única vez. Warpar o binário e re-binarizar
            // zerava o preenchimento das bolhas (tudo lia como branco).
            const targetW = 2480; // Standard A4 300dpi width to map 10000-based ratios accurately
            const targetH = 3508;
            const geometryMetrics = engine.measureGeometry(corners, imageData.width);
            const reprojectionError = engine.reprojectionError(corners, targetW, targetH);
            if (!Number.isFinite(reprojectionError) || reprojectionError > 2
                || Math.abs(geometryMetrics.orientationDegrees) > 20
                || geometryMetrics.scaleRatio < 0.5 || geometryMetrics.scaleRatio > 1.05) {
                throw new Error('Geometria do cartão fora do envelope suportado');
            }
            const warped = engine.warp(gray, corners, targetW, targetH);

            // 6. Read Bubbles
            const thresholds = template.thresholds_json || {
                mark: 0.45,
                blank: 0.15,
                uncertain_low: 0.25,
                uncertain_high: 0.40
            };
            
            const answers = engine.readBubbles(warped, template, thresholds);

            // 7. Assess Quality
            const quality = engine.assessQuality(corners.length, answers, reprojectionError);
            quality.image_quality = imageQuality;
            const processingEvidence = normalizedEvidence(answers, quality, geometryMetrics, reprojectionError);

            const endTime = performance.now();

            // Cleanup
            mat.delete();
            gray.delete();
            thresh.delete();
            warped.delete();

            // Send back results
            self.postMessage({
                type: 'PROCESS_DONE',
                id,
                payload: {
                    template_id: template.template_id,
                    quality,
                    answers,
                    processingEvidence,
                    qrData: qrPayload,
                    processing_time_s: (endTime - startTime) / 1000,
                    debugImages: engine.getDebugImages() // Useful for UI overlay if needed
                }
            });
        }
    } catch (error: any) {
        self.postMessage({
            type: 'ERROR',
            id,
            error: error.message || String(error)
        });
    }
};
