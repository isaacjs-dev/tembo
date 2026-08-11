<?php

namespace Tests\Feature;

use App\Models\AppearanceTemplate;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\AppearanceDefinitionSchema;
use App\Services\AppearanceTemplateService;
use App\Services\CanonicalPrintDocumentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AppearanceTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_duplicates_system_template_without_mutating_original(): void
    {
        [$organization, $teacher] = $this->workspace('Galeria', 'teacher');
        $system = AppearanceTemplate::query()->where('slug', 'system-assessment_header-essential')->sole();

        $this->actingAs($teacher)->get(route('appearance-templates.index'))
            ->assertOk()->assertSee('Layouts e cabeçalhos')->assertSee('Personalizar cópia');
        $this->actingAs($teacher)->post(route('appearance-templates.duplicate', $system))
            ->assertRedirect();

        $copy = AppearanceTemplate::query()->where('is_system', false)->sole();
        $this->assertSame($organization->id, $copy->organization_id);
        $this->assertSame($teacher->id, $copy->owner_id);
        $this->assertSame(1, $copy->current_version);
        $this->assertSame(1, $system->fresh()->current_version);
    }

    public function test_editor_creates_immutable_canvas_version_and_rejects_stale_save(): void
    {
        [, $teacher] = $this->workspace('Editor', 'teacher');
        $this->actingAs($teacher);
        $template = app(AppearanceTemplateService::class)->create(
            $teacher, 'assessment_header', 'Meu cabeçalho', $this->canvasDefinition(), 'personal',
        );
        $changed = $this->canvasDefinition();
        $changed['elements'][0]['font_size'] = 20;

        $this->put(route('appearance-templates.update', $template), [
            'name' => 'Meu cabeçalho v2',
            'base_version' => 1,
            'definition' => json_encode($changed, JSON_THROW_ON_ERROR),
            'summary' => 'Ajuste de título',
        ])->assertRedirect(route('appearance-templates.edit', $template));

        $template->refresh();
        $this->assertSame(2, $template->current_version);
        $this->assertSame('Meu cabeçalho v2', $template->name);
        $this->assertSame(18.0, (float) $template->versions()->where('version', 1)->firstOrFail()->definition['elements'][0]['font_size']);
        $this->assertSame(20.0, (float) $template->currentVersion->definition['elements'][0]['font_size']);

        $this->put(route('appearance-templates.update', $template), [
            'name' => 'Sobrescrita antiga',
            'base_version' => 1,
            'definition' => json_encode($changed, JSON_THROW_ON_ERROR),
        ])->assertStatus(409);
        $this->assertSame(2, $template->fresh()->current_version);
    }

    public function test_logo_upload_is_private_hash_verified_and_renderable(): void
    {
        Storage::fake('local');
        [$organization, $teacher] = $this->workspace('Logo', 'teacher');
        $this->actingAs($teacher);
        $template = app(AppearanceTemplateService::class)->create(
            $teacher, 'assessment_header', 'Com logo', $this->canvasDefinition(), 'personal',
        );
        $definition = $this->canvasDefinition();
        $definition['elements'][] = [
            'id' => 'logo', 'type' => 'image', 'asset_key' => 'logo',
            'x' => 20, 'y' => 20, 'width' => 150, 'height' => 100, 'alt_text' => 'Logo institucional',
        ];

        $this->put(route('appearance-templates.update', $template), [
            'name' => 'Com logo', 'base_version' => 1,
            'definition' => json_encode($definition, JSON_THROW_ON_ERROR),
            'asset_key' => 'logo', 'asset' => UploadedFile::fake()->image('logo.png', 120, 80),
        ])->assertRedirect();

        $version = $template->fresh()->currentVersion;
        $asset = $version->assets['logo'];
        Storage::disk('local')->assertExists($asset['storage_path']);
        $this->assertStringStartsWith('appearance-assets/', $asset['storage_path']);
        $this->get(route('appearance-templates.asset', [$template, $version, 'logo']))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
        $exam = Exam::query()->create([
            'organization_id' => $organization->id, 'author_id' => $teacher->id,
            'title' => 'Avaliação com logo', 'status' => 'draft',
            'assessment_header_version_id' => $version->id,
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id, 'owner_id' => $teacher->id,
            'type' => 'essay', 'content' => ['statement' => 'Explique o conceito.'],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 1, 'order' => 1]);
        $document = app(CanonicalPrintDocumentService::class)->preview($exam);
        $this->assertStringStartsWith('data:image/png;base64,', collect($document['header']['elements'])->firstWhere('type', 'image')['src']);
        $this->assertStringContainsString('data:image/png;base64,', view('exams.print.canonical-document', compact('document'))->render());

        Storage::disk('local')->put($asset['storage_path'], 'tampered');
        $this->get(route('appearance-templates.asset', [$template, $version, 'logo']))->assertServerError();
    }

    public function test_canvas_schema_fails_closed_for_unknown_types_bounds_and_missing_assets(): void
    {
        $schema = app(AppearanceDefinitionSchema::class);
        foreach ([
            fn () => tap($this->canvasDefinition(), fn (&$value) => $value['elements'][0]['type'] = 'html'),
            fn () => tap($this->canvasDefinition(), fn (&$value) => $value['elements'][0]['x'] = 999),
        ] as $invalid) {
            try {
                $schema->normalize('assessment_header', $invalid());
                $this->fail('Schema aceitou elemento inseguro.');
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }

        [, $teacher] = $this->workspace('Asset ausente', 'teacher');
        $definition = $this->canvasDefinition();
        $definition['elements'][] = ['id' => 'logo', 'type' => 'image', 'asset_key' => 'logo', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'alt_text' => 'Logo'];
        try {
            app(AppearanceTemplateService::class)->create($teacher, 'assessment_header', 'Inválido', $definition);
            $this->fail('Imagem sem asset foi aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('asset', $exception->errors());
        }
    }

    public function test_cross_tenant_and_system_mutations_are_not_exposed(): void
    {
        [, $teacherA] = $this->workspace('Tenant A', 'teacher');
        [, $teacherB] = $this->workspace('Tenant B', 'teacher');
        $this->actingAs($teacherA);
        $template = app(AppearanceTemplateService::class)->create(
            $teacherA, 'assessment_header', 'Privado A', $this->canvasDefinition(),
        );

        $this->actingAs($teacherB)->get(route('appearance-templates.edit', $template))->assertNotFound();
        $this->actingAs($teacherA);
        app(AppearanceTemplateService::class)->archive($template, $teacherA);
        $this->actingAs($teacherB)->get(route('appearance-templates.edit', $template))->assertNotFound();
        $system = AppearanceTemplate::query()->where('slug', 'system-assessment_header-essential')->sole();
        $this->actingAs($teacherB)->get(route('appearance-templates.edit', $system))->assertForbidden();
    }

    public function test_institutional_template_offers_copy_instead_of_edit_to_teacher(): void
    {
        [$organization, $admin] = $this->workspace('Instituição visual', 'admin');
        $teacher = User::factory()->create(['organization_id' => $organization->id, 'type' => 'teacher', 'status' => 'active']);
        $teacher->organizations()->attach($organization, ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($admin);
        $template = app(AppearanceTemplateService::class)->create(
            $admin, 'assessment_header', 'Cabeçalho da instituição', $this->canvasDefinition(), 'organization',
        );
        $this->assertFalse(app(AppearanceTemplateService::class)->canMutate($template, $teacher));

        $response = $this->actingAs($teacher)->get(route('appearance-templates.index'))->assertOk();
        $response->assertSee('Cabeçalho da instituição')->assertSee('Personalizar cópia');
        $response->assertDontSee(route('appearance-templates.edit', $template));
        $this->post(route('appearance-templates.duplicate', $template))->assertRedirect();
        $this->assertDatabaseHas('appearance_templates', [
            'organization_id' => $organization->id, 'owner_type' => User::class,
            'owner_id' => $teacher->id, 'visibility_scope' => 'private',
        ]);
    }

    public function test_archived_selection_remains_visible_only_to_its_author_until_replaced(): void
    {
        [$organization, $teacher] = $this->workspace('Histórico visual', 'teacher');
        $this->actingAs($teacher);
        $service = app(AppearanceTemplateService::class);
        $template = $service->create($teacher, 'assessment_header', 'Cabeçalho antigo', $this->canvasDefinition());
        $versionId = $template->currentVersion->id;
        $service->archive($template, $teacher);

        $plainCatalog = $service->catalogFor($teacher, $organization);
        $selectedCatalog = $service->catalogFor($teacher, $organization, [$versionId]);
        $this->assertFalse($plainCatalog['assessment_header']->contains('id', $template->id));
        $this->assertTrue($selectedCatalog['assessment_header']->contains('id', $template->id));

        $exam = Exam::query()->create([
            'organization_id' => $organization->id, 'author_id' => $teacher->id,
            'title' => 'Avaliação histórica', 'status' => 'draft',
            'assessment_header_version_id' => $versionId,
        ]);
        $service->applySelection($exam, $teacher, null, $versionId);
        $this->assertSame($versionId, $exam->fresh()->assessment_header_version_id);
        $this->put(route('appearance-templates.update', $template), [
            'name' => 'Tentativa após arquivo', 'base_version' => 1,
            'definition' => json_encode($this->canvasDefinition(), JSON_THROW_ON_ERROR),
        ])->assertStatus(409);

        $otherExam = Exam::query()->create([
            'organization_id' => $organization->id, 'author_id' => $teacher->id,
            'title' => 'Avaliação nova', 'status' => 'draft',
        ]);
        try {
            $service->applySelection($otherExam, $teacher, null, $versionId);
            $this->fail('Template arquivado foi aplicado a uma nova Avaliação.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        [, $foreign] = $this->workspace('Outro contexto', 'teacher');
        $this->assertFalse($service->catalogFor($foreign, $foreign->organization, [$versionId])['assessment_header']->contains('id', $template->id));
    }

    public function test_active_template_keeps_an_exact_older_selected_version_in_exam_catalog(): void
    {
        [$organization, $teacher] = $this->workspace('Versão fixada', 'teacher');
        $this->actingAs($teacher);
        $service = app(AppearanceTemplateService::class);
        $template = $service->create($teacher, 'assessment_header', 'Cabeçalho evolutivo', $this->canvasDefinition());
        $versionOne = $template->currentVersion;
        $changed = $this->canvasDefinition();
        $changed['elements'][0]['font_size'] = 22;
        $service->createVersionFromEditor($template, $teacher, 1, $changed, []);

        $catalog = $service->catalogFor($teacher, $organization, [$versionOne->id]);
        $entries = $catalog['assessment_header']->where('id', $template->id);
        $this->assertCount(2, $entries);
        $this->assertSame(
            [$versionOne->id, $template->fresh()->currentVersion->id],
            $entries->pluck('currentVersion.id')->sort()->values()->all(),
        );
        $this->assertTrue((bool) $entries->firstWhere('currentVersion.id', $versionOne->id)->selected_historical);
    }

    /** @return array{Organization, User} */
    private function workspace(string $name, string $role): array
    {
        $organization = Organization::query()->create(['name' => $name, 'active' => true]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => $role === 'admin' ? 'institution_admin' : $role,
            'status' => 'active',
        ]);
        $user->organizations()->attach($organization, ['role_in_org' => $role, 'status' => 'active', 'joined_at' => now()]);

        return [$organization, $user];
    }

    /** @return array<string, mixed> */
    private function canvasDefinition(): array
    {
        return [
            'mode' => 'canvas', 'height_mm' => 36,
            'canvas' => ['width_units' => 1000, 'height_units' => 360],
            'elements' => [[
                'id' => 'title', 'type' => 'text', 'token' => 'assessment.title',
                'x' => 180, 'y' => 30, 'width' => 640, 'height' => 70,
                'align' => 'center', 'font_size' => 18, 'font_weight' => 700, 'color' => '#111827',
            ]],
        ];
    }
}
