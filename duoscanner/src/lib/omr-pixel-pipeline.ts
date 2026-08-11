import { classifyBubble, extractROI, selectAnswer, type BubbleROI, type QuestionResult } from './bubble-classifier.ts';
import { detectFiducials } from './fiducial-detector.ts';
import { isValidGeometryVector, resolveCardPageGeometryPixels } from './geometry-contract.ts';
import { isValidRowsPerPage, resolveGrid, resolveGridPosition } from './grid-mapping.ts';
import {
  computeHomography,
  invertHomography,
  reprojectionError,
  sortCorners,
  transformPoint,
  validateFiducialQuadrilateral,
  warpPerspective,
} from './homography.ts';
import { analyzeImageQuality, rgbaToGrayscale, type ImageQualityMetrics } from './image-quality.ts';
import { getTemplate, type LayoutTemplate } from './template-registry.ts';

const RECTIFIED_WIDTH = 1000;
export const CONFIDENCE_AUTO_ACCEPT = 0.80;
export const CONFIDENCE_RESCAN_THRESHOLD = 0.45;
export const CONFIDENCE_QUESTION_REVIEW = 0.60;

export type BubbleMarkType = 'answered' | 'blank' | 'multiple_marks' | 'ambiguous';
export type OMRAction = 'accept' | 'review' | 'rescan';

export interface QuestionEvidence {
  action: 'accept' | 'review';
  reasons: string[];
  roi: BubbleROI;
  originalPolygon?: { x: number; y: number }[];
}

export interface OMRProcessingResult {
  answers: Record<string, number | null>;
  confidences: Record<string, number>;
  fillRatios: Record<string, number[]>;
  markTypes: Record<string, BubbleMarkType>;
  multipleMarks: Record<string, number[]>;
  questionEvidence: Record<string, QuestionEvidence>;
  overallConfidence: number;
  fiducialConfidence: number;
  fiducialCount: number;
  usedHomography: boolean;
  processedAt: string;
  flaggedForReview: string[];
  needsRescan: boolean;
  fiducialCorners?: { x: number; y: number }[];
  action: OMRAction;
  processingPath: 'homography' | 'manual' | 'unavailable';
  reasons: string[];
  imageQuality: ImageQualityMetrics | null;
  reprojectionError: { rms: number; max: number } | null;
  orientationDegrees: number | null;
  scaleRatio: number | null;
  sourceSize: { width: number; height: number } | null;
}

export interface OMRPixelProcessingOptions {
  questionIds: number[];
  optionCounts: Record<string, number>;
  layoutVersion?: number;
  templateVersion?: number;
  rowsPerPage?: number;
  qrGeometry?: number[];
  qrVersion?: number;
  orientationVerified?: boolean;
}

interface PixelQuestionResult {
  markType: BubbleMarkType;
  selectedOption: number | null;
  multipleOptions?: number[];
  confidence: number;
  fillRatios: number[];
  roi: BubbleROI;
}

