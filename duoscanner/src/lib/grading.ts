import type { ExamCopy, QuestionData } from '@/types/exam';
import type { GradingResult } from '@/types/scan';

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

/**
 * Grade detected answers against the answer key.
 *
 * The key challenge is reverse-mapping shuffled options:
 * - The printed sheet has options in shuffled order (via ExamCopy.options_map)
 * - The student fills bubble at visual position (0=A, 1=B, etc.)
 * - options_map[questionId][visualPosition] gives the original option index
 * - We compare the original option index against Question.correct_option
 */
export function gradeAnswers(
  detectedAnswers: Record<string, number | null>,
  copy: ExamCopy,
  questions: QuestionData[]
): { results: GradingResult[]; totalScore: number; maxScore: number } {
  const gradingQuestions = copy.question_snapshot?.length ? copy.question_snapshot : questions;
  const results: GradingResult[] = [];
  let totalScore = 0;
  let maxScore = 0;

  // Process questions in the order they appear in the copy
  const orderedQuestionIds = (copy.questions_map && copy.questions_map.length > 0)
    ? copy.questions_map
    : gradingQuestions.map((q) => q.id);

  orderedQuestionIds.forEach((qId, index) => {
    const question = gradingQuestions.find((q) => q.id === qId);
    if (!question || question.type === 'essay') return;

    maxScore += question.points;

    const visualAnswer = detectedAnswers[String(qId)] ?? null;

    // Reverse-map: visual position -> original option index
    let originalAnswer: number | null = null;
    const optMap = copy.options_map?.[String(qId)];
    if (visualAnswer !== null) {
      if (optMap && optMap[visualAnswer] !== undefined) {
        originalAnswer = optMap[visualAnswer];
      } else {
        // No shuffling — visual position IS the original position
        originalAnswer = visualAnswer;
      }
    }

    const isCorrect =
      originalAnswer !== null &&
      question.correct_option !== null &&
      originalAnswer === question.correct_option;

    const points = isCorrect ? question.points : 0;
    totalScore += points;

    results.push({
      questionId: qId,
      questionNumber: index + 1,
      detectedOptionIndex: visualAnswer,
      originalOptionIndex: originalAnswer,
      correctOptionIndex: question.correct_option,
      isCorrect,
      points,
      confidence: 1.0, // Will be overridden by OMR processor
    });
  });

  return { results, totalScore, maxScore };
}

export function optionIndexToLetter(index: number | null): string {
  if (index === null || index < 0 || index >= LETTERS.length) return '?';
  return LETTERS[index];
}

export function getScoreMessage(score: number, maxScore: number): string {
  const percentage = maxScore > 0 ? (score / maxScore) * 100 : 0;
  if (percentage >= 90) return 'Excelente desempenho!';
  if (percentage >= 70) return 'Bom desempenho!';
  if (percentage >= 50) return 'Desempenho regular.';
  return 'Precisa melhorar.';
}
