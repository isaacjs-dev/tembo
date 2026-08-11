export interface Point {
    x: number;
    y: number;
}

export interface Size {
    width: number;
    height: number;
}

export interface Rect {
    x: number;
    y: number;
    width: number;
    height: number;
}

export interface OmrTemplate {
    template_id: string;
    width: number;
    height: number;
    corners_json: {
        TL: Point;
        TR: Point;
        BR: Point;
        BL: Point;
    };
    thresholds_json?: {
        mark?: number;
        blank?: number;
        uncertain_low?: number;
        uncertain_high?: number;
    };
    questions: OmrQuestionDef[];
    layout_meta?: unknown;
}

export interface OmrQuestionDef {
    question_number: number;
    option_labels_json: string[]; // ["A", "B", "C", "D"]
    rois_json: Record<string, Rect>; // {"A": {x, y, width, height}}
}

export interface OmrQualityInfo {
    corners_found: number;
    reprojection_error: number;
    issues: string[];
    needs_review: boolean;
    image_quality?: {
        brightness: number;
        contrast: number;
        laplacianVariance: number;
        acceptable: boolean;
        reasons: string[];
    };
}

export interface OmrProcessingEvidence {
    pipelineVersion: 'web-v2';
    processingPath: 'homography';
    action: 'accept' | 'review' | 'rescan';
    reasons: string[];
    imageQuality: OmrQualityInfo['image_quality'];
    geometry: {
        fiducialCount: number;
        reprojectionError: { rms: number; max: number };
        orientationDegrees: number;
        scaleRatio: number;
    };
    questions: Record<string, {
        action: 'accept' | 'review';
        reasons: string[];
        fillRatios: number[];
        confidence: number;
        roi: { x: number; y: number; w: number; h: number };
    }>;
}

export interface AnswerResult {
    q: number;
    selected: string | null;
    status: 'OK' | 'BLANK' | 'DOUBLE' | 'UNCERTAIN';
    scores: Record<string, number>;
    boxes?: Record<string, Rect>;
}

export interface OmrResult {
    template_id?: string;
    quality: OmrQualityInfo;
    answers: AnswerResult[];
    processing_time_s: number;
    processingEvidence?: OmrProcessingEvidence;
}

// Emits shapes required for drawing on Web or SVG Canvas overlays.
export interface OverlayData {
    corners: Point[];
    bubbles: {
        rect: Rect;
        checked: boolean;
        status: string;
        label: string;
    }[];
}
