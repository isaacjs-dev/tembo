<?php

namespace App\Services;

use App\Models\ActivityAttempt;
use App\Models\ActivityResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityGraderService
{
    public function grade(ActivityAttempt $attempt): ActivityAttempt
    {
        return DB::transaction(function () use ($attempt): ActivityAttempt {
            $attempt = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($attempt->status, ['submitted', 'graded'], true)) {
                return $attempt->load('responses');
            }
            abort_unless($attempt->status === 'in_progress', 409);
            $attempt->load('responses');
            $questions = collect($attempt->content_snapshot['questions'] ?? []);
            $responses = $attempt->responses->keyBy('snapshot_question_key');
            $hasManual = $questions->isEmpty()
                || data_get($attempt->content_snapshot, 'activity.modality') === 'paper';

            foreach ($questions as $question) {
                $key = (int) $question['key'];
                $response = $responses->get($key) ?? ActivityResponse::create([
                    'activity_attempt_id' => $attempt->id, 'snapshot_question_key' => $key,
                    'question_id' => $question['question_id'] ?? null, 'answer' => null,
                ]);
                if (($question['type'] ?? null) === 'essay') {
                    $hasManual = true;

                    continue;
                }
                if (data_get($attempt->content_snapshot, 'activity.modality') === 'paper') {
                    continue;
                }
                $correct = $this->isCorrect($question, $response->answer);
                $response->update([
                    'is_correct' => $correct,
                    'points_awarded' => $correct ? (float) ($question['points'] ?? 0) : 0,
                    'answered_at' => $response->answered_at ?: now(),
                ]);
            }

            $attempt->refresh()->load('responses');
            $attempt->update([
                'status' => $hasManual ? 'submitted' : 'graded',
                'score' => (float) $attempt->responses->sum('points_awarded'),
                'submitted_at' => $attempt->submitted_at ?: now(),
                'graded_at' => $hasManual ? null : now(),
            ]);

            return $attempt->fresh('responses');
        }, 3);
    }

    private function isCorrect(array $question, ?array $answer): bool
    {
        $value = data_get($answer, 'value');
        $content = is_array($question['content'] ?? null) ? $question['content'] : [];

        return match ($question['type'] ?? null) {
            'multiple_choice', 'true_false' => (string) $value === (string) ($content['correct_option'] ?? ''),
            default => Str::of((string) $value)->squish()->lower()->value() === Str::of((string) ($content['correct_answer'] ?? ''))->squish()->lower()->value(),
        };
    }
}
