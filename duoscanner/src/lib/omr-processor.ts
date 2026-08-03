/**
 * OMR Processor — Full 8-Step Pipeline
 *
 * Pipeline:
 *   Step 0: Capture guidance (done in camera.tsx)
 *   Step 1: Pre-processing (resize, convert to RGBA pixel data)
 *   Step 2: Fiducial detection (4 black square markers)
 *   Step 3: Perspective correction (homography → rectified image)
 *   Step 4: QR Code decode on rectified image (via jsQR fallback)
 *   Step 5: Segmentation (header vs answer area)
 *   Step 6: Grid mapping (mm → px using template)
 *   Step 7: Bubble classification (fill-ratio per bubble)
 *   Step 8: Confidence metrics + rescan decision
 *
 * IMPORTANT: This module computes everything in pixel data. It uses the
 * Canvas API (available in React Native via react-native-canvas or the
 * built-in canvas in Hermes). If Canvas is not available, it gracefully
 * falls back to the legacy proportional-grid approach for the bubble
 * reading step (bypassing homography).
 */

import * as FileSystem from 'expo-file-system';
import * as ImageManipulator from 'expo-image-manipulator';
import { decodePng } from './png-decoder';
import { detectFiducials, type FiducialResult } from './fiducial-detector';
import { computeHomography, warpPerspective, sortCorners } from './homography';
import { classifyBubble, selectAnswer, extractROI, type QuestionResult } from './bubble-classifier';
import { getTemplate, type LayoutTemplate } from './template-registry';
import type { QuestionOMRResult, BubbleMarkType } from '@/types/scan';

// ─── Constants ───────────────────────────────────────────────────────────────

/** Target width for the working copy of the image */
const PROCESS_WIDTH = 1200;
/** Target output width for the rectified (perspective-corrected) image */
const RECTIFIED_WIDTH = 1000;
/** Minimum overall confidence to auto-accept result without review prompt */
export const CONFIDENCE_AUTO_ACCEPT = 0.80;
/** Overall confidence below this triggers a "please re-scan" warning */
export const CONFIDENCE_RESCAN_THRESHOLD = 0.45;
/** Per-question confidence below this flags for manual review */
export const CONFIDENCE_QUESTION_REVIEW = 0.60;

// ─── Public interface ────────────────────────────────────────────────────────

export interface OMRProcessingResult {
  answers: Record<string, number | null>;
  confidences: Record<string, number>;
  fillRatios: Record<string, number[]>;
  markTypes: Record<string, BubbleMarkType>;
  multipleMarks: Record<string, number[]>;   // questionId → [optIdx, optIdx]
  overallConfidence: number;
  fiducialConfidence: number;
  fiducialCount: number;
  usedHomography: boolean;
  processedAt: string;
  /** Questions that need manual review (confidence < CONFIDENCE_QUESTION_REVIEW) */
  flaggedForReview: string[];
  /** true if overall confidence < CONFIDENCE_RESCAN_THRESHOLD */
  needsRescan: boolean;
  /** Fiducial corners if homography was used or at least attempted */
  fiducialCorners?: { x: number; y: number }[];
}

export interface OMRProcessingOptions {
  questionIds: number[];
  optionCounts: Record<string, number>;   // questionId → number of options
  layoutVersion?: number;                 // from QR payload
  /** Exact v4 layout encoded in the answer-sheet QR. */
  qrGeometry?: number[];
  /** Pixel data of the image (RGBA). If undefined, will be extracted from uri. */
  pixelData?: { data: Uint8ClampedArray; width: number; height: number };
}

// ─── Main entry point ────────────────────────────────────────────────────────

/**
 * Process an answer sheet image and return detected answers.
 *
 * @param imageUri  Path to the captured image (expo file:// URI)
 * @param options   Processing options
 */
