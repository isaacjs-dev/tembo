<?php

namespace App\Console\Commands;

use App\Models\OmrTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class OmrCalibrateCommand extends Command
{
    protected $signature = 'omr:calibrate
        {template_id : ID do template a calibrar}
        {--images-dir= : Diretório com imagens de amostra (relativo a storage/app/public)}
        {--sample-count=20 : Número mínimo de amostras para calibração}';

    protected $description = 'Roda calibração OMR usando N imagens de amostra e sugere thresholds ideais';

    public function handle(): int
    {
        $template = OmrTemplate::with('questions')->find($this->argument('template_id'));

        if (! $template) {
            $this->error("Template #{$this->argument('template_id')} não encontrado.");

            return 1;
        }

        $imagesDir = $this->option('images-dir');
        if (! $imagesDir) {
            $this->error('Informe --images-dir com o diretório das imagens de amostra.');

            return 1;
        }

        $fullPath = Storage::disk('public')->path($imagesDir);
        if (! is_dir($fullPath)) {
            $this->error("Diretório não encontrado: {$fullPath}");

            return 1;
        }

        // Listar imagens
        $images = glob($fullPath.'/*.{png,jpg,jpeg,PNG,JPG,JPEG}', GLOB_BRACE);
        $sampleCount = (int) $this->option('sample-count');

        if (count($images) < $sampleCount) {
            $this->warn('Encontradas apenas '.count($images)." imagens (mínimo recomendado: {$sampleCount}).");
            if (count($images) === 0) {
                $this->error('Nenhuma imagem encontrada.');

                return 1;
            }
        }

        $this->info("Calibrando template: {$template->name}");
        $this->info('Imagens: '.count($images));

        // Exportar template JSON
        $tempDir = storage_path('app/omr-temp/calibrate-'.$template->id);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $templatePath = $tempDir.'/template.json';
        file_put_contents($templatePath, json_encode($template->toEngineJson(), JSON_PRETTY_PRINT));

        // Processar cada imagem e coletar scores
        $allScores = [];
        $cornersFound = 0;
        $total = count($images);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($images as $imgPath) {
            $debugDir = $tempDir.'/debug_'.basename($imgPath, '.'.pathinfo($imgPath, PATHINFO_EXTENSION));

            $python = $this->findPython();
            $enginePath = base_path('omr-engine/omr_read.py');

            $result = Process::timeout(60)->run([
                $python, $enginePath,
                '--image', $imgPath,
                '--template', $templatePath,
                '--debug', $debugDir,
            ]);

            $json = json_decode($result->output(), true);

            if (is_array($json) && isset($json['answers'])) {
                foreach ($json['answers'] as $answer) {
                    foreach ($answer['scores'] ?? [] as $label => $score) {
                        $allScores[] = (float) $score;
                    }
                }
                if (($json['quality']['corners_found'] ?? 0) >= 4) {
                    $cornersFound++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (empty($allScores)) {
            $this->error('Nenhum score coletado. Verifique as imagens e o template.');

            return 1;
        }

        // Analisar distribuição de scores
        sort($allScores);
        $count = count($allScores);

        $percentile = function (float $p) use ($allScores, $count) {
            $idx = (int) floor($p * $count);

            return $allScores[min($idx, $count - 1)];
        };

        $this->info('=== RESULTADOS DA CALIBRAÇÃO ===');
        $this->info("Imagens processadas: {$total}");
        $this->info("Cantos detectados: {$cornersFound}/{$total} (".round($cornersFound / $total * 100).'%)');
        $this->info("Total de scores coletados: {$count}");
        $this->newLine();

        // Distribuição
        $this->info('Distribuição de fill scores:');
        $this->table(
            ['Percentil', 'Score'],
            [
                ['P5', round($percentile(0.05), 4)],
                ['P10', round($percentile(0.10), 4)],
                ['P25', round($percentile(0.25), 4)],
                ['P50 (mediana)', round($percentile(0.50), 4)],
                ['P75', round($percentile(0.75), 4)],
                ['P90', round($percentile(0.90), 4)],
                ['P95', round($percentile(0.95), 4)],
            ]
        );

        // Sugerir thresholds
        // blank: tudo abaixo de P25 (bolhas não preenchidas)
        // mark: tudo acima de P75 (bolhas preenchidas)
        // uncertain: zona entre P25 e P75
        $blankThreshold = round($percentile(0.15), 4);
        $uncertainLow = round($percentile(0.30), 4);
        $uncertainHigh = round($percentile(0.60), 4);
        $markThreshold = round($percentile(0.70), 4);

        // Ajustar se forem inconsistentes
        if ($markThreshold <= $uncertainHigh) {
            $markThreshold = round($uncertainHigh + 0.05, 4);
        }
        if ($uncertainLow <= $blankThreshold) {
            $uncertainLow = round($blankThreshold + 0.05, 4);
        }

        $this->newLine();
        $this->info('=== THRESHOLDS SUGERIDOS ===');
        $this->table(
            ['Threshold', 'Valor', 'Significado'],
            [
                ['blank', $blankThreshold, 'Score <= este valor = bolha vazia'],
                ['uncertain_low', $uncertainLow, 'Score >= este valor pode ser incerto'],
                ['uncertain_high', $uncertainHigh, 'Score < este valor ainda é incerto'],
                ['mark', $markThreshold, 'Score >= este valor = bolha preenchida'],
            ]
        );

        // Salvar?
        if ($this->confirm('Deseja salvar esses thresholds no template?', true)) {
            $template->update([
                'thresholds_json' => [
                    'mark' => $markThreshold,
                    'blank' => $blankThreshold,
                    'uncertain_low' => $uncertainLow,
                    'uncertain_high' => $uncertainHigh,
                ],
            ]);
            $this->info("Thresholds salvos no template #{$template->id}.");
        }

        // Limpar temp
        $this->cleanTempDir($tempDir);

        return 0;
    }

    private function findPython(): string
    {
        foreach (['python3', 'python'] as $cmd) {
            $result = Process::run([$cmd, '--version']);
            if ($result->successful()) {
                return $cmd;
            }
        }

        return 'python';
    }

    private function cleanTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
