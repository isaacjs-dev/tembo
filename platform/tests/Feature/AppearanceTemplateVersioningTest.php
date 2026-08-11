<?php

namespace Tests\Feature;

use App\Models\AppearanceTemplate;
use App\Models\AppearanceTemplateVersion;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateQuestion;
use App\Models\Organization;
use App\Models\TemplateDefault;
use App\Models\User;
use App\Services\AppearanceTemplateService;
use App\Services\OmrTemplateVersionService;
use Database\Seeders\OmrTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use OutOfBoundsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AppearanceTemplateVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_contexts_are_distinct_and_versions_are_immutable(): void
    {
        $templates = AppearanceTemplate::query()->where('is_system', true)->get()->keyBy('kind');

        $this->assertSame(
            collect(AppearanceTemplate::KINDS)->sort()->values()->all(),
            $templates->keys()->sort()->values()->all(),
        );
        $this->assertSame(3, TemplateDefault::query()->where('scope_key', 'system')->count());

        $system = $templates['assessment_layout'];
        $version = $system->currentVersion;
        $original = $version->definition;

        try {
            app(AppearanceTemplateService::class)->createVersion(
                $system,
                User::factory()->create(['type' => 'global_admin']),
                ['page' => ['size' => 'Letter']],
            );
            $this->fail('Template do sistema aceitou mutação.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(LogicException::class);
        $version->update(['definition' => ['tampered' => true]]);
        $this->assertSame($original, $version->fresh()->definition);
    }

    public function test_defaults_follow_user_then_organization_then_system_and_snapshots_pin_versions(): void
    {
        [$organization, $admin] = $this->workspaceUser('Escola Aparência', 'admin');
        $service = app(AppearanceTemplateService::class);
        $organizationTemplate = $this->appearanceTemplate(
            $organization,
            $admin,
            Organization::class,
            $organization->id,
            'assessment_layout',
            'Layout da escola',
            'org_public',
        );
        $organizationVersionOne = $this->appearanceVersion(
            $organizationTemplate,
            $admin,
            1,
            $this->layoutDefinition('portrait'),
        );
        $service->setDefault($organizationTemplate, $admin, 'organization', $organization);

        $system = AppearanceTemplate::query()->where('kind', 'assessment_layout')->where('is_system', true)->sole();
        $personal = $service->duplicate($system, $admin, $organization);
        $service->setDefault($personal, $admin, 'user', $organization);

        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $admin->id,
            'title' => 'Avaliação com aparência versionada',
            'status' => 'draft',
        ]);

        $userSnapshot = $service->snapshotForExam($exam);
        $this->assertSame($personal->id, data_get($userSnapshot, 'assessment_layout.template_id'));

        $service->archive($personal, $admin);
        $organizationSnapshot = $service->snapshotForExam($exam);
        $this->assertSame($organizationTemplate->id, data_get($organizationSnapshot, 'assessment_layout.template_id'));
        $this->assertSame('portrait', data_get($organizationSnapshot, 'assessment_layout.definition.page.orientation'));
        $historicalCopy = ExamCopy::query()->create([
            'exam_id' => $exam->id,
            'copy_number' => 1,
            'template_snapshot' => $organizationSnapshot,
            'questions_map' => [],
            'options_map' => [],
            'validation_hash' => str()->random(40),
        ]);

        $organizationVersionTwo = $service->createVersion(
            $organizationTemplate,
            $admin,
            $this->layoutDefinition('landscape'),
        );
        $this->assertSame(2, $organizationVersionTwo->version);
        $this->assertSame(2, TemplateDefault::query()
            ->where('scope_key', 'organization:'.$organization->id)
            ->where('kind', 'assessment_layout')->value('template_version'));

        $exam->update(['assessment_layout_version_id' => $organizationVersionOne->id]);
        $pinned = $service->snapshotForExam($exam->fresh());
        $this->assertSame('portrait', data_get($pinned, 'assessment_layout.definition.page.orientation'));
        $this->assertSame('portrait', data_get($organizationSnapshot, 'assessment_layout.definition.page.orientation'));
        $this->assertSame('portrait', data_get($historicalCopy->fresh()->template_snapshot, 'assessment_layout.definition.page.orientation'));
        $this->assertNotSame($organizationVersionOne->content_hash, $organizationVersionTwo->content_hash);
    }

    public function test_cross_tenant_templates_cannot_be_read_or_mutated(): void
    {
        [$organizationA, $teacherA] = $this->workspaceUser('Escola A', 'teacher');
        [$organizationB, $teacherB] = $this->workspaceUser('Escola B', 'teacher');
        $template = $this->appearanceTemplate(
            $organizationA,
            $teacherA,
            User::class,
            $teacherA->id,
            'assessment_header',
            'Cabeçalho A',
            'org_public',
        );
        $foreignVersion = $this->appearanceVersion($template, $teacherA, 1, ['marker' => 'tenant-a']);

        $this->assertFalse(AppearanceTemplate::query()
            ->visibleTo($teacherB, $organizationB)
            ->whereKey($template->id)->exists());

        try {
            app(AppearanceTemplateService::class)->duplicate($template, $teacherB, $organizationB);
            $this->fail('Template de outro tenant foi duplicado.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $exam = Exam::query()->create([
            'organization_id' => $organizationB->id,
            'author_id' => $teacherB->id,
            'title' => 'Tentativa de referência cruzada',
            'status' => 'draft',
            'assessment_header_version_id' => $foreignVersion->id,
        ]);
        try {
            app(AppearanceTemplateService::class)->snapshotForExam($exam);
            $this->fail('Versão de outro tenant foi aplicada à avaliação.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $sameTenantTeacher = User::factory()->create([
            'organization_id' => $organizationA->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $sameTenantTeacher->organizations()->attach($organizationA->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $sameTenantExam = Exam::query()->create([
            'organization_id' => $organizationA->id,
            'author_id' => $sameTenantTeacher->id,
            'title' => 'Tentativa de template privado alheio',
            'status' => 'draft',
            'assessment_header_version_id' => $foreignVersion->id,
        ]);
        $template->update(['visibility_scope' => 'private']);
        try {
            app(AppearanceTemplateService::class)->snapshotForExam($sameTenantExam);
            $this->fail('Template privado de outro professor foi aplicado à avaliação.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        try {
            app(AppearanceTemplateService::class)->createVersion($template, $teacherB, ['marker' => 'attack']);
            $this->fail('Template de outro tenant foi alterado.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_omr_versions_freeze_geometry_header_logo_and_rois(): void
    {
        [$organization, $teacher] = $this->workspaceUser('Escola OMR', 'teacher');
        $template = OmrTemplate::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $teacher->id,
            'owner_type' => User::class,
            'owner_id' => $teacher->id,
            'name' => 'Cartão versionado',
            'slug' => 'cartao-versionado',
            'visibility_scope' => 'private',
            'is_system' => false,
            'is_default' => false,
            'is_active' => true,
            'current_version' => 1,
            'width' => 1000,
            'height' => 1414,
            'corner_points_json' => [],
            'layout_config' => ['columns' => 1],
            'header_config' => ['title' => 'Cabeçalho v1'],
            'logo_path' => 'logos/v1.png',
            'thresholds_json' => ['filled' => 0.55],
            'calibration_json' => ['dpi' => 300],
            'qr_region_json' => ['x' => 10, 'y' => 20],
        ]);
        OmrTemplateQuestion::query()->create([
            'omr_template_id' => $template->id,
            'question_number' => 1,
            'option_labels_json' => ['A', 'B'],
            'rois_json' => ['A' => ['x' => 1], 'B' => ['x' => 2]],
            'weight' => 1,
        ]);
        $legacyCurrent = $template->snapshotForVersion(1);
        $this->assertTrue($legacyCurrent['legacy_fallback']);
        $this->assertSame('live_legacy_current', $legacyCurrent['version_source']);
        $versions = app(OmrTemplateVersionService::class);
        $versionOne = $versions->append($template, $teacher);

        $template->update([
            'header_config' => ['title' => 'Cabeçalho v2'],
            'logo_path' => 'logos/v2.png',
            'thresholds_json' => ['filled' => 0.7],
        ]);
        $template->questions()->delete();
        OmrTemplateQuestion::query()->create([
            'omr_template_id' => $template->id,
            'question_number' => 1,
            'option_labels_json' => ['A', 'B'],
            'rois_json' => ['A' => ['x' => 100], 'B' => ['x' => 200]],
            'weight' => 1,
        ]);
        $versionTwo = $versions->append($template->fresh(), $teacher);

        $historical = $template->fresh()->snapshotForVersion($versionOne->version);
        $current = $template->fresh()->snapshotForVersion($versionTwo->version);
        $this->assertSame('Cabeçalho v1', data_get($historical, 'header_config.title'));
        $this->assertSame('logos/v1.png', $historical['logo_path']);
        $this->assertSame(1, data_get($historical, 'definition.questions.0.rois.A.x'));
        $this->assertSame(0.55, data_get($historical, 'definition.thresholds.filled'));
        $this->assertSame('Cabeçalho v2', data_get($current, 'header_config.title'));
        $this->assertSame(100, data_get($current, 'definition.questions.0.rois.A.x'));

        try {
            $template->fresh()->snapshotForVersion(999);
            $this->fail('Uma versão OMR inexistente usou a geometria live como fallback.');
        } catch (OutOfBoundsException) {
            $this->assertTrue(true);
        }

        [, $foreignTeacher] = $this->workspaceUser('Escola OMR externa', 'teacher');
        try {
            $versions->append($template->fresh(), $foreignTeacher);
            $this->fail('Ator de outro tenant versionou o cartão OMR.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->expectException(LogicException::class);
        $versionOne->update(['content_hash' => str_repeat('0', 64)]);
    }

    public function test_omr_system_seeder_is_idempotent_and_creates_complete_versions(): void
    {
        $this->seed(OmrTemplateSeeder::class);
        $versionsAfterFirstRun = OmrTemplate::query()->where('is_system', true)
            ->withCount('versions')->get()->sum('versions_count');

        $this->seed(OmrTemplateSeeder::class);

        $this->assertSame(
            $versionsAfterFirstRun,
            OmrTemplate::query()->where('is_system', true)->withCount('versions')->get()->sum('versions_count'),
        );
        $this->assertGreaterThan(0, $versionsAfterFirstRun);
        $this->assertSame(0, OmrTemplate::query()->where('is_system', true)
            ->whereHas('versions', fn ($query) => $query
                ->whereNull('definition')
                ->orWhereNull('content_hash'))
            ->count());

        $system = OmrTemplate::query()->where('is_system', true)->firstOrFail();
        try {
            $system->update(['name' => 'Mutação indevida']);
            $this->fail('Template OMR do sistema aceitou mutação direta.');
        } catch (LogicException) {
            $this->assertNotSame('Mutação indevida', $system->fresh()->name);
        }
    }

    /** @return array{Organization, User} */
    private function workspaceUser(string $name, string $role): array
    {
        $organization = Organization::query()->create(['name' => $name, 'active' => true]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => $role === 'admin' ? 'institution_admin' : $role,
            'status' => 'active',
        ]);
        $user->organizations()->attach($organization->id, [
            'role_in_org' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$organization, $user];
    }

    private function appearanceTemplate(
        Organization $organization,
        User $creator,
        string $ownerType,
        int $ownerId,
        string $kind,
        string $name,
        string $visibility,
    ): AppearanceTemplate {
        return AppearanceTemplate::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'kind' => $kind,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'visibility_scope' => $visibility,
            'is_system' => false,
            'current_version' => 1,
        ]);
    }

    private function appearanceVersion(
        AppearanceTemplate $template,
        User $creator,
        int $version,
        array $definition,
    ): AppearanceTemplateVersion {
        return AppearanceTemplateVersion::query()->create([
            'appearance_template_id' => $template->id,
            'version' => $version,
            'schema_version' => 1,
            'definition' => $definition,
            'assets' => [],
            'content_hash' => hash('sha256', json_encode(['definition' => $definition, 'assets' => []], JSON_THROW_ON_ERROR)),
            'created_by' => $creator->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function layoutDefinition(string $orientation): array
    {
        return [
            'page' => [
                'size' => 'A4',
                'orientation' => $orientation,
                'margins_mm' => [15, 15, 15, 15],
            ],
            'questions' => [
                'columns' => 1,
                'separator' => 'line',
                'avoid_break_inside' => true,
            ],
        ];
    }
}
