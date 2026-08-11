export interface DetectedAnswer {
  questionIndex: number;
  questionId: number;
  selectedOption: number | null; // 0=A, 1=B, 2=C, 3=D, 4=E
  confidence: number; // 0.0 - 1.0
}

export interface ScanResult {
  localId: string;
  examId: number;
  copyId: number | null;
  validationHash: string;
  imageUri: string;
  detectedAnswers: Record<string, number | null>; // questionId -> selected option index
  questionConfidences?: Record<string, number>;
  confirmedAnswers: Record<string, number | null> | null;
  studentId: number | null;
  studentName: string | null;
  confidenceScore: number;
  score: number | null;
  totalPoints: number | null;
  status: 'processing' | 'review' | 'confirmed' | 'synced' | 'rejected';
  createdAt: string;
  syncedAt: string | null;
  serverScanId: number | null;
  /** QR contract version (currently supported: 3, 4 and 5). */
  qrVersion?: number;
  /** Versioned OMR template identity, independent from the QR contract. */
  templateId?: number;
  templateVersion?: number;
  rowsPerPage?: number;
  // Historical name retained for persisted scans; now always mirrors tpl_v.
  layoutVersion?: number;
  pageIndex?: number;
  pageTotal?: number;
  qStart?: number;
  qEnd?: number;
  sessionId?: string;
  /** v4 QR geometry: [x0, y0, columnStep, rowStep, bubbleWidth, optionStep] × 10000. */
  qrGeometry?: number[];
  /** v4 QR option counts, one digit per printed question (0 means essay). */
  qrOptionCounts?: string;
  /** Clockwise rotation needed to restore QR to the card's canonical top-right. */
  orientationQuarterTurns?: 0 | 1 | 2 | 3;
  /** Signed QR metadata retained for deferred, server-side grading. */
  qrPayload?: Record<string, unknown>;
  /** Position-keyed values for a signed offline QR (never answer-key data). */
  printedAnswers?: Record<string, number | null>;
  printedConfidences?: Record<string, number>;
  /** Reproducible local evidence; the backend still revalidates QR and identity. */
  omrEvidence?: {
    pipelineVersion: 'mobile-v2';
    processingPath: 'homography' | 'hybrid' | 'manual' | 'unavailable';
    action: 'accept' | 'review' | 'rescan';
    reasons: string[];
    imageQuality: Record<string, unknown> | null;
    geometry: {
      fiducialCount: number;
      fiducialConfidence: number;
      reprojectionError: { rms: number; max: number } | null;
      orientationDegrees: number | null;
      scaleRatio: number | null;
    };
    questions: Record<string, {
      action: 'accept' | 'review';
      reasons: string[];
      fillRatios: number[];
      confidence: number;
      roi: { x: number; y: number; w: number; h: number };
    }>;
  };
}


export interface GradingResult {
  questionId: number;
  questionNumber: number;
  detectedOptionIndex: number | null;
  originalOptionIndex: number | null;
  correctOptionIndex: number | null;
  isCorrect: boolean;
  points: number;
  confidence: number;
}

export interface QRPayload {
  // v0 fields (always present)
  e: number;   // exam_id
  c: number;   // copy_id
  h: string;   // validation_hash
  p?: number;  // page_index (1-based)
  pt?: number; // page_total
  qs?: number; // question_start (global sequential)
  qe?: number; // question_end   (global sequential)
  // Contract + template fields
  v?: number;    // QR contract version
  tpl_id?: number;
  tpl_v?: number;
  cols?: number; // number of bubble columns
  rpp?: number;  // rows per page (per column)
  chk?: string;  // HMAC authenticator (legacy hex or current base64url)
  /** v4 geometry, relative to the fiducial frame and scaled by 10000. */
  g?: number[];
  /** v4 number of options for each question on this page. */
  oc?: string;
  /** Encrypted answer key used only by the server. It is never decrypted on-device. */
  gab_enc?: string;
  /** Original signed JSON; used for deferred server verification only. */
  signedPayload?: Record<string, unknown>;
  // Parsed metadata (set by parser, not from QR)
  legacyQr?: boolean;
}

/** Mark type returned by the bubble classifier for a single question */
export type BubbleMarkType = 'answered' | 'blank' | 'multiple_marks' | 'ambiguous';

/** Per-question OMR result including fill ratios for debugging */
export interface QuestionOMRResult {
  questionId: number;
  markType: BubbleMarkType;
  selectedOption: number | null; // null if blank/ambiguous/multiple
  multipleOptions?: number[];     // set when markType='multiple_marks'
  confidence: number;
  fillRatios: number[];           // fill ratio per option A, B, C...
}

/** Status of a single page within a multi-sheet scan session */
export type PageStatus = 'pending' | 'confirmed' | 'synced' | 'rejected';

/** Tracks progress when scanning a multi-page answer sheet */
export interface MultiPageProgress {
  sessionId: string;   // UUID grouping all pages of one student's scan
  examId: number;
  copyId: number;
  studentId: number | null;
  totalPages: number;
  pages: Record<number, {  // keyed by page_index (1-based)
    status: PageStatus;
    localScanId: string;
    qStart: number;
    qEnd: number;
    answers: Record<string, number | null>;
    confidences: Record<string, number>;
  }>;
}
