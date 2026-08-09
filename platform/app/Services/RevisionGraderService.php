<?php

namespace App\Services;

use App\Models\RevisionItem;
use Illuminate\Support\Str;

class RevisionGraderService
{
    public function grade(RevisionItem $item, mixed $answer): array
    {
        $solution = $item->solution ?? [];
        $correct = match ($item->type) {
            'multiple_choice', 'true_false' => (string) $this->scalar($answer) === (string) ($solution['correct_option'] ?? ''),
            'matching' => $this->canonicalPairs($answer) === $this->canonicalPairs($solution['pairs'] ?? []),
            'ordering' => $this->stringList($answer) === $this->stringList($solution['order'] ?? []),
            'fill_blank', 'short_answer' => $this->accepted($answer, $solution['accepted_answers'] ?? []),
            'flashcard' => true,
            default => false,
        };

        return ['is_correct' => $correct, 'points_awarded' => $correct ? (float) $item->points : 0.0,
            'feedback' => $correct ? 'Resposta correta.' : ($item->explanation ?: 'Revise o conteúdo e tente novamente.')];
    }

    public function snapshot(RevisionItem $item): array
    {
        return ['id' => $item->id, 'type' => $item->type, 'prompt' => $item->prompt, 'content' => $item->content,
            'solution' => $item->solution, 'explanation' => $item->explanation, 'hints' => $item->hints,
            'points' => (float) $item->points, 'updated_at' => $item->updated_at?->toIso8601String()];
    }

    private function accepted(mixed $answer, array $accepted): bool
    {
        $value = $this->normalize((string) $this->scalar($answer));

        return $value !== '' && collect($accepted)->map(fn ($candidate) => $this->normalize((string) $candidate))->contains($value);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9\s]/', '', Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim($value)))));
    }

    private function scalar(mixed $answer): mixed
    {
        return is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
    }

    private function stringList(mixed $value): array
    {
        $value = $this->scalar($value);

        return array_map('strval', is_array($value) ? array_values($value) : []);
    }

    private function canonicalPairs(mixed $value): array
    {
        $value = $this->scalar($value);
        $pairs = is_array($value) ? $value : [];
        ksort($pairs);

        return array_map('strval', $pairs);
    }
}
