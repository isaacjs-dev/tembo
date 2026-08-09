<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ExamPresentationService
{
    public const MODES = ['full', 'paginated', 'auto'];

    /**
     * @param  Collection<int, mixed>  $questions
     * @param  array<string, mixed>  $settings
     * @return Collection<int, Collection<int, mixed>>
     */
    public function blocks(Collection $questions, array $settings): Collection
    {
        $mode = in_array($settings['digital_presentation'] ?? null, self::MODES, true)
            ? $settings['digital_presentation']
            : 'auto';

        if ($mode === 'full' || $questions->isEmpty()) {
            return collect([$questions->values()]);
        }

        if ($mode === 'paginated') {
            $perPage = min(20, max(1, (int) ($settings['questions_per_page'] ?? 5)));

            return $questions->chunk($perPage)->map->values()->values();
        }

        return $this->automaticBlocks($questions);
    }

    /** @param Collection<int, mixed> $questions */
    private function automaticBlocks(Collection $questions): Collection
    {
        $blocks = collect();
        $current = collect();
        $weight = 0;

        foreach ($questions as $question) {
            $content = is_array($question->content ?? null) ? $question->content : [];
            $questionWeight = mb_strlen(strip_tags((string) ($content['statement'] ?? '')))
                + collect($content['options'] ?? [])->sum(fn ($option): int => mb_strlen(strip_tags((string) $option)))
                + (($question->type ?? null) === 'essay' ? 900 : 250);

            if ($current->isNotEmpty() && ($current->count() >= 6 || $weight + $questionWeight > 3500)) {
                $blocks->push($current);
                $current = collect();
                $weight = 0;
            }

            $current->push($question);
            $weight += $questionWeight;
        }

        if ($current->isNotEmpty()) {
            $blocks->push($current);
        }

        return $blocks->values();
    }
}