export function processOMRPixels(
  pixelResult: { data: Uint8Array | Uint8ClampedArray; width: number; height: number },
  options: OMRPixelProcessingOptions,
): OMRProcessingResult {
  const { questionIds, optionCounts, layoutVersion = 0, rowsPerPage, qrGeometry, qrVersion } = options;
  if (!questionIds?.length) return createEmptyResult([], ['no_questions']);
  if ((qrVersion === 4 || qrVersion === 5) && options.orientationVerified !== true) {
    return createEmptyResult(questionIds, ['orientation_unknown']);
  }

  const hasSignedGeometry = isValidGeometryVector(qrGeometry) && isValidRowsPerPage(rowsPerPage);
  if ((qrVersion === 4 || qrVersion === 5) && !hasSignedGeometry) {
    return createEmptyResult(questionIds, ['signed_geometry_missing']);
  }

  const template = getTemplate(hasSignedGeometry ? 0 : layoutVersion);
  const grid = resolveGrid(questionIds.length, template, hasSignedGeometry ? rowsPerPage : undefined);
  let rgba: Uint8Array;
  let initialQuality: ImageQualityMetrics;
  try {
    rgba = new Uint8Array(pixelResult.data.buffer, pixelResult.data.byteOffset, pixelResult.data.byteLength);
    initialQuality = analyzeImageQuality(
      rgbaToGrayscale(rgba, pixelResult.width, pixelResult.height, 4),
      pixelResult.width,
      pixelResult.height,
    );
  } catch {
    return createEmptyResult(questionIds, ['invalid_pixel_buffer']);
  }

  const fiducialResult = detectFiducials(rgba, pixelResult.width, pixelResult.height, 4);
  const corners = sortCorners(fiducialResult.corners);
  const evidence = {
    imageQuality: initialQuality,
    fiducialConfidence: fiducialResult.confidence,
    fiducialCount: fiducialResult.found,
    fiducialCorners: corners,
  };
  const quadrilateral = validateFiducialQuadrilateral(corners, pixelResult.width, pixelResult.height);
  if (!quadrilateral.valid) {
    return createEmptyResult(questionIds, [quadrilateral.reason ?? 'fiducial_geometry_invalid'], evidence);
  }

  const topWidth = Math.hypot(corners[1].x - corners[0].x, corners[1].y - corners[0].y);
  const bottomWidth = Math.hypot(corners[2].x - corners[3].x, corners[2].y - corners[3].y);
  const leftHeight = Math.hypot(corners[3].x - corners[0].x, corners[3].y - corners[0].y);
  const rightHeight = Math.hypot(corners[2].x - corners[1].x, corners[2].y - corners[1].y);
  const orientationDegrees = Math.atan2(
    corners[1].y - corners[0].y,
    corners[1].x - corners[0].x,
  ) * 180 / Math.PI;
  const scaleRatio = ((topWidth + bottomWidth) / 2) / pixelResult.width;
  if (Math.abs(orientationDegrees) > 20 || scaleRatio < 0.5 || scaleRatio > 1.05) {
    return createEmptyResult(questionIds, [
      Math.abs(orientationDegrees) > 20 ? 'orientation_outside_envelope' : 'scale_outside_envelope',
    ], {
      ...evidence,
      orientationDegrees,
      scaleRatio,
    });
  }
  const rectifiedHeight = Math.round(RECTIFIED_WIDTH * ((leftHeight + rightHeight) / (topWidth + bottomWidth)));
  if (!Number.isFinite(rectifiedHeight) || rectifiedHeight < 600 || rectifiedHeight > 2200) {
    return createEmptyResult(questionIds, ['rectified_dimensions_invalid'], evidence);
  }

  const destination = [
    { x: 0, y: 0 },
    { x: RECTIFIED_WIDTH - 1, y: 0 },
    { x: RECTIFIED_WIDTH - 1, y: rectifiedHeight - 1 },
    { x: 0, y: rectifiedHeight - 1 },
  ];
  let gray: Uint8Array;
  let projection: { rms: number; max: number };
  let inverseHomography: number[];
  try {
    const homography = computeHomography(corners, destination);
    inverseHomography = invertHomography(homography);
    projection = reprojectionError(homography, corners, destination);
    if (!Number.isFinite(projection.max) || projection.max > 2) throw new Error('reprojection');
    const warped = warpPerspective(
      rgba,
      pixelResult.width,
      pixelResult.height,
      4,
      homography,
      RECTIFIED_WIDTH,
      rectifiedHeight,
    );
    gray = rgbaToGrayscale(warped, RECTIFIED_WIDTH, rectifiedHeight, 4);
  } catch {
    return createEmptyResult(questionIds, ['homography_invalid'], evidence);
  }

  const imageQuality = analyzeImageQuality(gray, RECTIFIED_WIDTH, rectifiedHeight);
  if (!imageQuality.acceptable) {
    return createEmptyResult(questionIds, imageQuality.reasons, {
      ...evidence,
      imageQuality,
      reprojectionError: projection,
      orientationDegrees,
      scaleRatio,
      usedHomography: true,
    });
  }

  const answers: Record<string, number | null> = {};
  const confidences: Record<string, number> = {};
  const fillRatios: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  const multipleMarks: Record<string, number[]> = {};
  const questionEvidence: Record<string, QuestionEvidence> = {};
  const flaggedForReview: string[] = [];

  for (let index = 0; index < grid.totalQuestions; index += 1) {
    const questionId = questionIds[index];
    const { col, row } = resolveGridPosition(index, grid.rowsPerColumn);
    const optionCount = optionCounts[String(questionId)] ?? template.maxOptions;
    const key = String(questionId);
    try {
      const result = hasSignedGeometry
        ? readQuestionFromQrGeometry(gray, RECTIFIED_WIDTH, rectifiedHeight, qrGeometry, col, row, optionCount)
        : readQuestionFromTemplate(gray, RECTIFIED_WIDTH, rectifiedHeight, template, col, row, optionCount);
      answers[key] = result.selectedOption;
      confidences[key] = result.confidence;
      fillRatios[key] = result.fillRatios;
      markTypes[key] = result.markType;
      if (result.markType === 'multiple_marks' && result.multipleOptions) multipleMarks[key] = result.multipleOptions;
      const reasons = questionReasons(result);
      const action = reasons.length ? 'review' : 'accept';
      questionEvidence[key] = {
        action,
        reasons,
        roi: result.roi,
        originalPolygon: mapRoiToOriginal(result.roi, inverseHomography),
      };
      if (action === 'review') flaggedForReview.push(key);
    } catch {
      answers[key] = null;
      confidences[key] = 0;
      fillRatios[key] = [];
      markTypes[key] = 'ambiguous';
      flaggedForReview.push(key);
      questionEvidence[key] = {
        action: 'review',
        reasons: ['roi_invalid'],
        roi: { x: 0, y: 0, w: 0, h: 0 },
      };
    }
  }

  const values = questionIds.map((questionId) => confidences[String(questionId)] ?? 0);
  const overallConfidence = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : 0;
  const everyRoiInvalid = questionIds.length > 0 && questionIds.every((questionId) =>
    questionEvidence[String(questionId)]?.reasons.includes('roi_invalid'));
  // Once image and geometry gates pass, weak/erased/double/ambiguous marks are
  // review work, not a structural recapture. Rescan is reserved for an image
  // that cannot produce any valid ROI.
  const action: OMRAction = everyRoiInvalid
    ? 'rescan'
    : flaggedForReview.length || overallConfidence < CONFIDENCE_AUTO_ACCEPT
      ? 'review'
      : 'accept';

  return {
    answers,
    confidences,
    fillRatios,
    markTypes,
    multipleMarks,
    questionEvidence,
    overallConfidence,
    fiducialConfidence: fiducialResult.confidence,
    fiducialCount: fiducialResult.found,
    usedHomography: true,
    processedAt: new Date().toISOString(),
    flaggedForReview,
    needsRescan: action === 'rescan',
    fiducialCorners: corners,
    action,
    processingPath: 'homography',
    reasons: action === 'accept' ? [] : action === 'rescan' ? ['low_overall_confidence'] : ['question_review_required'],
    imageQuality,
    reprojectionError: projection,
    orientationDegrees,
    scaleRatio,
    sourceSize: { width: pixelResult.width, height: pixelResult.height },
  };
}