export async function processOMR(
  imageUri: string,
  options: OMRProcessingOptions
): Promise<OMRProcessingResult> {
  const { questionIds, optionCounts, layoutVersion = 0, qrGeometry } = options;

  if (!questionIds || questionIds.length === 0) {
    return createEmptyResult([], false);
  }

  const template = getTemplate(layoutVersion);

  // ── Step 1: Resize image to working size ───────────────────────────────────
  let workingUri: string;
  let workingW: number;
  let workingH: number;

  try {
    const resized = await ImageManipulator.manipulateAsync(
      imageUri,
      [{ resize: { width: PROCESS_WIDTH } }],
      { format: ImageManipulator.SaveFormat.PNG }
    );
    workingUri = resized.uri;
    workingW = resized.width;
    workingH = resized.height;
  } catch {
    return createEmptyResult(questionIds, false);
  }

  // ── Step 2 & 3: Try to get pixel data and run homography ──────────────────
  const pixelResult = await tryGetPixelData(workingUri, workingW, workingH);

  let fiducialResult = { corners: [] as any[], confidence: 0, found: 0 };
  let rectifiedUri = workingUri;
  let rectifiedW = workingW;
  let rectifiedH = workingH;
  let rectifiedPixels: Uint8ClampedArray | null = null;
  let usedHomography = false;

  if (pixelResult) {
    try {
      // Keep the scanner fully native-compatible: the previous browser/Node
      // OpenCV.js bundle imported `fs` and could not be packaged by Metro.
      // These pure TypeScript routines run on Hermes and preserve the same
      // detection/homography pipeline without a WebView or Node polyfills.
      const rgba = new Uint8Array(
        pixelResult.data.buffer,
        pixelResult.data.byteOffset,
        pixelResult.data.byteLength
      );
      fiducialResult = detectFiducials(
        rgba,
        pixelResult.width,
        pixelResult.height,
        4
      );
      const corners = sortCorners(fiducialResult.corners);

      console.log(`OMR: Fiducials found=${corners.length}`);

      if (corners.length >= 4) {
        // Calculate dynamic height based on aspect ratio
        const sorted = corners; // [TL, TR, BR, BL]
        const topW = Math.hypot(sorted[1].x - sorted[0].x, sorted[1].y - sorted[0].y);
        const botW = Math.hypot(sorted[2].x - sorted[3].x, sorted[2].y - sorted[3].y);
        const leftH = Math.hypot(sorted[3].x - sorted[0].x, sorted[3].y - sorted[0].y);
        const rightH = Math.hypot(sorted[2].x - sorted[1].x, sorted[2].y - sorted[1].y);

        const avgW = Math.max(1, (topW + botW) / 2);
        const avgH = Math.max(1, (leftH + rightH) / 2);

        const scale = RECTIFIED_WIDTH / template.areaWidthMm;
        const dynamicAreaHeightMm = template.areaWidthMm * (avgH / avgW);
        const dstH = Math.round(dynamicAreaHeightMm * scale);

        const destination = [
          { x: 0, y: 0 },
          { x: RECTIFIED_WIDTH - 1, y: 0 },
          { x: RECTIFIED_WIDTH - 1, y: dstH - 1 },
          { x: 0, y: dstH - 1 },
        ];
        const homography = computeHomography(sorted, destination);
        const warped = warpPerspective(
          rgba,
          pixelResult.width,
          pixelResult.height,
          4,
          homography,
          RECTIFIED_WIDTH,
          dstH
        );

        rectifiedW = RECTIFIED_WIDTH;
        rectifiedH = dstH;
        rectifiedPixels = new Uint8ClampedArray(warped.buffer);
        usedHomography = true;

        console.log(`OMR: Homography applied -> ${rectifiedW}x${rectifiedH}`);
      }
    } catch (e) {
      console.error('OMR processing pipeline error:', e);
    }
  }

  // Use rectified pixels if available, else fallback to file-based approach
  const activePixels = rectifiedPixels ?? pixelResult?.data ?? null;
  const activeW = rectifiedPixels ? rectifiedW : (pixelResult?.width ?? workingW);
  const activeH = rectifiedPixels ? rectifiedH : (pixelResult?.height ?? workingH);

  // ── Step 6 & 7: Map grid and read bubbles ────────────────────────────────
  const answers: Record<string, number | null> = {};
  const confidences: Record<string, number> = {};
  const fillRatiosMap: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  const multipleMarks: Record<string, number[]> = {};
  const flaggedForReview: string[] = [];

  const totalQ = Math.min(questionIds.length, template.numCols * template.rowsPerCol);

  if (activePixels) {
    // Pixel-based processing (preferred)
    for (let i = 0; i < totalQ; i++) {
      const qId = questionIds[i];
      const col = Math.floor(i / template.rowsPerCol);
      const row = i % template.rowsPerCol;
      const numOpts = optionCounts[String(qId)] ?? template.maxOptions;

      try {
        const result = isValidQrGeometry(qrGeometry)
          ? readQuestionFromQrGeometry(
            activePixels, activeW, activeH, qrGeometry,
            col, row, numOpts
          )
          : readQuestionFromPixels(
            activePixels, activeW, activeH, template,
            col, row, numOpts, usedHomography
          );

        answers[String(qId)] = result.selectedOption;
        confidences[String(qId)] = result.confidence;
        fillRatiosMap[String(qId)] = result.fillRatios;
        markTypes[String(qId)] = result.markType;

        if (result.markType === 'multiple_marks' && result.multipleOptions) {
          multipleMarks[String(qId)] = result.multipleOptions;
        }
        if (result.confidence < CONFIDENCE_QUESTION_REVIEW) {
          flaggedForReview.push(String(qId));
        }
      } catch (err) {
        console.warn(`OMR: Q${qId} failed:`, err);
        answers[String(qId)] = null;
        confidences[String(qId)] = 0;
        fillRatiosMap[String(qId)] = [];
        markTypes[String(qId)] = 'ambiguous';
        flaggedForReview.push(String(qId));
      }
    }
  } else {
    // Fallback: legacy file-size approach (no pixel data available)
    console.warn('OMR: No pixel data available, falling back to legacy approach');
    return await legacyFileSizeProcessing(workingUri, questionIds, optionCounts, workingW, workingH);
  }

  const vals = Object.values(confidences).filter((c) => c > 0);
  const overallConfidence = vals.length > 0
    ? vals.reduce((a, b) => a + b, 0) / vals.length
    : 0;

  return {
    answers,
    confidences,
    fillRatios: fillRatiosMap,
    markTypes,
    multipleMarks,
    overallConfidence,
    fiducialConfidence: fiducialResult.confidence,
    fiducialCount: fiducialResult.found,
    usedHomography,
    processedAt: new Date().toISOString(),
    flaggedForReview,
    needsRescan: overallConfidence < CONFIDENCE_RESCAN_THRESHOLD,
    fiducialCorners: fiducialResult.corners,
  };
}

