/**
 * Correction Engine — applies grading based on the active answer sheet type.
 *
 * This is the mobile-side equivalent of OmrGradingService.
 * It takes detected answers and applies grading rules according to the
 * configured answer sheet type (Essential or Detailed).
 */
import type { AnswerSheetTypeSlug } from './config-resolver';
import { getResolvedConfig } from './config-resolver';

export interface CorrectionInput {
  detectedAnswers: Record<number, number | null>; // questionNumber → optionIndex (0-4) or null
  confidences: Record<number, number>;              // questionNumber → confidence (0-1)
  answerKey: Record<number, number>;                 // questionNumber → correct optionIndex
  points?: Record<number, number>;                   // questionNumber → point weight (default: 1)
}

export interface CorrectionResult {
  score: number;
  totalPoints: number;
  percentage: number;
  correctCount: number;
  incorrectCount: number;
  blankCount: number;
  reviewCount: number;
  details: QuestionCorrectionDetail[];
  answerSheetType: AnswerSheetTypeSlug;
}

export interface QuestionCorrectionDetail {
  questionNumber: number;
  detectedOption: number | null;
  correctOption: number;
  isCorrect: boolean;
  isBlank: boolean;
  needsReview: boolean;
  confidence: number;
  points: number;
  earnedPoints: number;
}

/**
 * Applies correction to detected answers using the answer key.
 */
export function applyCorrection(input: CorrectionInput): CorrectionResult {
  const config = getResolvedConfig();
  const gradingConfig = config.gradingConfig ?? {};

  const confidenceThresholdReview = gradingConfig.confidence_threshold_review ?? 0.65;

  const details: QuestionCorrectionDetail[] = [];
  let score = 0;
  let totalPoints = 0;
  let correctCount = 0;
  let incorrectCount = 0;
  let blankCount = 0;
  let reviewCount = 0;

  for (const [qNumStr, correctOption] of Object.entries(input.answerKey)) {
    const qNum = parseInt(qNumStr, 10);
    const detectedOption = input.detectedAnswers[qNum] ?? null;
    const confidence = input.confidences[qNum] ?? 0;
    const questionPoints = input.points?.[qNum] ?? 1;

    totalPoints += questionPoints;

    const isBlank = detectedOption === null || detectedOption === -1;
    const isCorrect = !isBlank && detectedOption === correctOption;
    const needsReview = confidence < confidenceThresholdReview;
    const earnedPoints = isCorrect ? questionPoints : 0;

    if (isBlank) blankCount++;
    else if (isCorrect) correctCount++;
    else incorrectCount++;

    if (needsReview) reviewCount++;

    score += earnedPoints;

    details.push({
      questionNumber: qNum,
      detectedOption,
      correctOption,
      isCorrect,
      isBlank,
      needsReview,
      confidence,
      points: questionPoints,
      earnedPoints,
    });
  }

  return {
    score,
    totalPoints,
    percentage: totalPoints > 0 ? Math.round((score / totalPoints) * 100) : 0,
    correctCount,
    incorrectCount,
    blankCount,
    reviewCount,
    details: details.sort((a, b) => a.questionNumber - b.questionNumber),
    answerSheetType: config.answerSheetType,
  };
}

/**
 * Builds a CorrectionInput from cached exam data (for preloaded and hybrid modes).
 */
export function buildCorrectionFromCache(
  examData: {
    questions: Array<{ id: number; number: number; correct_option: number; points?: number }>;
  },
  detectedAnswers: Record<number, number | null>,
  confidences: Record<number, number>
): CorrectionInput {
  const answerKey: Record<number, number> = {};
  const points: Record<number, number> = {};

  for (const q of examData.questions) {
    answerKey[q.number] = q.correct_option;
    points[q.number] = q.points ?? 1;
  }

  return {
    detectedAnswers,
    confidences,
    answerKey,
    points,
  };
}
