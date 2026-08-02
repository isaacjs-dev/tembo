import { OmrEngine } from './engine';
import { QrReader } from './qr_reader';
import type { OmrTemplate } from './types';

// The global `cv` object will be populated by importScripts
declare var cv: any;

let engine: OmrEngine | null = null;
let cvInitialized = false;

// We use a Promise to wait for OpenCV to be ready
let cvReadyPromise: Promise<void> | null = null;

// Initialize the worker
self.onmessage = async (e: MessageEvent) => {
    const { type, payload, id } = e.data;

    try {
        if (type === 'INIT') {
            const { opencvUrl, debug } = payload;
            
            if (!cvInitialized) {
                cvReadyPromise = new Promise((resolve, reject) => {
                    // Define Module before loading opencv.js
                    (self as any).Module = {
                        onRuntimeInitialized: () => {
                            cvInitialized = true;
                            engine = new OmrEngine(debug || false);
                            resolve();
                        }
                    };
                    
                    try {
                        // Load OpenCV from the provided URL (e.g., CDN or local public path)
                        importScripts(opencvUrl || '/vendor/opencv/opencv-4.8.0.js');
                    } catch (err) {
                        reject(err);
                    }
                });
                
                await cvReadyPromise;
            }
            
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

            const { imageData, templateData } = payload;
            
            // 1. Read QR Code first to get template config and geometry
            const qrPayload = QrReader.read(imageData);
            if (!qrPayload) {
                throw new Error("QR Code not found or unreadable");
            }

            // The QR payload acts as the layout_meta
            const template: OmrTemplate = templateData || {
                template_id: qrPayload.tpl || 'auto',
                width: imageData.width,
                height: imageData.height,
                corners_json: { TL: {x:0,y:0}, TR: {x:0,y:0}, BR: {x:0,y:0}, BL: {x:0,y:0} }, // fake corners
                layout_meta: qrPayload, // We inject this for the engine to use dynamically
                questions: [] // Engine will rely on grid logic
            };
            
            // Reconstruct questions array from qrPayload so the engine knows what to grade.
            // `oc` traz a contagem REAL de opções por questão (V/F=2, dissertativa=0); assim o
            // motor lê só as bolhas existentes (V/F = só A e B) e não confunde C/D/E com ruído.
            if (!template.questions || template.questions.length === 0) {
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
            const quality = engine.assessQuality(corners.length, answers);

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