function isValidQrGeometry(value: number[] | undefined): value is number[] {
  return Array.isArray(value)
    && value.length === 6
    && value.every((item) => Number.isFinite(item) && item >= 0)
    && value[2] > 0
    && value[3] > 0
    && value[4] > 0
    && value[5] > 0;
}

/**
 * Reads v4 answer sheets using the coordinates emitted with the QR.  The
 * four fiducials have already rectified the image, so all values are fractions
 * of that frame. This avoids guessing a legacy template for a current card.
 */
function readQuestionFromQrGeometry(
  pixels: Uint8ClampedArray,
  imgW: number,
  imgH: number,
  geometry: number[],
  col: number,
  row: number,
  numOpts: number
): QuestionOMRResult & { markType: BubbleMarkType; multipleOptions?: number[] } {
  const [startX, startY, colStep, rowStep, bubbleWidth, optionStep] = geometry.map((item) => item / 10000);
  const bubblePx = Math.max(8, Math.round(bubbleWidth * imgW));
  const centerY = (startY + row * rowStep) * imgH + bubblePx / 2;
  const classifications = [];

  for (let opt = 0; opt < numOpts; opt++) {
    const centerX = (startX + col * colStep + opt * optionStep) * imgW + bubblePx / 2;
    // Read only the centre of the bubble: the printed ring and letter should
    // not be mistaken for a filled response.
    const roiSize = Math.max(6, Math.round(bubblePx * 0.58));
    const x = Math.max(0, Math.min(imgW - roiSize, Math.round(centerX - roiSize / 2)));
    const y = Math.max(0, Math.min(imgH - roiSize, Math.round(centerY - roiSize / 2)));
    const roiData = extractROI(new Uint8Array(pixels.buffer), imgW, { x, y, w: roiSize, h: roiSize });
    classifications.push(classifyBubble(roiData));
  }

  const result = selectAnswer(classifications);
  return {
    questionId: 0,
    markType: result.type === 'answered' ? 'answered' : result.type === 'blank' ? 'blank' : result.type === 'multiple_marks' ? 'multiple_marks' : 'ambiguous',
    selectedOption: result.type === 'answered' ? result.optionIndex : null,
    multipleOptions: result.type === 'multiple_marks' ? result.optionIndices : undefined,
    confidence: result.confidence,
    fillRatios: result.fillRatios,
  };
}

