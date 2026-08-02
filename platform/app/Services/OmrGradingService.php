<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamCopy;
use App\Models\ExamSubmission;
use App\Models\OmrScan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OmrGradingService
{
    /**
     * Normaliza uma resposta para formato canônico comparável com o gabarito.
     *
     * Para true_false:
     *   Aceita: true/false (bool), "true"/"false", "1"/"0", 1/0, "V"/"F", "T"/"F"
     *   Retorna: int 1 (verdadeiro) ou int 0 (falso)
     *
     * Para multiple_choice:
     *   Aceita: "A".."E" (letras), 0..4 (índices int ou string)
     *   Retorna: int 0..4 (índice numérico)
     *
     * Para branco/nulo/vazio:
     *   Retorna: null
     *
     * Para inválido:
     *   Retorna: ['invalid' => true, 'raw' => $answer]
     */
    public static function normalizeAnswer(mixed $answer, string $questionType, array $optionsContext = []): mixed
    {
        // Branco / nulo / vazio
        if ($answer === null || $answer === '' || $answer === '—') {
            return null;
        }

        if ($questionType === 'true_false') {
            return self::normalizeTrueFalse($answer);
        }

        if ($questionType === 'multiple_choice') {
            return self::normalizeMultipleChoice($answer, $optionsContext);
        }

        // Tipo desconhecido (essay, etc.) — não normalizar
        return null;
    }

    /**
     * Normaliza resposta V/F para int 1 (true) ou int 0 (false).
     */
    private static function normalizeTrueFalse(mixed $answer): mixed
    {
        // Bool nativo
        if ($answer === true || $answer === false) {
            return $answer ? 1 : 0;
        }

        // Normalizar string para lowercase
        if (is_string($answer)) {
            $lower = strtolower(trim($answer));

            $trueValues = ['true', '1', 'v', 't', 'verdadeiro', 'yes', 'sim'];
            $falseValues = ['false', '0', 'f', 'falso', 'no', 'não', 'nao'];

            if (in_array($lower, $trueValues, true)) {
                return 1;
            }
            if (in_array($lower, $falseValues, true)) {
                return 0;
            }

            // String não reconhecida
            return ['invalid' => true, 'raw' => $answer];
        }

        // Int/float
        if (is_numeric($answer)) {
            $val = (int) $answer;
            if ($val === 1 || $val === 0) {
                return $val;
            }

            return ['invalid' => true, 'raw' => $answer];
        }

        return ['invalid' => true, 'raw' => $answer];
    }

    /**
     * Normaliza resposta de múltipla escolha para int index (0..4).
     */
    private static function normalizeMultipleChoice(mixed $answer, array $optionsContext = []): mixed
    {
        $maxOptions = count($optionsContext) > 0 ? count($optionsContext) : 5;

        // Já é inteiro
        if (is_int($answer) || (is_string($answer) && ctype_digit($answer))) {
            $idx = (int) $answer;
            if ($idx >= 0 && $idx < $maxOptions) {
                return $idx;
            }

            return ['invalid' => true, 'raw' => $answer];
        }

        // Letra (A..E)
        if (is_string($answer) && strlen(trim($answer)) === 1) {
            $letter = strtoupper(trim($answer));
            $idx = ord($letter) - ord('A');
            if ($idx >= 0 && $idx < $maxOptions) {
                return $idx;
            }
        }

        return ['invalid' => true, 'raw' => $answer];
    }

    /**
     * Verifica se o valor normalizado é uma resposta inválida.
     */
    public static function isInvalidAnswer(mixed $normalized): bool
    {
        return is_array($normalized) && ($normalized['invalid'] ?? false) === true;
    }

    /**
     * Compara resposta normalizada com gabarito normalizado.
     * Ambos já devem estar no mesmo formato canônico (int).
     */
    private function compareAnswers(mixed $normalizedAnswer, mixed $normalizedCorrect, string $questionType): bool
    {
        if ($normalizedAnswer === null || $normalizedCorrect === null) {
            return false;
        }
        if (self::isInvalidAnswer($normalizedAnswer) || self::isInvalidAnswer($normalizedCorrect)) {
            return false;
        }

        // Comparação estrita entre valores canônicos (ambos int)
        return $normalizedAnswer === $normalizedCorrect;
    }

    /**
     * Compute the score of a set of answers without persisting to ExamSubmission.
     */
    public function gradeAnswers($examId, $copyId, array $answers): array
    {
        $exam = Exam::with('questions')->find($examId);
        $copy = $copyId ? ExamCopy::find($copyId) : null;

        $totalScore = 0;
        $totalPoints = 0;
        $details = [];

        if (! $exam) {
            return ['score' => 0, 'total_points' => 0, 'details' => []];
        }

        foreach ($exam->questions as $question) {
            $maxPts = $question->pivot->points ?? 1;
            $totalPoints += $maxPts;

            $rawAnswer = $answers[$question->id] ?? null;
            $visualAnswer = $rawAnswer;

            // Reverse-map shuffled options if copy has option mapping
            $originalAnswer = $visualAnswer;
            if ($copy && $visualAnswer !== null) {
                $optMap = $copy->options_map[$question->id] ?? null;
                if ($optMap && isset($optMap[$visualAnswer])) {
                    $originalAnswer = $optMap[$visualAnswer];
                }
            }

            // Normalizar resposta e gabarito
            $options = $question->content['options'] ?? [];
            $normalizedAnswer = self::normalizeAnswer($originalAnswer, $question->type, $options);
            $normalizedCorrect = self::normalizeAnswer(
                $question->content['correct_option'] ?? null,
                $question->type,
                $options
            );

            // Resposta inválida: logar e não pontuar
            $status = 'graded';
            if (self::isInvalidAnswer($normalizedAnswer)) {
                Log::warning('OmrGrading: resposta inválida', [
                    'question_id' => $question->id,
                    'type' => $question->type,
                    'raw' => $originalAnswer,
                ]);
                $status = 'invalid_answer';
            }
            if (self::isInvalidAnswer($normalizedCorrect)) {
                Log::warning('OmrGrading: gabarito inválido/ausente', [
                    'question_id' => $question->id,
                    'type' => $question->type,
                    'correct_raw' => $question->content['correct_option'] ?? null,
                ]);
                $status = 'invalid_gabarito';
            }

            $isCorrect = $this->compareAnswers($normalizedAnswer, $normalizedCorrect, $question->type);
            $points = $isCorrect ? $maxPts : 0;
            $totalScore += $points;

            $details[$question->id] = [
                'visual' => $visualAnswer,
                'original' => $originalAnswer,
                'normalized' => self::isInvalidAnswer($normalizedAnswer) ? null : $normalizedAnswer,
                'correct_normalized' => self::isInvalidAnswer($normalizedCorrect) ? null : $normalizedCorrect,
                'correct' => $isCorrect,
                'points' => $points,
                'status' => $status,
            ];
        }

        return ['score' => $totalScore, 'total_points' => $totalPoints, 'details' => $details];
    }

    /**
     * Grade a scan's answers and create/update the submission.
     *
     * @param  OmrScan  $scan  Must have exam relationship loaded
     * @param  array  $answers  Map of question_id => answer value
     * @param  ExamCopy|null  $copy  If provided, reverse-map shuffled answers to original indices
     */
    public function grade(OmrScan $scan, array $answers, ?ExamCopy $copy = null): array
    {
        $exam = $scan->exam;
        $studentId = (int) $scan->student_id;

        if (! $exam || ! $studentId) {
            throw new \InvalidArgumentException('A leitura OMR precisa de prova e estudante válidos para ser corrigida.');
        }

        return DB::transaction(function () use ($scan, $exam, $studentId, $answers, $copy): array {
            $lockedScan = OmrScan::query()->lockForUpdate()->findOrFail($scan->id);
            $submission = null;

            if ($lockedScan->exam_submission_id) {
                $mappedSubmission = ExamSubmission::query()
                    ->with('answers')
                    ->find($lockedScan->exam_submission_id);

                $sameContext = $mappedSubmission
                    && (int) $mappedSubmission->exam_id === (int) $exam->id
                    && (int) $mappedSubmission->user_id === $studentId;

                if ($sameContext && $mappedSubmission->status === 'in_progress') {
                    $submission = $mappedSubmission;
                } elseif (
                    $sameContext
                    && $this->answersMatchSubmission($mappedSubmission, $exam, $answers, $copy)
                ) {
                    return [
                        'submission' => $mappedSubmission,
                        'score' => (float) ($mappedSubmission->score ?? 0),
                        'total_points' => (float) (
                            $lockedScan->total_points
                            ?? $exam->questions->sum(fn ($question) => (float) ($question->pivot->points ?? 0))
                        ),
                        'details' => $lockedScan->grading_details ?? [],
                    ];
                } else {
                    // Reassignment or a corrected answer set creates a new immutable attempt.
                    $lockedScan->update(['exam_submission_id' => null]);
                }
            }

            if (! $submission) {
                // Locking the student serializes attempt-number allocation even when no
                // previous submission row exists yet.
                User::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
                $lastAttempt = (int) ExamSubmission::query()
                    ->where('exam_id', $exam->id)
                    ->where('user_id', $studentId)
                    ->max('attempt_number');

                $submission = ExamSubmission::create([
                    'exam_id' => $exam->id,
                    'user_id' => $studentId,
                    'attempt_number' => $lastAttempt + 1,
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'client_token' => (string) Str::uuid(),
                ]);

                $lockedScan->update(['exam_submission_id' => $submission->id]);
            }

            $result = $this->persistGrade($submission, $exam, $answers, $copy);

            $lockedScan->update([
                'exam_submission_id' => $submission->id,
                'score' => $result['score'],
                'total_points' => $result['total_points'],
                'grading_details' => $result['details'],
            ]);
            $scan->setAttribute('exam_submission_id', $submission->id);

            return $result;
        }, 3);
    }

    private function persistGrade(
        ExamSubmission $submission,
        Exam $exam,
        array $answers,
        ?ExamCopy $copy
    ): array {
        $totalScore = 0;
        $totalPoints = 0;
        $details = [];

        foreach ($exam->questions as $question) {
            $visualAnswer = $answers[$question->id] ?? null;

            // Reverse-map if copy has shuffled options
            $originalAnswer = $visualAnswer;
            if ($copy && $visualAnswer !== null) {
                $optMap = $copy->options_map[$question->id] ?? null;
                if ($optMap && isset($optMap[$visualAnswer])) {
                    $originalAnswer = $optMap[$visualAnswer];
                }
            }

            // Normalizar resposta e gabarito
            $options = $question->content['options'] ?? [];
            $normalizedAnswer = self::normalizeAnswer($originalAnswer, $question->type, $options);
            $normalizedCorrect = self::normalizeAnswer(
                $question->content['correct_option'] ?? null,
                $question->type,
                $options
            );

            $isCorrect = $this->compareAnswers($normalizedAnswer, $normalizedCorrect, $question->type);
            $maxPts = $question->pivot->points ?? 1;
            $points = $isCorrect ? $maxPts : 0;
            $totalScore += $points;
            $totalPoints += $maxPts;

            $status = 'graded';
            if (self::isInvalidAnswer($normalizedAnswer)) {
                $status = 'invalid_answer';
            }
            if (self::isInvalidAnswer($normalizedCorrect)) {
                $status = 'invalid_gabarito';
            }

            $details[$question->id] = [
                'visual' => $visualAnswer,
                'original' => $originalAnswer,
                'normalized' => self::isInvalidAnswer($normalizedAnswer) ? null : $normalizedAnswer,
                'correct_normalized' => self::isInvalidAnswer($normalizedCorrect) ? null : $normalizedCorrect,
                'correct' => $isCorrect,
                'points' => $points,
                'status' => $status,
            ];

            ExamAnswer::updateOrCreate(
                ['exam_submission_id' => $submission->id, 'question_id' => $question->id],
                [
                    'answer_data' => [
                        'selected' => self::isInvalidAnswer($normalizedAnswer) ? null : $normalizedAnswer,
                        'visual' => $visualAnswer,
                        'original_raw' => $originalAnswer,
                    ],
                    'is_correct' => $isCorrect,
                    'points_awarded' => $points,
                ]
            );
        }

        $submission->update([
            'status' => 'graded',
            'score' => $totalScore,
            'finished_at' => now(),
        ]);

        return [
            'submission' => $submission,
            'score' => $totalScore,
            'total_points' => $totalPoints,
            'details' => $details,
        ];
    }

    private function answersMatchSubmission(
        ExamSubmission $submission,
        Exam $exam,
        array $answers,
        ?ExamCopy $copy
    ): bool {
        $storedAnswers = $submission->answers->keyBy('question_id');

        if ($storedAnswers->count() !== $exam->questions->count()) {
            return false;
        }

        foreach ($exam->questions as $question) {
            $visualAnswer = $answers[$question->id] ?? null;
            $originalAnswer = $visualAnswer;

            if ($copy && $visualAnswer !== null) {
                $optionMap = $copy->options_map[$question->id] ?? null;
                if ($optionMap && isset($optionMap[$visualAnswer])) {
                    $originalAnswer = $optionMap[$visualAnswer];
                }
            }

            $normalized = self::normalizeAnswer(
                $originalAnswer,
                $question->type,
                $question->content['options'] ?? []
            );
            $normalized = self::isInvalidAnswer($normalized) ? null : $normalized;
            $stored = $storedAnswers->get($question->id);

            if (! $stored || data_get($stored->answer_data, 'selected') !== $normalized) {
                return false;
            }
        }

        return true;
    }
}
