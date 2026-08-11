<?php

namespace Tests\Feature;

use App\Models\AppearanceTemplate;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\TemplateDefault;
use App\Models\User;
use App\Services\AppearanceDefinitionSchema;
use App\Services\AppearanceTemplateService;
use App\Services\CanonicalPrintDocumentService;
use App\Services\ExamPrintService;
use App\Services\ExamWizardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppearanceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_catalog_has_ten_unique_valid_layouts_and_headers(): void
    {
        $schema = app(AppearanceDefinitionSchema::class);

        foreach (['assessment_layout', 'assessment_header'] as $kind) {
            $templates = AppearanceTemplate::query()
                ->where('is_system', true)->where('kind', $kind)->with('currentVersion')->get();

            $this->assertCount(10, $templates);
            $this->assertCount(10, $templates->pluck('name')->unique());
            $this->assertCount(10, $templates->pluck('slug')->unique());
            $this->assertCount(10, $templates->pluck('currentVersion.content_hash')->unique());
            foreach ($templates as $template) {
                $normalized = $schema->normalize($kind, $template->currentVersion->definition);
                $this->assertSame($normalized, $schema->normalize($kind, $normalized));
            }
        }

        $this->assertSame(
            20,
            AppearanceTemplate::query()->where('is_system', true)
                ->whereIn('kind', ['assessment_layout', 'assessment_header'])->count(),
        );
    }

    public function test_teacher_can_browse_and_autosave_an_exact_layout_and_header_version(): void
    {
        [$exam, $teacher] = $this->context();
        $layouts = AppearanceTemplate::query()->where('kind', 'assessment_layout')->with('currentVersion')->get();
        $headers = AppearanceTemplate::query()->where('kind', 'assessment_header')->with('currentVersion')->get();
        $layout = $layouts->firstWhere('slug', 'system-assessment-layout-objective-grid');
        $header = $headers->firstWhere('slug', 'system-assessment-header-formal-institutional');

        $page = $this->actingAs($teacher)
            ->withSession(['workspace_id' => $exam->organization_id])
            ->get(route('exams.edit', $exam));
        $page->assertOk();
        foreach ($layouts->merge($headers) as $template) {
            $page->assertSee($template->name);
        }

        $revision = data_get($exam->fresh()->settings, '_wizard.revision');
        $this->patchJson(route('exams.autosaveDraft', $exam), [
            'step' => 'appearance',
            'payload' => [
                'shuffle_questions' => false,
                'shuffle_options' => true,
                'assessment_layout_version_id' => $layout->currentVersion->id,
                'assessment_header_version_id' => $header->currentVersion->id,
            ],
            'revision' => $revision,
        ])->assertOk()->assertJsonPath('saved', true);

        $exam->refresh();
        $this->assertSame($layout->currentVersion->id, $exam->assessment_layout_version_id);
        $this->assertSame($header->currentVersion->id, $exam->assessment_header_version_id);
        $this->assertTrue((bool) data_get($exam->settings, 'shuffle_options'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'exam_appearance_changed',
            'model_type' => Exam::class,
            'model_id' => $exam->id,
        ]);

        $document = app(CanonicalPrintDocumentService::class)->preview($exam);
        $this->assertSame(2, $document['layout']['columns']);
        $this->assertSame('box', $document['layout']['separator']);
        $this->assertSame(64.0, $document['header']['height_mm']);
        $this->assertSame($header->currentVersion->id, data_get($document, 'appearance_snapshot.assessment_header.version_id'));
    }

    public function test_foreign_private_template_cannot_be_selected_by_forging_the_version_id(): void
    {
        [$exam, $teacher] = $this->context();
        $foreignOrganization = Organization::query()->create(['name' => 'Outro tenant', 'active' => true]);
        $foreignTeacher = $this->teacher($foreignOrganization, 'foreign@example.test');
        $source = AppearanceTemplate::query()->where('kind', 'assessment_layout')->where('is_system', true)->firstOrFail();
        $foreign = app(AppearanceTemplateService::class)->duplicate($source, $foreignTeacher, $foreignOrganization);
        $header = AppearanceTemplate::query()->where('kind', 'assessment_header')->where('is_system', true)
            ->with('currentVersion')->firstOrFail();

        $this->actingAs($teacher)
            ->withSession(['workspace_id' => $exam->organization_id])
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'appearance',
                'payload' => [
                    'shuffle_questions' => false,
                    'shuffle_options' => false,
                    'assessment_layout_version_id' => $foreign->currentVersion->id,
                    'assessment_header_version_id' => $header->currentVersion->id,
                ],
                'revision' => data_get($exam->fresh()->settings, '_wizard.revision'),
            ])->assertNotFound();

        $this->assertNull($exam->fresh()->assessment_layout_version_id);

        $this->actingAs($teacher)
            ->withSession(['workspace_id' => $exam->organization_id])
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'appearance',
                'payload' => [
                    'shuffle_questions' => false,
                    'shuffle_options' => false,
                    'assessment_layout_version_id' => $header->currentVersion->id,
                    'assessment_header_version_id' => $header->currentVersion->id,
                ],
                'revision' => data_get($exam->fresh()->settings, '_wizard.revision'),
            ])->assertStatus(422);
    }

    public function test_every_layout_generates_an_a4_pdf_and_remains_portrait_safe_in_combined_output(): void
    {
        [$exam] = $this->context();
        $layouts = AppearanceTemplate::query()->where('kind', 'assessment_layout')
            ->where('is_system', true)->with('currentVersion')->get();

        foreach ($layouts as $layout) {
            $exam->update(['assessment_layout_version_id' => $layout->currentVersion->id]);
            $document = app(CanonicalPrintDocumentService::class)->preview($exam->fresh());
            $html = view('exams.print.canonical-preview', compact('document'))->render();
            $pdf = Pdf::loadView('exams.print.canonical-pdf', compact('document'))->output();

            $this->assertStringContainsString('size: A4 portrait', $html, $layout->name);
            $this->assertStringStartsWith('%PDF-', $pdf, $layout->name);

            $copies = app(ExamPrintService::class)->generateCopies($exam->fresh(), 1, ['output_type' => 'both']);
            $copyDocument = app(CanonicalPrintDocumentService::class)->copy($exam->fresh(), $copies->first());
            $printDocuments = collect([$copies->first()->id => $copyDocument]);
            $cardPagesByCopy = [];
            $calibration = [];
            $options = [];
            $outputType = 'both';
            $combined = view('exams.pdf_advanced', compact(
                'exam', 'copies', 'printDocuments', 'cardPagesByCopy', 'calibration', 'options', 'outputType'
            ))->render();
            $this->assertSame(1, substr_count($combined, '@page'), $layout->name);
            $this->assertStringContainsString('@page { size: A4 portrait; margin: 10mm; }', $combined, $layout->name);
        }
    }

    public function test_every_header_renders_distinct_safe_html(): void
    {
        [$exam] = $this->context();
        $headers = AppearanceTemplate::query()->where('kind', 'assessment_header')
            ->where('is_system', true)->with('currentVersion')->get();
        $rendered = [];

        foreach ($headers as $header) {
            $exam->update(['assessment_header_version_id' => $header->currentVersion->id]);
            $document = app(CanonicalPrintDocumentService::class)->preview($exam->fresh());
            $html = view('exams.print.canonical-preview', compact('document'))->render();
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringContainsString('min-height: '.$document['header']['height_mm'].'mm', $html);
            foreach ($document['header']['elements'] as $element) {
                if ($element['type'] === 'text') {
                    $this->assertDoesNotMatchRegularExpression('/^_+$/', $element['value'], $header->name);
                }
            }
            $rendered[] = hash('sha256', json_encode($document['header'], JSON_THROW_ON_ERROR));
        }

        $this->assertCount(10, array_unique($rendered));
    }

    public function test_changing_catalog_selection_does_not_change_an_existing_copy(): void
    {
        [$exam] = $this->context();
        $service = app(CanonicalPrintDocumentService::class);
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1, ['output_type' => 'exam'])->first();
        $before = $service->copy($exam, $copy);
        $layout = AppearanceTemplate::query()->where('slug', 'system-assessment-layout-objective-grid')
            ->with('currentVersion')->firstOrFail();
        $header = AppearanceTemplate::query()->where('slug', 'system-assessment-header-formal-institutional')
            ->with('currentVersion')->firstOrFail();
        $exam->update([
            'assessment_layout_version_id' => $layout->currentVersion->id,
            'assessment_header_version_id' => $header->currentVersion->id,
        ]);

        $after = $service->copy($exam->fresh(), $copy->fresh());

        $this->assertSame($before['document_hash'], $after['document_hash']);
        $this->assertSame($before['appearance_snapshot'], $after['appearance_snapshot']);
    }

    public function test_catalog_migration_fails_on_slug_collision_and_refuses_unsafe_rollback(): void
    {
        $migration = require database_path('migrations/2026_08_11_000500_seed_professional_appearance_catalog.php');
        try {
            $migration->up();
            $this->fail('A migration aceitou colisão de slugs do catálogo.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('já existe', $exception->getMessage());
        }

        [$exam, $teacher] = $this->context();
        $layout = AppearanceTemplate::query()->where('slug', 'system-assessment-layout-objective-grid')
            ->with('currentVersion')->firstOrFail();
        $exam->update(['assessment_layout_version_id' => $layout->currentVersion->id]);
        try {
            $migration->down();
            $this->fail('Rollback removeu template referenciado por Avaliação.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('vinculado', $exception->getMessage());
        }

        $exam->update(['assessment_layout_version_id' => null]);
        TemplateDefault::query()->create([
            'organization_id' => $exam->organization_id,
            'user_id' => $teacher->id,
            'scope_key' => 'test-catalog-default',
            'kind' => 'assessment_layout',
            'template_type' => AppearanceTemplate::class,
            'template_id' => $layout->id,
            'template_version' => 1,
            'set_by' => $teacher->id,
        ]);
        try {
            $migration->down();
            $this->fail('Rollback removeu template configurado como padrão.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('padrões configurados', $exception->getMessage());
        }
    }

    /** @return array{Exam, User} */
    private function context(): array
    {
        $organization = Organization::query()->create(['name' => 'Escola Catálogo', 'active' => true]);
        $teacher = $this->teacher($organization, 'catalog@example.test');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação do catálogo',
            'status' => 'draft',
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Qual alternativa representa o resultado correto?',
                'options' => ['Alternativa A', 'Alternativa B', 'Alternativa C', 'Alternativa D'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 2, 'order' => 1]);
        app(ExamWizardService::class)->initialize($exam, 'appearance');

        return [$exam->fresh(), $teacher];
    }

    private function teacher(Organization $organization, string $email): User
    {
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
            'email' => $email,
        ]);
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $teacher->organizations()->attach($organization->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);

        return $teacher;
    }
}