// ─── Pixel-based bubble reading ──────────────────────────────────────────────

function readQuestionFromPixels(
  pixels: Uint8ClampedArray,
  imgW: number,
  imgH: number,
  template: LayoutTemplate,
  col: number,
  row: number,
  numOpts: number,
  usingHomography: boolean
): QuestionOMRResult & { markType: BubbleMarkType; multipleOptions?: number[] } {
  // Scale from mm to pixels
  const mmToPx = imgW / template.areaWidthMm;

  // X: bubble positions per column
  const colStartMm = template.colBubbleStartMm[col] ?? template.colBubbleStartMm[0];
  const colEndMm = template.colBubbleEndMm[col] ?? template.colBubbleEndMm[0];
  const colWidthMm = colEndMm - colStartMm;
  const bubbleWMm = colWidthMm / Math.max(numOpts, 1);

  // Y: question row position
  // The first line starts around 15.8mm or 22.6mm
  const gridTopPx = template.gridTopOffsetMm * mmToPx;

  // Use the actual image height (which matches dynamic dstH based on the bottom fiducial)
  const gridBottomPx = imgH;

  // The top fiducial is 0, the bottom fiducial is dstH.
  // The space between the top row and the bottom fiducial contains `template.rowsPerCol` rows.
  // We leave some margin at the bottom where the fiducial sits.
  const usableH = gridBottomPx - gridTopPx;
  const rowStepPx = usableH / template.rowsPerCol;

  // We add dynamic discipline-header offset if it's column 0
  let colDisciplineOffsetPx = 0;
  if (col === 0) {
    colDisciplineOffsetPx = template.disciplineHeaderHeightMm * mmToPx;
  }

  // We make bubbleH very generous (e.g., 36 pixels = ~6mm) so it finds the bubble 
  // even if the exact discipline header offsets vary per column
  const rowCenterYPx = gridTopPx + colDisciplineOffsetPx + (row + 0.5) * rowStepPx;
  const bubbleH = Math.max(Math.round(rowStepPx * 0.90), 36);

  const bubbleClassifications = [];
  for (let opt = 0; opt < numOpts; opt++) {
    const bubbleCenterXMm = colStartMm + (opt + 0.5) * bubbleWMm;
    const bubbleCenterXPx = bubbleCenterXMm * mmToPx;
    const bubbleW = Math.max(Math.round(bubbleWMm * mmToPx * 0.70), 8);

    const x = Math.max(0, Math.round(bubbleCenterXPx - bubbleW / 2));
    const y = Math.max(0, Math.round(rowCenterYPx - bubbleH / 2));
    const w = Math.min(bubbleW, imgW - x);
    const h = Math.min(bubbleH, imgH - y);

    if (w <= 0 || h <= 0) {
      bubbleClassifications.push({ state: 'ambiguous' as const, fillRatio: 0, confidence: 0 });
      continue;
    }

    const roiData = extractROI(new Uint8Array(pixels.buffer), imgW, { x, y, w, h });
    bubbleClassifications.push(classifyBubble(roiData));
  }

  const result: QuestionResult = selectAnswer(bubbleClassifications);

  let selectedOption: number | null = null;
  let markType: BubbleMarkType;
  let multipleOptions: number[] | undefined;

  switch (result.type) {
    case 'answered':
      selectedOption = result.optionIndex;
      markType = 'answered';
      break;
    case 'blank':
      markType = 'blank';
      break;
    case 'multiple_marks':
      markType = 'multiple_marks';
      multipleOptions = result.optionIndices;
      break;
    default:
      markType = 'ambiguous';
  }

  return {
    questionId: 0, // Will be set by caller
    markType,
    selectedOption,
    multipleOptions,
    confidence: result.confidence,
    fillRatios: result.fillRatios,
  };
}

