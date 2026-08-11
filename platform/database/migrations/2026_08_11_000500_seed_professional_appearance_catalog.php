<?php

use App\Models\AppearanceTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->catalog() as $item) {
                if (DB::table('appearance_templates')->where('slug', $item['slug'])->exists()) {
                    throw new RuntimeException("Catálogo não aplicado: o slug {$item['slug']} já existe.");
                }

                $now = now();
                $templateId = DB::table('appearance_templates')->insertGetId([
                    'kind' => $item['kind'],
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'visibility_scope' => 'system',
                    'is_system' => true,
                    'current_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $payload = ['definition' => $item['definition'], 'assets' => []];
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                DB::table('appearance_template_versions')->insert([
                    'appearance_template_id' => $templateId,
                    'version' => 1,
                    'schema_version' => 1,
                    'definition' => json_encode($item['definition'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'assets' => json_encode([], JSON_THROW_ON_ERROR),
                    'content_hash' => hash('sha256', $encoded),
                    'change_summary' => 'Catálogo profissional inicial do sistema.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        $slugs = array_column($this->catalog(), 'slug');
        $templateIds = DB::table('appearance_templates')->whereIn('slug', $slugs)->pluck('id');
        $versionIds = DB::table('appearance_template_versions')
            ->whereIn('appearance_template_id', $templateIds)->pluck('id');

        if (DB::table('exams')->whereIn('assessment_layout_version_id', $versionIds)
            ->orWhereIn('assessment_header_version_id', $versionIds)->exists()) {
            throw new RuntimeException('Rollback recusado: o catálogo profissional está vinculado a Avaliações.');
        }
        if (DB::table('template_defaults')->where('template_type', AppearanceTemplate::class)
            ->whereIn('template_id', $templateIds)->exists()) {
            throw new RuntimeException('Rollback recusado: o catálogo profissional possui padrões configurados.');
        }

        DB::transaction(function () use ($templateIds): void {
            DB::table('appearance_template_versions')->whereIn('appearance_template_id', $templateIds)->delete();
            DB::table('appearance_templates')->whereIn('id', $templateIds)->delete();
        });
    }

    /** @return array<int, array{kind:string,name:string,slug:string,definition:array<string,mixed>}> */
    private function catalog(): array
    {
        $layout = fn (string $name, string $slug, string $orientation, array $margins, int $columns, string $separator): array => [
            'kind' => 'assessment_layout',
            'name' => $name,
            'slug' => 'system-assessment-layout-'.$slug,
            'definition' => [
                'page' => ['size' => 'A4', 'orientation' => $orientation, 'margins_mm' => $margins],
                'questions' => ['columns' => $columns, 'separator' => $separator, 'avoid_break_inside' => true],
            ],
        ];
        $header = fn (string $name, string $slug, float $height, array $elements): array => [
            'kind' => 'assessment_header',
            'name' => $name,
            'slug' => 'system-assessment-header-'.$slug,
            'definition' => ['height_mm' => $height, 'elements' => $elements],
        ];
        $text = fn (string $token): array => ['type' => 'text', 'token' => $token];
        $field = fn (string $token): array => ['type' => 'field', 'token' => $token];
        $line = ['type' => 'line'];

        return [
            $layout('Editorial compacto', 'editorial-compact', 'portrait', [10, 10, 10, 10], 1, 'line'),
            $layout('Leitura confortável', 'comfortable-reading', 'portrait', [20, 18, 20, 18], 1, 'none'),
            $layout('Caderno em boxes', 'boxed-workbook', 'portrait', [14, 14, 14, 14], 1, 'box'),
            $layout('Objetiva em duas colunas', 'objective-two-columns', 'portrait', [12, 12, 12, 12], 2, 'line'),
            $layout('Modular em duas colunas', 'modular-two-columns', 'portrait', [14, 12, 14, 12], 2, 'box'),
            $layout('Seções livres', 'free-sections', 'portrait', [18, 14, 18, 14], 1, 'none'),
            $layout('Objetiva em grade', 'objective-grid', 'portrait', [10, 16, 10, 16], 2, 'box'),
            $layout('Econômico', 'economical', 'portrait', [8, 8, 8, 8], 2, 'none'),
            $layout('Margens amplas', 'wide-margins', 'portrait', [24, 22, 24, 22], 1, 'line'),

            $header('Institucional completo', 'institutional-complete', 52, [
                $text('institution.name'), $line, $text('assessment.title'), $field('subject.name'),
                $field('student.name'), $field('student.registration'),
            ]),
            $header('Minimalista centrado', 'minimal-centered', 24, [
                $text('assessment.title'), $line, $field('student.name'),
            ]),
            $header('Professor e disciplina', 'teacher-subject', 40, [
                $text('assessment.title'), $text('teacher.name'), $field('subject.name'), $line, $field('student.name'),
            ]),
            $header('Turma em foco', 'class-focus', 42, [
                $text('institution.name'), $text('assessment.title'), $field('class.name'), $field('student.name'),
            ]),
            $header('Identificação completa', 'complete-identification', 56, [
                $text('institution.name'), $text('assessment.title'), $field('student.name'),
                $field('student.registration'), $field('class.name'), $field('assessment.date'),
            ]),
            $header('Avaliação e período', 'assessment-period', 38, [
                $text('assessment.title'), $field('assessment.subtitle'), $field('assessment.period'),
                $field('assessment.date'), $field('student.name'),
            ]),
            $header('Linha editorial', 'editorial-line', 30, [
                $text('institution.name'), $line, $text('assessment.title'), $field('student.name'),
            ]),
            $header('Acadêmico compacto', 'academic-compact', 20, [
                $text('assessment.title'), $field('student.name'), $field('class.name'),
            ]),
            $header('Formal institucional', 'formal-institutional', 64, [
                $text('institution.name'), $line, $text('assessment.title'), $field('assessment.subtitle'),
                $field('subject.name'), $text('teacher.name'), $field('student.name'),
                $field('student.registration'), $field('assessment.date'),
            ]),
        ];
    }
};