export function createEmptyResult(
  questionIds: number[],
  reasons: string[],
  evidence: Partial<Pick<OMRProcessingResult,
    'imageQuality' | 'reprojectionError' | 'orientationDegrees' | 'scaleRatio' | 'fiducialConfidence' | 'fiducialCount' | 'fiducialCorners' | 'usedHomography'>> = {},
): OMRProcessingResult {
  const answers: Record<string, number | null> = {};
  const confidences: Record<string, number> = {};
  const fillRatios: Record<string, number[]> = {};
  const markTypes: Record<string, BubbleMarkType> = {};
  const questionEvidence: Record<string, QuestionEvidence> = {};
  for (const questionId of questionIds) {
    const key = String(questionId);
    answers[key] = null;
    confidences[key] = 0;
    fillRatios[key] = [];
    markTypes[key] = 'ambiguous';
    questionEvidence[key] = { action: 'review', reasons, roi: { x: 0, y: 0, w: 0, h: 0 } };
  }
  return {
    answers,
    confidences,
    fillRatios,
    markTypes,
    multipleMarks: {},
    questionEvidence,
    overallConfidence: 0,
    fiducialConfidence: evidence.fiducialConfidence ?? 0,
    fiducialCount: evidence.fiducialCount ?? 0,
    usedHomography: evidence.usedHomography ?? false,
    processedAt: new Date().toISOString(),
    flaggedForReview: questionIds.map(String),
    needsRescan: true,
    fiducialCorners: evidence.fiducialCorners ?? [],
    action: 'rescan',
    processingPath: 'unavailable',
    reasons,
    imageQuality: evidence.imageQuality ?? null,
    reprojectionError: evidence.reprojectionError ?? null,
    orientationDegrees: evidence.orientationDegrees ?? null,
    scaleRatio: evidence.scaleRatio ?? null,
    sourceSize: null,
  };
}

function questionReasons(result: PixelQuestionResult): string[] {
  const reasons: string[] = [];
  if (result.confidence < CONFIDENCE_QUESTION_REVIEW) reasons.push('low_mark_confidence');
  if (result.markType === 'multiple_marks') reasons.push('multiple_marks');
  if (result.markType === 'ambiguous') reasons.push('ambiguous_mark');
  return reasons;
}