// ─── Pixel data extraction ───────────────────────────────────────────────────

/**
 * Decode an image file into raw RGBA pixel data using fast-png.
 * fast-png is a pure-JS PNG decoder that works in Hermes (React Native).
 *
 * IMPORTANT: The image must be in PNG format. We always save the working
 * copy as PNG (ImageManipulator.SaveFormat.PNG) so this is guaranteed.
 */
async function tryGetPixelData(
  uri: string,
  w: number,
  h: number
): Promise<{ data: Uint8ClampedArray; width: number; height: number } | null> {
  try {
    // Read PNG file as base64
    const base64 = await FileSystem.readAsStringAsync(uri, {
      encoding: FileSystem.EncodingType.Base64,
    });

    // Decode base64 → Uint8Array (binary PNG data)
    const binaryStr = atob(base64);
    const pngBytes = new Uint8Array(binaryStr.length);
    for (let i = 0; i < binaryStr.length; i++) {
      pngBytes[i] = binaryStr.charCodeAt(i);
    }

    // Decode PNG → pixel data using our RN-compatible decoder
    const decoded = decodePng(pngBytes);

    // Convert to RGBA regardless of source channels
    let rgba: Uint8ClampedArray;
    if (decoded.channels === 4) {
      rgba = new Uint8ClampedArray(decoded.data);
    } else if (decoded.channels === 3) {
      rgba = new Uint8ClampedArray(decoded.width * decoded.height * 4);
      for (let i = 0; i < decoded.width * decoded.height; i++) {
        rgba[i * 4 + 0] = decoded.data[i * 3 + 0];
        rgba[i * 4 + 1] = decoded.data[i * 3 + 1];
        rgba[i * 4 + 2] = decoded.data[i * 3 + 2];
        rgba[i * 4 + 3] = 255;
      }
    } else if (decoded.channels === 2) {
      // Grayscale + Alpha → RGBA
      rgba = new Uint8ClampedArray(decoded.width * decoded.height * 4);
      for (let i = 0; i < decoded.width * decoded.height; i++) {
        const v = decoded.data[i * 2];
        rgba[i * 4 + 0] = v;
        rgba[i * 4 + 1] = v;
        rgba[i * 4 + 2] = v;
        rgba[i * 4 + 3] = decoded.data[i * 2 + 1];
      }
    } else if (decoded.channels === 1) {
      rgba = new Uint8ClampedArray(decoded.width * decoded.height * 4);
      for (let i = 0; i < decoded.width * decoded.height; i++) {
        rgba[i * 4 + 0] = decoded.data[i];
        rgba[i * 4 + 1] = decoded.data[i];
        rgba[i * 4 + 2] = decoded.data[i];
        rgba[i * 4 + 3] = 255;
      }
    } else {
      return null;
    }

    console.log(`OMR: PNG decoded ${decoded.width}×${decoded.height}, channels=${decoded.channels}`);
    return { data: rgba, width: decoded.width, height: decoded.height };
  } catch (err) {
    console.warn('OMR: PNG decode failed, using legacy path:', err);
    return null;
  }
}

// ─── Legacy fallback (file-size heuristic) ───────────────────────────────────
// Kept as a fallback while pixel access is not yet available.

