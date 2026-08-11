<?php

namespace Tests\Unit;

use App\Services\OmrPageGeometryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OmrPageGeometryServiceTest extends TestCase
{
    #[DataProvider('goldenCases')]
    public function test_it_matches_the_cross_runtime_golden_contract(array $case): void
    {
        $questions = collect(str_split($case['option_counts']))->map(fn (string $count): array => [
            'type' => $count === '0' ? 'essay' : ($count === '2' ? 'true_false' : 'multiple_choice'),
            'option_count' => (int) $count,
        ]);

        $geometry = (new OmrPageGeometryService)->build(
            $case['layout'],
            $questions,
            $case['q_start'],
            $case['template_id'],
            $case['template_version']
        );

        $this->assertSame(1, $geometry['contract_version']);
        $this->assertSame('page-local-zero-based', $geometry['index_basis']);
        $this->assertSame($case['expected']['g'], $geometry['contract']['g']);
        $this->assertSame($case['expected']['rpp'], $geometry['contract']['rpp']);
        $this->assertSame($case['option_counts'], $geometry['contract']['oc']);
        $this->assertSame($case['q_start'], $geometry['contract']['qs']);
        $this->assertSame($case['expected']['q_end'], $geometry['contract']['qe']);
        $this->assertSame($case['expected']['geometry_hash'], $geometry['geometry_hash']);
        $this->assertEquals(
            $case['expected']['frame'],
            array_values($geometry['frame'])
        );
        $this->assertEquals(
            $case['expected']['first_bubble'],
            array_values(array_intersect_key($geometry['cells'][0]['bubbles'][0], array_flip(['x', 'y'])))
        );
        $lastCell = $geometry['cells'][array_key_last($geometry['cells'])];
        $lastBubble = $lastCell['bubbles'][array_key_last($lastCell['bubbles'])];
        $this->assertEquals(
            $case['expected']['last_bubble'],
            array_values(array_intersect_key($lastBubble, array_flip(['x', 'y'])))
        );
    }

    public function test_it_fails_closed_for_unsafe_or_unsupported_geometry(): void
    {
        $this->expectException(\RuntimeException::class);

        (new OmrPageGeometryService)->build(
            ['columns' => 1, 'rows_per_column' => 1, 'frame_top_mm' => 20],
            [['type' => 'multiple_choice', 'option_count' => 5]],
            1,
            1,
            1
        );
    }

    /** @return array<string, array{0: array}> */
    public static function goldenCases(): array
    {
        $path = dirname(__DIR__, 3).'/contracts/omr/card-page-geometry.v1.json';
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return collect($fixture['cases'])
            ->mapWithKeys(fn (array $case): array => [$case['name'] => [$case]])
            ->all();
    }
}