function readQuestionFromQrGeometry(
  pixels: Uint8Array,
  width: number,
  height: number,
  geometry: number[],
  column: number,
  row: number,
  optionCount: number,
): PixelQuestionResult {
  const resolved = resolveCardPageGeometryPixels(geometry, width, height);
  const bubbleSize = Math.max(8, Math.round(resolved.bubbleWidth));
  const centerY = resolved.startY + row * resolved.rowStep + bubbleSize / 2;
  const roiSize = Math.max(6, Math.round(bubbleSize * 0.58));
  const classifications = [];
  const rois: BubbleROI[] = [];
  for (let option = 0; option < optionCount; option += 1) {
    const centerX = resolved.startX + column * resolved.columnStep + option * resolved.optionStep + bubbleSize / 2;
    const roi = boundedRoi(centerX - roiSize / 2, centerY - roiSize / 2, roiSize, roiSize, width, height);
    rois.push(roi);
    classifications.push(classifyBubble(extractROI(pixels, width, height, roi)));
  }
  return normalizeQuestionResult(selectAnswer(classifications), unionRois(rois));
}

function readQuestionFromTemplate(
  pixels: Uint8Array,
  width: number,
  height: number,
  template: LayoutTemplate,
  column: number,
  row: number,
  optionCount: number,
): PixelQuestionResult {
  const mmToPixels = width / template.areaWidthMm;
  const columnStart = template.colBubbleStartMm[column] ?? template.colBubbleStartMm[0];
  const columnEnd = template.colBubbleEndMm[column] ?? template.colBubbleEndMm[0];
  const bubbleWidthMm = (columnEnd - columnStart) / Math.max(optionCount, 1);
  const gridTop = template.gridTopOffsetMm * mmToPixels;
  const rowStep = (height - gridTop) / template.rowsPerCol;
  const disciplineOffset = column === 0 ? template.disciplineHeaderHeightMm * mmToPixels : 0;
  const centerY = gridTop + disciplineOffset + (row + 0.5) * rowStep;
  const roiHeight = Math.max(Math.round(rowStep * 0.9), 36);
  const classifications = [];
  const rois: BubbleROI[] = [];
  for (let option = 0; option < optionCount; option += 1) {
    const centerX = (columnStart + (option + 0.5) * bubbleWidthMm) * mmToPixels;
    const roiWidth = Math.max(Math.round(bubbleWidthMm * mmToPixels * 0.7), 8);
    const roi = boundedRoi(centerX - roiWidth / 2, centerY - roiHeight / 2, roiWidth, roiHeight, width, height);
    rois.push(roi);
    classifications.push(classifyBubble(extractROI(pixels, width, height, roi)));
  }
  return normalizeQuestionResult(selectAnswer(classifications), unionRois(rois));
}

function normalizeQuestionResult(result: QuestionResult, roi: BubbleROI): PixelQuestionResult {
  const markType: BubbleMarkType = result.type === 'answered'
    ? 'answered'
    : result.type === 'blank'
      ? 'blank'
      : result.type === 'multiple_marks'
        ? 'multiple_marks'
        : 'ambiguous';
  return {
    markType,
    selectedOption: result.type === 'answered' ? result.optionIndex : null,
    multipleOptions: result.type === 'multiple_marks' ? result.optionIndices : undefined,
    confidence: result.confidence,
    fillRatios: result.fillRatios,
    roi,
  };
}

function boundedRoi(x: number, y: number, width: number, height: number, imageWidth: number, imageHeight: number): BubbleROI {
  const boundedX = Math.max(0, Math.min(imageWidth, Math.round(x)));
  const boundedY = Math.max(0, Math.min(imageHeight, Math.round(y)));
  const boundedWidth = Math.min(Math.round(width), imageWidth - boundedX);
  const boundedHeight = Math.min(Math.round(height), imageHeight - boundedY);
  if (boundedWidth <= 0 || boundedHeight <= 0) throw new Error('ROI outside rectified frame');
  return { x: boundedX, y: boundedY, w: boundedWidth, h: boundedHeight };
}

function unionRois(rois: BubbleROI[]): BubbleROI {
  if (!rois.length) throw new Error('Question has no bubble ROI');
  const x = Math.min(...rois.map((roi) => roi.x));
  const y = Math.min(...rois.map((roi) => roi.y));
  const right = Math.max(...rois.map((roi) => roi.x + roi.w));
  const bottom = Math.max(...rois.map((roi) => roi.y + roi.h));
  return { x, y, w: right - x, h: bottom - y };
}

function mapRoiToOriginal(roi: BubbleROI, inverseHomography: number[]): { x: number; y: number }[] {
  return [
    { x: roi.x, y: roi.y },
    { x: roi.x + roi.w, y: roi.y },
    { x: roi.x + roi.w, y: roi.y + roi.h },
    { x: roi.x, y: roi.y + roi.h },
  ].map((point) => transformPoint(inverseHomography, point));
}
