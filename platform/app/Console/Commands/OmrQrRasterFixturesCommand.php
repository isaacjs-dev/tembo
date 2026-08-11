<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Services\OmrQrRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OmrQrRasterFixturesCommand extends Command
{
    protected $signature = 'omr:qr-raster-fixtures {--output= : Caminho do PDF A4 de regressão}';

    protected $description = 'Emite fixtures SVG determinísticas para a regressão raster do QR OMR';

    public function handle(OmrQrRendererService $renderer): int
    {
        $vectors = json_decode(
            file_get_contents(base_path('../contracts/omr/qr-contract.vectors.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $vectorsByName = collect($vectors['vectors'])->keyBy('name');
        $current = $vectorsByName['current-v5']['payload'];
        $maximum = array_replace($current, [
            'e' => 2147483647,
            'c' => 2147483647,
            'h' => str_repeat('h', 40),
            'p' => 1,
            'pt' => 1,
            'qs' => 1,
            'qe' => 80,
            'rpp' => 20,
            'tpl_id' => 2147483647,
            'tpl_v' => 9999,
            'g' => [645, 606, 2360, 606, 226, 306],
            'oc' => str_repeat('5', 80),
            'chk' => str_repeat('A', 22),
        ]);

        $cases = collect([
            'legacy-v3' => $vectorsByName['legacy-v3']['payload'],
            'early-v4' => $vectorsByName['early-v4']['payload'],
            'full-v4' => $vectorsByName['full-v4']['payload'],
            'current' => $current,
            'maximum-supported-page' => $maximum,
        ])->map(function (array $payload, string $name) use ($renderer): array {
            $encoded = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            $rendered = $renderer->renderWithProfile($encoded);

            return [
                'name' => $name,
                'payload' => $payload,
                'encoded' => $encoded,
                'svg_base64' => base64_encode($rendered['svg']),
                'profile' => $rendered['profile'],
            ];
        })->values()->all();

        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            $directory = dirname($output);
            File::ensureDirectoryExists($directory);
            File::put($output, $this->productionViewPdf($cases));
        }

        $this->line(json_encode([
            'constraints' => $renderer->constraints(),
            'cases' => $cases,
            'pdf_path' => is_string($output) && $output !== '' ? $output : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /** @param list<array{name:string,encoded:string,svg_base64:string,profile:array<string,mixed>}> $cases */
    private function productionViewPdf(array $cases): string
    {
        $exam = (new Exam)->forceFill(['title' => 'Regressão física QR OMR']);
        $exam->setRelation('organization', null);
        $allCopiesData = collect($cases)->map(function (array $case, int $index): array {
            $copy = (new ExamCopy)->forceFill(['copy_number' => $index + 1]);

            return [
                'copy' => $copy,
                'template' => null,
                'pagesData' => [[
                    'page' => 1,
                    'totalPages' => 1,
                    'qStart' => 1,
                    'qEnd' => 80,
                    'questions' => collect(),
                    'qrBase64' => $case['svg_base64'],
                    'qrPayload' => $case['payload'],
                    'qrPrint' => $case['profile'],
                    'geometry' => [
                        'frame' => ['x' => 12.0, 'y' => 56.0, 'w' => 186.0, 'h' => 225.0, 'fid' => 4.76],
                        'cells' => [],
                        'bubbleMm' => 4.2,
                    ],
                ]],
            ];
        })->all();

        return Pdf::loadView('pdf.answer-sheet-essential', [
            'exam' => $exam,
            'sheetType' => null,
            'layout' => [],
            'allCopiesData' => $allCopiesData,
        ])->setPaper('a4', 'portrait')->output();
    }
}
