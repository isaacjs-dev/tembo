<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class ExamQuestionSnapshotService
{
    public function __construct(private readonly QuestionResourceSnapshotService $resourceSnapshots) {}

    /** @return array<int, array<string, mixed>> */
    public function fromQuestions(Collection $questions): array
    {
        return $questions->values()->map(fn ($question, int $index): array => [
            'id' => (int) $question->id,
            'type' => (string) $question->type,
            'content' => $question->content,
            'points' => (float) ($question->pivot->points ?? 0),
            'order' => (int) ($question->pivot->order ?? $index + 1),
            'discipline_id' => $question->discipline_id ? (int) $question->discipline_id : null,
            'discipline_name' => $question->discipline?->name,
            'resources' => $this->resourceSnapshots->forQuestion($question, true),
        ])->all();
    }
}
