<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\AnswerSheetGeneratorService;
use App\Services\ConfigPrecedenceResolver;
use App\Services\ExamPrintService;
use App\Services\OmrPageGeometryService;
use App\Support\OmrSystemTemplateCatalog;
use Database\Seeders\OmrTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OmrCardCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_ten_distinct_immutable_a4_templates_idempotently(): void
    {
        $this->seed(OmrTemplateSeeder::class);
        $this->seed(OmrTemplateSeeder::class);

        $templates = OmrTemplate::query()->where('is_system', true)->with('versions')->get();
        $this->assertCount(10, $templates);
        $this->assertSame(1, $templates->where('is_default', true)->count());
        $this->assertSame(10, $templates->where('is_active', true)->count());
        $this->assertSame(10, $templates->pluck('slug')->unique()->count());
        $this->assertSame(10, $templates->sum(fn (OmrTemplate $template): int => $template->versions->count()));
        $this->assertSame(10, $templates->pluck('versions.0.content_hash')->unique()->count());
        $this->assertTrue($templates->every(fn (OmrTemplate $template): bool => $template->paper_size === 'A4'
            && $template->orientation === 'portrait'
            && $template->max_questions === $template->columns * $template->rows_per_column));

        $standard = $templates->firstWhere('slug', 'sistema-padrao');
        $detailed = $templates->firstWhere('slug', 'sistema-detalhado');
        $this->assertSame('top_center', data_get($standard?->layout_config, 'qr_position'));
        $this->assertSame(6.0, (float) data_get($standard?->layout_config, 'fiducial_size_mm'));
        $this->assertSame(
            '5280a587330b380077cf26b6037394a88736352edca2960f8a2021c042b64a5c',
            hash('sha256', json_encode($standard?->layout_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        );
        $this->assertSame(50.0, (float) data_get($detailed?->layout_config, 'frame_top_mm'));
        $this->assertSame('CARTÃO RESPOSTA', data_get($standard?->header_config, 'title'));
    }

    public function test_every_catalog_definition_passes_the_canonical_geometry_at_maximum_capacity(): void
    {
        $geometry = app(OmrPageGeometryService::class);
        $hashes = [];

        foreach (OmrSystemTemplateCatalog::definitions() as $index => $definition) {
            $layout = $definition['layout'];
            $question = $layout['max_options'] === 2
                ? ['type' => 'true_false', 'option_count' => 2]
                : ['type' => 'multiple_choice', 'option_count' => $layout['max_options']];
            $page = $geometry->build(
                $layout,
                array_fill(0, $layout['max_questions'], $question),
                1,
                $index + 1,
                1,
            );

            $this->assertCount($layout['max_questions'], $page['cells'], $definition['slug']);
            $this->assertSame($layout['rows_per_column'], $page['contract']['rpp']);
            $this->assertSame($layout['max_questions'], $page['contract']['qe']);
            $hashes[] = $page['geometry_hash'];
        }

        $this->assertCount(10, array_unique($hashes));
    }

    public function test_every_system_template_renders_its_maximum_capacity_as_one_a4_pdf_page(): void
    {
        $organization = Organization::query()->create(['name' => 'Escola PDF A4', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação de capacidade máxima',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $multipleChoiceIds = collect();
        $trueFalseIds = collect();
        foreach (range(1, 80) as $number) {
            $multipleChoiceIds->push(Question::query()->create([
                'organization_id' => $organization->id,
                'owner_id' => $teacher->id,
                'type' => 'multiple_choice',
                'content' => [
                    'statement' => "Questão objetiva {$number}",
                    'options' => ['A', 'B', 'C', 'D', 'E'],
                    'correct_option' => 0,
                ],
                'visibility_scope' => 'private',
                'level' => 'medium',
            ])->id);
        }
        foreach (range(1, 60) as $number) {
            $trueFalseIds->push(Question::query()->create([
                'organization_id' => $organization->id,
                'owner_id' => $teacher->id,
                'type' => 'true_false',
                'content' => ['statement' => "Questão V/F {$number}", 'correct_option' => 0],
                'visibility_scope' => 'private',
                'level' => 'medium',
            ])->id);
        }
        $this->seed(OmrTemplateSeeder::class);

        foreach (OmrSystemTemplateCatalog::definitions() as $definition) {
            $template = OmrTemplate::query()->where('slug', $definition['slug'])->firstOrFail();
            $sourceIds = $definition['layout']['max_options'] === 2 ? $trueFalseIds : $multipleChoiceIds;
            $questionIds = $sourceIds->take($definition['layout']['max_questions'])->values();
            $exam->questions()->sync($questionIds->mapWithKeys(
                fn (int $id, int $index): array => [$id => ['points' => 1, 'order' => $index + 1]]
            )->all());
            $exam->unsetRelation('questions');

            app(AnswerSheetGeneratorService::class)->assertCompatible(
                $exam,
                $template,
                null,
                (int) $template->current_version,
            );
            $options = [
                'card_template_id' => $template->id,
                'card_template_version' => (int) $template->current_version,
                'template_snapshot' => $template->snapshotForVersion((int) $template->current_version),
            ];
            $copy = app(ExamPrintService::class)->generateCopies($exam, 1, $options)->firstOrFail();
            $result = app(AnswerSheetGeneratorService::class)->generate($exam, collect([$copy]), $template);
            $bytes = $result['pdf']->output();

            $this->assertStringStartsWith('%PDF-', $bytes, $definition['slug']);
            $this->assertSame(1, $result['pdf']->getDomPDF()->getCanvas()->get_page_count(), $definition['slug']);
        }
    }

    public function test_incompatible_binary_template_is_rejected_before_exam_or_copies_are_written(): void
    {
        Role::findOrCreate('teacher', 'web');
        $organization = Organization::query()->create(['name' => 'Escola Catálogo', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação com cinco alternativas',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão objetiva',
                'options' => ['A', 'B', 'C', 'D', 'E'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
            'level' => 'medium',
        ]);
        $exam->questions()->attach($question->id, ['points' => 1, 'order' => 1]);
        $this->seed(OmrTemplateSeeder::class);
        $binary = OmrTemplate::query()->where('slug', 'sistema-vf-ampliado-60')->firstOrFail();

        $response = $this->actingAs($teacher)->from(route('exams.show', $exam))->post(
            route('exams.exportAnswerSheet', $exam),
            ['quantity' => 1, 'card_template_id' => $binary->id],
        );

        $response->assertRedirect(route('exams.show', $exam));
        $response->assertSessionHasErrors('card_template_id');
        $this->assertNull($exam->fresh()->card_template_id);
        $this->assertSame(0, ExamCopy::query()->where('exam_id', $exam->id)->count());
    }

    public function test_preflight_uses_the_same_current_version_that_would_be_persisted(): void
    {
        Role::findOrCreate('teacher', 'web');
        $organization = Organization::query()->create(['name' => 'Escola Versões', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação vinculada à versão anterior',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão com cinco alternativas',
                'options' => ['A', 'B', 'C', 'D', 'E'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
            'level' => 'medium',
        ]);
        $exam->questions()->attach($question->id, ['points' => 1, 'order' => 1]);
        $layoutV1 = OmrSystemTemplateCatalog::definitions()[0]['layout'];
        $layoutV2 = array_replace($layoutV1, ['max_options' => 2]);
        $template = OmrTemplate::query()->create([
            'name' => 'Modelo versionado',
            'slug' => 'modelo-versionado-teste',
            'organization_id' => $organization->id,
            'created_by' => $teacher->id,
            'visibility_scope' => 'private',
            'layout_config' => $layoutV2,
            'corner_points_json' => [],
            'thresholds_json' => [],
            'max_questions' => 40,
            'max_columns' => 2,
            'columns' => 2,
            'rows_per_column' => 20,
            'max_options' => 2,
            'is_active' => true,
            'current_version' => 2,
        ]);
        OmrTemplateVersion::query()->create([
            'omr_template_id' => $template->id,
            'version' => 1,
            'layout_config' => $layoutV1,
            'header_config' => [],
        ]);
        OmrTemplateVersion::query()->create([
            'omr_template_id' => $template->id,
            'version' => 2,
            'layout_config' => $layoutV2,
            'header_config' => [],
        ]);
        $exam->forceFill(['card_template_id' => $template->id, 'card_template_version' => 1])->save();

        $response = $this->actingAs($teacher)->from(route('exams.show', $exam))->post(
            route('exams.exportAnswerSheet', $exam),
            ['quantity' => 1, 'card_template_id' => $template->id],
        );

        $response->assertRedirect(route('exams.show', $exam));
        $response->assertSessionHasErrors('card_template_id');
        $this->assertSame(1, $exam->fresh()->card_template_version);
        $this->assertSame(0, ExamCopy::query()->where('exam_id', $exam->id)->count());
    }

    public function test_invalid_answer_key_is_rejected_before_exam_or_copies_are_written(): void
    {
        Role::findOrCreate('teacher', 'web');
        $organization = Organization::query()->create(['name' => 'Escola Gabarito', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação com gabarito inválido',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão sem índice de resposta válido',
                'options' => ['A', 'B', 'C'],
                'correct_option' => 9,
            ],
            'visibility_scope' => 'private',
            'level' => 'medium',
        ]);
        $exam->questions()->attach($question->id, ['points' => 1, 'order' => 1]);
        $this->seed(OmrTemplateSeeder::class);
        $template = OmrTemplate::query()->where('slug', 'sistema-padrao')->firstOrFail();

        $response = $this->actingAs($teacher)->from(route('exams.show', $exam))->post(
            route('exams.exportAnswerSheet', $exam),
            ['quantity' => 1, 'card_template_id' => $template->id],
        );

        $response->assertRedirect(route('exams.show', $exam));
        $response->assertSessionHasErrors('card_template_id');
        $this->assertNull($exam->fresh()->card_template_id);
        $this->assertSame(0, ExamCopy::query()->where('exam_id', $exam->id)->count());
    }

    public function test_failure_after_preflight_rolls_back_template_binding_and_generated_copies(): void
    {
        Role::findOrCreate('teacher', 'web');
        $organization = Organization::query()->create(['name' => 'Escola Transacional', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação transacional',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão válida',
                'options' => ['A', 'B', 'C'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
            'level' => 'medium',
        ]);
        $exam->questions()->attach($question->id, ['points' => 1, 'order' => 1]);
        $this->seed(OmrTemplateSeeder::class);
        $template = OmrTemplate::query()->where('slug', 'sistema-padrao')->firstOrFail();

        $generator = Mockery::mock(AnswerSheetGeneratorService::class);
        $generator->shouldReceive('assertCompatible')->once();
        $failingPdf = new class
        {
            public function output(): string
            {
                throw new \RuntimeException('Falha controlada durante a renderização.');
            }
        };
        $generator->shouldReceive('generate')->once()->andReturn(['pdf' => $failingPdf, 'template' => $template]);
        $this->instance(AnswerSheetGeneratorService::class, $generator);

        $resolver = Mockery::mock(ConfigPrecedenceResolver::class);
        $resolver->shouldReceive('resolveWithTrace')->once()->andReturn(['effective_value' => 'hybrid']);
        $this->instance(ConfigPrecedenceResolver::class, $resolver);

        $response = $this->actingAs($teacher)->from(route('exams.show', $exam))->post(
            route('exams.exportAnswerSheet', $exam),
            ['quantity' => 1, 'card_template_id' => $template->id],
        );

        $response->assertRedirect(route('exams.show', $exam));
        $response->assertSessionHasErrors();
        $this->assertNull($exam->fresh()->card_template_id);
        $this->assertSame(0, ExamCopy::query()->where('exam_id', $exam->id)->count());
    }
}
