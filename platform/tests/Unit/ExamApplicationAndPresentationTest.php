<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Services\ExamApplicationModeService;
use App\Services\ExamPresentationService;
use PHPUnit\Framework\TestCase;

class ExamApplicationAndPresentationTest extends TestCase
{
    public function test_explicit_and_legacy_modes_resolve_their_capabilities(): void
    {
        $service = new ExamApplicationModeService;
        $expected = [
            'online' => [true, false, false, false],
            'printed_digital' => [true, true, false, false],
            'printed_omr' => [false, true, true, false],
            'offline_omr' => [false, true, true, true],
            'hybrid' => [true, true, true, false],
            'paper' => [false, true, true, false],
        ];

        foreach ($expected as $mode => $capabilities) {
            $exam = new Exam(['settings' => ['application_mode' => $mode]]);
            $resolved = $service->capabilities($exam);
            $this->assertSame($capabilities, [
                $resolved['digital'],
                $resolved['print'],
                $resolved['omr'],
                $resolved['offline'],
            ]);
        }
    }

    public function test_full_paginated_and_automatic_presentations_build_deterministic_blocks(): void
    {
        $questions = collect(range(1, 7))->map(fn (int $id): object => (object) [
            'id' => $id,
            'type' => $id === 7 ? 'essay' : 'multiple_choice',
            'content' => [
                'statement' => str_repeat("Questão {$id} ", $id === 6 ? 300 : 5),
                'options' => ['A', 'B', 'C', 'D'],
            ],
        ]);
        $service = new ExamPresentationService;

        $this->assertCount(1, $service->blocks($questions, ['digital_presentation' => 'full']));
        $this->assertSame([3, 3, 1], $service
            ->blocks($questions, ['digital_presentation' => 'paginated', 'questions_per_page' => 3])
            ->map->count()->all());
        $automatic = $service->blocks($questions, ['digital_presentation' => 'auto']);
        $this->assertGreaterThan(1, $automatic->count());
        $this->assertSame($questions->pluck('id')->all(), $automatic->flatten(1)->pluck('id')->all());
    }
}
