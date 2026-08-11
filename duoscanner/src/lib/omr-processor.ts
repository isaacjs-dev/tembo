import * as FileSystem from 'expo-file-system';
import * as ImageManipulator from 'expo-image-manipulator';

import { decodePng } from './png-decoder';
import { pointFromNormalizedImage, rotateRgbaQuarterTurns, type QuarterTurns } from './qr-orientation';
import {
  CONFIDENCE_AUTO_ACCEPT,
  CONFIDENCE_QUESTION_REVIEW,
  CONFIDENCE_RESCAN_THRESHOLD,
  createEmptyResult,
  processOMRPixels,
  type OMRPixelProcessingOptions,
  type OMRProcessingResult,
} from './omr-pixel-pipeline';

const PROCESS_WIDTH = 1200;

export {
  CONFIDENCE_AUTO_ACCEPT,
  CONFIDENCE_QUESTION_REVIEW,
  CONFIDENCE_RESCAN_THRESHOLD,
  processOMRPixels,
};
export type { OMRProcessingResult } from './omr-pixel-pipeline';

export interface OMRProcessingOptions extends OMRPixelProcessingOptions {
  /** Deterministic/testing entry point. Production normally decodes imageUri. */
  pixelData?: { data: Uint8ClampedArray; width: number; height: number };
  orientationQuarterTurns?: QuarterTurns;
}

/**
 * Expo adapter for the pure pixel pipeline. Decode, geometry and image-quality
 * failures return `rescan` with no inferred answers; there is deliberately no
 * file-size/JPEG heuristic fallback.
 */
export async function processOMR(
  imageUri: string,
  options: OMRProcessingOptions,
): Promise<OMRProcessingResult> {
  if (!options.questionIds?.length) return createEmptyResult([], ['no_questions']);
  if (options.pixelData) {
    if (options.orientationQuarterTurns === undefined) return createEmptyResult(options.questionIds, ['orientation_unknown']);
    const result = processOMRPixels(rotateRgbaQuarterTurns(options.pixelData, options.orientationQuarterTurns), {
      ...options,
      orientationVerified: true,
    });
    return evidenceInCapturedCoordinates(result, options.pixelData.width, options.pixelData.height, options.orientationQuarterTurns);
  }

  try {
    const resized = await ImageManipulator.manipulateAsync(
      imageUri,
      [{ resize: { width: PROCESS_WIDTH } }],
      { format: ImageManipulator.SaveFormat.PNG },
    );
    const pixels = await decodeImagePixels(resized.uri);
    if (!pixels) return createEmptyResult(options.questionIds, ['image_decode_failed']);
    if (options.orientationQuarterTurns === undefined) return createEmptyResult(options.questionIds, ['orientation_unknown']);
    const result = processOMRPixels(rotateRgbaQuarterTurns(pixels, options.orientationQuarterTurns), {
      ...options,
      orientationVerified: true,
    });
    return evidenceInCapturedCoordinates(result, pixels.width, pixels.height, options.orientationQuarterTurns);
  } catch {
    return createEmptyResult(options.questionIds, ['image_preprocessing_failed']);
  }
}

function evidenceInCapturedCoordinates(
  result: OMRProcessingResult,
  originalWidth: number,
  originalHeight: number,
  turns: QuarterTurns,
): OMRProcessingResult {
  return {
    ...result,
    sourceSize: { width: originalWidth, height: originalHeight },
    fiducialCorners: result.fiducialCorners?.map((point) =>
      pointFromNormalizedImage(point, originalWidth, originalHeight, turns)),
    questionEvidence: Object.fromEntries(Object.entries(result.questionEvidence).map(([key, evidence]) => [key, {
      ...evidence,
      originalPolygon: evidence.originalPolygon?.map((point) =>
        pointFromNormalizedImage(point, originalWidth, originalHeight, turns)),
    }])),
  };
}

async function decodeImagePixels(
  uri: string,
): Promise<{ data: Uint8ClampedArray; width: number; height: number } | null> {
  try {
    const base64 = await FileSystem.readAsStringAsync(uri, {
      encoding: FileSystem.EncodingType.Base64,
    });
    const binary = atob(base64);
    const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
    const decoded = decodePng(bytes);
    const pixelCount = decoded.width * decoded.height;
    const rgba = new Uint8ClampedArray(pixelCount * 4);
    for (let index = 0; index < pixelCount; index += 1) {
      const source = index * decoded.channels;
      const target = index * 4;
      const grayscale = decoded.channels <= 2;
      rgba[target] = grayscale ? decoded.data[source] : decoded.data[source];
      rgba[target + 1] = grayscale ? decoded.data[source] : decoded.data[source + 1];
      rgba[target + 2] = grayscale ? decoded.data[source] : decoded.data[source + 2];
      rgba[target + 3] = decoded.channels === 2
        ? decoded.data[source + 1]
        : decoded.channels === 4
          ? decoded.data[source + 3]
          : 255;
    }
    return { data: rgba, width: decoded.width, height: decoded.height };
  } catch {
    return null;
  }
}

/** Manual edits are explicit human evidence, never machine confidence. */
export function createManualOMRResult(
  answers: Record<string, number | null>,
  questionIds: number[],
): OMRProcessingResult {
  const confidences: Record<string, number> = {};
  const fillRatios: Record<string, number[]> = {};
  const markTypes: OMRProcessingResult['markTypes'] = {};
  const questionEvidence: OMRProcessingResult['questionEvidence'] = {};
  for (const questionId of questionIds) {
    const key = String(questionId);
    confidences[key] = answers[key] === null ? 0 : 1;
    fillRatios[key] = [];
    markTypes[key] = answers[key] === null ? 'blank' : 'answered';
    questionEvidence[key] = {
      action: 'accept',
      reasons: ['manual_confirmation'],
      roi: { x: 0, y: 0, w: 0, h: 0 },
    };
  }
  return {
    answers,
    confidences,
    fillRatios,
    markTypes,
    multipleMarks: {},
    questionEvidence,
    overallConfidence: 1,
    fiducialConfidence: 0,
    fiducialCount: 0,
    usedHomography: false,
    processedAt: new Date().toISOString(),
    flaggedForReview: [],
    needsRescan: false,
    fiducialCorners: [],
    action: 'review',
    processingPath: 'manual',
    reasons: ['manual_confirmation'],
    imageQuality: null,
    reprojectionError: null,
    orientationDegrees: null,
    scaleRatio: null,
    sourceSize: null,
  };
}
