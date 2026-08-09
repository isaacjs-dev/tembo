export function mapVisualAnswersToOriginalOptions(
  answers: Record<string, number | null>,
  optionsMap?: Record<string, number[] | null>
): Record<string, number | null> {
  return Object.fromEntries(
    Object.entries(answers).map(([questionId, visualOption]) => {
      if (visualOption === null) return [questionId, null];
      const map = optionsMap?.[questionId];

      return [questionId, map?.[visualOption] ?? visualOption];
    })
  );
}

export function mapQuestionValuesToPrintedPositions<T>(
  values: Record<string, T>,
  questionOrder: number[] | undefined,
  contractVersion: number
): Record<string, T> {
  if (contractVersion < 4 || !questionOrder?.length) {
    return { ...values };
  }

  const mapped: Record<string, T> = {};
  for (const [questionId, value] of Object.entries(values)) {
    const position = questionOrder.indexOf(Number(questionId));
    mapped[String(position >= 0 ? position + 1 : questionId)] = value;
  }

  return mapped;
}