const GRID_COLS_LEGACY = 4;
const GRID_ROWS_LEGACY = 15;
const MAX_OPTIONS_LEGACY = 5;
const COL_BUBBLES_START_LEGACY = [0.149, 0.408, 0.630, 0.852];
const COL_BUBBLES_END_LEGACY = [0.310, 0.568, 0.790, 0.935];
const GRID_TOP_LEGACY = 0.17;
const GRID_BOTTOM_LEGACY = 0.95;

async function legacyFileSizeProcessing(
  uri: string,
  questionIds: number[],
  optionCounts: Record<string, number>,
  W: number,
  H: number
): Promise<OMRProcessingResult> {
  const answers: Record<string, number | null> = {};
  const confidences: Record<string, number> = {};
  const fillRatiosMap: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  const flaggedForReview: string[] = [];

  const totalQ = Math.min(questionIds.length, GRID_COLS_LEGACY * GRID_ROWS_LEGACY);

  for (let i = 0; i < totalQ; i++) {
    const qId = questionIds[i];
    const col = Math.floor(i / GRID_ROWS_LEGACY);
    const row = i % GRID_ROWS_LEGACY;
    const numOpts = optionCounts[String(qId)] || MAX_OPTIONS_LEGACY;

    try {
      const sizes = await Promise.all(
        Array.from({ length: numOpts }, (_, opt) =>
          legacyCropAndMeasure(uri, col, row, opt, numOpts, W, H)
        )
      );

      const { selectedOption, confidence } = legacyAnalyzeBubbles(sizes);
      answers[String(qId)] = selectedOption;
      confidences[String(qId)] = confidence;
      fillRatiosMap[String(qId)] = sizes.map((s) => s / Math.max(...sizes));
      markTypes[String(qId)] = selectedOption !== null ? 'answered' : 'ambiguous';

      if (confidence < CONFIDENCE_QUESTION_REVIEW) {
        flaggedForReview.push(String(qId));
      }
    } catch {
      answers[String(qId)] = null;
      confidences[String(qId)] = 0;
      fillRatiosMap[String(qId)] = [];
      markTypes[String(qId)] = 'ambiguous';
      flaggedForReview.push(String(qId));
    }
  }

  const vals = Object.values(confidences).filter((c) => c > 0);
  const overall = vals.length > 0 ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;

  return {
    answers, confidences, fillRatios: fillRatiosMap, markTypes,
    multipleMarks: {}, overallConfidence: overall,
    fiducialConfidence: 0, fiducialCount: 0, usedHomography: false,
    processedAt: new Date().toISOString(), flaggedForReview,
    needsRescan: overall < CONFIDENCE_RESCAN_THRESHOLD,
    fiducialCorners: [],
  };
}

async function legacyCropAndMeasure(
  uri: string,
  col: number, row: number, opt: number, numOpts: number,
  imgW: number, imgH: number
): Promise<number> {
  const colStart = COL_BUBBLES_START_LEGACY[col] ?? COL_BUBBLES_START_LEGACY[0];
  const colEnd = COL_BUBBLES_END_LEGACY[col] ?? COL_BUBBLES_END_LEGACY[0];
  const bw = (colEnd - colStart) * imgW / numOpts;
  const cx = (colStart * imgW) + (opt + 0.5) * bw;

  const gridTopPx = GRID_TOP_LEGACY * imgH;
  const gridH = (GRID_BOTTOM_LEGACY - GRID_TOP_LEGACY) * imgH;
  const rowStep = (0.87 * gridH) / GRID_ROWS_LEGACY;
  const cy = gridTopPx + (0.08 * gridH) + (row + 0.5) * rowStep;

  const cropW = Math.max(Math.round(bw * 0.75), 8);
  const cropH = Math.max(Math.round(rowStep * 0.55), 8);
  const x = Math.max(0, Math.min(Math.round(cx - cropW / 2), imgW - cropW));
  const y = Math.max(0, Math.min(Math.round(cy - cropH / 2), imgH - cropH));

  try {
    const cropped = await ImageManipulator.manipulateAsync(
      uri,
      [{ crop: { originX: x, originY: y, width: Math.max(cropW, 2), height: Math.max(cropH, 2) } }],
      { format: ImageManipulator.SaveFormat.JPEG, compress: 0.1 }
    );
    const info = await FileSystem.getInfoAsync(cropped.uri);
    if (info.exists && 'size' in info) return info.size;
  } catch { /* skip */ }
  return 0;
}

