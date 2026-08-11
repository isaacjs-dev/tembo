import type { OmrResult, OmrTemplate } from './types';
// We use the ?worker suffix to tell Vite to bundle this as a Web Worker
import OmrWorker from './worker?worker';

export * from './types';
export * from './engine';

export interface ProcessOptions {
    opencvUrl?: string; // e.g. "/vendor/opencv/opencv-4.8.0.js"
    opencvFallbackUrls?: string[];
    debug?: boolean;
    templateData?: OmrTemplate;
}

export class OmrBrowserEngine {
    private worker: Worker;
    private initialized: boolean = false;
    private initPromise: Promise<void> | null = null;
    private messageIdCounter: number = 0;
    
    // Map of id -> { resolve, reject }
    private pendingRequests: Map<number, {
        resolve: Function,
        reject: Function,
        timeout: ReturnType<typeof setTimeout>,
    }> = new Map();

    constructor(options: ProcessOptions = {}) {
        this.worker = new OmrWorker();
        
        // Setup global listener
        this.worker.onmessage = (e) => this.handleWorkerMessage(e);
        this.worker.onerror = (e) => this.handleWorkerError(e);

        // Auto initialize
        this.initPromise = new Promise((resolve, reject) => {
            const id = ++this.messageIdCounter;
            this.registerRequest(id, resolve, reject, 30_000);
            
            this.worker.postMessage({
                type: 'INIT',
                id,
                payload: {
                    opencvUrl: options.opencvUrl || '/vendor/opencv/opencv-4.8.0.js',
                    opencvFallbackUrls: options.opencvFallbackUrls || [],
                    debug: options.debug || false
                }
            });
        });
    }

    private handleWorkerMessage(e: MessageEvent) {
        const { type, id, payload, error } = e.data;
        
        const req = this.pendingRequests.get(id);
        if (!req) return;
        clearTimeout(req.timeout);

        if (type === 'ERROR') {
            req.reject(new Error(error));
        } else if (type === 'INIT_DONE') {
            this.initialized = true;
            req.resolve();
        } else if (type === 'PROCESS_DONE') {
            req.resolve(payload);
        }

        this.pendingRequests.delete(id);
    }

    private handleWorkerError(e: ErrorEvent) {
        console.error("OMR Worker Error:", e.message);
        // Reject all pending requests
        this.pendingRequests.forEach(req => {
            clearTimeout(req.timeout);
            req.reject(new Error(e.message));
        });
        this.pendingRequests.clear();
    }

    private registerRequest(id: number, resolve: Function, reject: Function, timeoutMs: number) {
        const timeout = setTimeout(() => {
            const pending = this.pendingRequests.get(id);
            if (!pending) return;
            this.pendingRequests.delete(id);
            pending.reject(new Error('OMR processing timed out'));
        }, timeoutMs);
        this.pendingRequests.set(id, { resolve, reject, timeout });
    }

    /**
     * Ensure the engine is initialized and OpenCV is loaded
     */
    public async waitForInit(): Promise<void> {
        if (this.initPromise) {
            await this.initPromise;
        }
    }

    /**
     * Process an ImageData object (from a canvas) to grade the answer sheet
     */
    public async processImage(imageData: ImageData, templateData?: OmrTemplate): Promise<OmrResult & { qrData: any, debugImages?: any }> {
        await this.waitForInit();

        return new Promise((resolve, reject) => {
            const id = ++this.messageIdCounter;
            this.registerRequest(id, resolve, reject, 45_000);

            this.worker.postMessage({
                type: 'PROCESS',
                id,
                payload: { imageData, templateData }
            });
        });
    }

    public terminate() {
        if (this.worker) {
            this.worker.terminate();
        }
        this.pendingRequests.forEach((request) => {
            clearTimeout(request.timeout);
            request.reject(new Error('OMR worker terminated'));
        });
        this.pendingRequests.clear();
    }
}