function legacyAnalyzeBubbles(sizes: number[]): { selectedOption: number | null; confidence: number } {
  const valid = sizes.filter((s) => s > 0);
  if (valid.length < 2) return { selectedOption: null, confidence: 0.2 };

  const sorted = [...valid].sort((a, b) => a - b);
  const median = sorted.length % 2 === 0
    ? (sorted[sorted.length / 2 - 1] + sorted[sorted.length / 2]) / 2
    : sorted[Math.floor(sorted.length / 2)];

  let maxDev = 0, maxIdx = -1;
  for (let i = 0; i < sizes.length; i++) {
    if (!sizes[i]) continue;
    const dev = Math.abs(sizes[i] - median);
    if (dev > maxDev) { maxDev = dev; maxIdx = i; }
  }
  if (maxIdx === -1) return { selectedOption: null, confidence: 0 };

  const remaining = sizes.filter((_, i) => i !== maxIdx && sizes[i] > 0);
  if (!remaining.length) return { selectedOption: maxIdx, confidence: 0.3 };

  const remMean = remaining.reduce((a, b) => a + b, 0) / remaining.length;
  const remVar = remaining.reduce((sum, v) => sum + (v - remMean) ** 2, 0) / remaining.length;
  const remStd = Math.sqrt(remVar);
  const dev2 = Math.abs(sizes[maxIdx] - remMean);
  const z = remStd > 0 ? dev2 / remStd : (dev2 > 5 ? 10 : 0);
  const pct = remMean > 0 ? dev2 / remMean : 0;

  if (z >= 3.0 || pct >= 0.30) return { selectedOption: maxIdx, confidence: 0.95 };
  if (z >= 2.0 || pct >= 0.20) return { selectedOption: maxIdx, confidence: 0.85 };
  if (z >= 1.5 || pct >= 0.12) return { selectedOption: maxIdx, confidence: 0.70 };
  if (z >= 1.0 || pct >= 0.08) return { selectedOption: maxIdx, confidence: 0.50 };
  return { selectedOption: null, confidence: 0.2 };
}

// ─── Utilities ───────────────────────────────────────────────────────────────

function createEmptyResult(questionIds: number[], usedHomography: boolean): OMRProcessingResult {
  const answers: Record<string, number | null> = {};
  const confidences: Record<string, number> = {};
  const fillRatios: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  questionIds.forEach((qId) => {
    answers[String(qId)] = null;
    confidences[String(qId)] = 0;
    fillRatios[String(qId)] = [];
    markTypes[String(qId)] = 'ambiguous';
  });
  return {
    answers, confidences, fillRatios, markTypes, multipleMarks: {},
    overallConfidence: 0, fiducialConfidence: 0, fiducialCount: 0,
    usedHomography, processedAt: new Date().toISOString(),
    flaggedForReview: questionIds.map(String), needsRescan: true,
    fiducialCorners: [],
  };
}

/**
 * Create OMR results from manually entered answers.
 */
export function createManualOMRResult(
  answers: Record<string, number | null>,
  questionIds: number[]
): OMRProcessingResult {
  const confidences: Record<string, number> = {};
  const fillRatios: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  questionIds.forEach((qId) => {
    const key = String(qId);
    confidences[key] = answers[key] !== null ? 1.0 : 0;
    fillRatios[key] = [];
    markTypes[key] = answers[key] !== null ? 'answered' : 'blank';
  });
  return {
    answers, confidences, fillRatios, markTypes, multipleMarks: {},
    overallConfidence: 1.0, fiducialConfidence: 1.0, fiducialCount: 4,
    usedHomography: false, processedAt: new Date().toISOString(),
    flaggedForReview: [], needsRescan: false,
  };
}
