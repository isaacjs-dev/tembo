<?php

namespace Tests\Feature\OMR;

use App\Models\Exam;
use App\Models\InstitutionRole;
use App\Models\OmrScan;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebScanTest extends TestCase
{
    use RefreshDatabase;

    protected $org;

    protected $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'global_admin']);
        Role::firstOrCreate(['name' => 'institution_admin']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 10, 'features' => []]);
        $this->org = Organization::create(['name' => 'Escola Teste']);

        $this->teacher = User::factory()->create([
            'type' => 'teacher',
            'organization_id' => $this->org->id,
        ]);
        $this->teacher->assignRole('teacher');
    }

    public function test_teacher_can_access_webscan_page()
    {
        $response = $this->actingAs($this->teacher)->get(route('institution.omr.webscan'));
        $response->assertStatus(200);
        $response->assertViewIs('omr.webscan');
    }

    public function test_custom_institution_role_requires_manage_omr_permission(): void
    {
        $role = InstitutionRole::create([
            'organization_id' => $this->org->id,
            'name' => 'Apoio sem OMR',
            'is_active' => true,
        ]);
        $this->teacher->organizations()->attach($this->org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
            'institution_role_id' => $role->id,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('institution.omr.webscan'))
            ->assertForbidden();

        $role->permissions()->create(['permission' => 'manage_omr']);
        $this->get(route('institution.omr.webscan'))->assertOk();
    }

    public function test_omr_upload_rejects_invalid_file_extensions()
    {
        // Simulando disco de upload local provisorio
        Storage::fake('local');

        $exam = Exam::create([
            'title' => 'Biology Test',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);

        // Tentativa de enviar um arquivo php se passando por txt/jpg (Risco de Segurança)
        $file = UploadedFile::fake()->create('hacker_script.php', 100);

        $response = $this->actingAs($this->teacher)->post(route('institution.omr.store'), [
            'exam_id' => $exam->id,
            'image' => $file,
        ]);

        // A checagem de regras de negocio padrao do laravel (Form request ou validate)
        // deve prever image|mimes:jpeg,png,jpg,webp impedindo o POST.
        // O status deve ser 302 (redirect back com errors) originado pelo ValidationException.
        $response->assertStatus(302);
        $response->assertSessionHasErrors('image');
    }

    public function test_untrusted_local_storage_path_endpoint_is_not_exposed(): void
    {
        $this->actingAs($this->teacher)
            ->post('/institution/omr/store-local', [
                'image_path' => 'omr-scans/another-tenant/private.jpg',
                'answers' => [],
                'quality' => ['needs_review' => false],
                'confidence_score' => 1,
                'omr_template_id' => 1,
            ])
            ->assertNotFound();
    }

    public function test_local_reprocessing_requires_human_confirmation_and_is_audited(): void
    {
        $exam = Exam::create([
            'title' => 'Review before grading',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);
        $scan = OmrScan::create([
            'exam_id' => $exam->id,
            'organization_id' => $this->org->id,
            'uploaded_by' => $this->teacher->id,
            'image_path' => 'omr-scans/'.$this->org->id.'/review.jpg',
            'idempotency_key' => 'review-before-grading',
            'status' => 'reviewing',
        ]);

        $this->actingAs($this->teacher)
            ->post(route('institution.omr.updateLocal', $scan), [
                'answers_json' => json_encode([['q' => 1, 'selected' => 0]]),
                'quality_json' => json_encode(['needs_review' => false, 'overall_confidence' => 0.98]),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'reviewing');

        $scan->refresh();
        $this->assertSame('reviewing', $scan->status);
        $this->assertNull($scan->score);
        $this->assertDatabaseHas('omr_audit_logs', [
            'omr_scan_id' => $scan->id,
            'user_id' => $this->teacher->id,
            'action' => 'REPROCESSED_FOR_REVIEW',
        ]);
    }

    public function test_new_scan_image_is_private_and_only_served_by_authenticated_omr_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $exam = Exam::create([
            'title' => 'Private OMR image',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);
        $image = UploadedFile::fake()->image('scan.jpg', 20, 20);
        $bytes = file_get_contents($image->getRealPath());

        $this->actingAs($this->teacher)->post(route('institution.omr.store'), [
            'exam_id' => $exam->id,
            'image' => UploadedFile::fake()->createWithContent('scan.jpg', $bytes),
        ])->assertRedirect();

        $scan = OmrScan::firstOrFail();
        Storage::disk('local')->assertExists($scan->image_path);
        Storage::disk('public')->assertMissing($scan->image_path);
        $this->get(route('institution.omr.image', $scan))->assertOk();

        // Even a historical file on the public disk is no longer exposed by
        // the unauthenticated catch-all storage route.
        Storage::disk('public')->put($scan->image_path, 'legacy');
        $this->get('/storage/'.$scan->image_path)->assertNotFound();
        $this->get('/storage/'.strtoupper($scan->image_path))->assertNotFound();

        $secondExam = Exam::create([
            'title' => 'Same image, different assessment',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);
        $this->post(route('institution.omr.store'), [
            'exam_id' => $secondExam->id,
            'image' => UploadedFile::fake()->createWithContent('scan.jpg', $bytes),
        ])->assertRedirect();
        $this->assertDatabaseCount('omr_scans', 2);
    }

    public function test_legacy_public_omr_images_are_moved_without_changing_database_paths(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $exam = Exam::create([
            'title' => 'Legacy image migration',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);
        $path = 'omr-scans/'.$this->org->id.'/legacy.jpg';
        Storage::disk('public')->put($path, 'legacy-image');
        $scan = OmrScan::create([
            'exam_id' => $exam->id,
            'organization_id' => $this->org->id,
            'uploaded_by' => $this->teacher->id,
            'image_path' => $path,
            'idempotency_key' => 'legacy-image-test',
            'status' => 'reviewing',
        ]);

        $migration = require database_path('migrations/2026_08_08_000110_move_legacy_omr_images_to_private_storage.php');
        $migration->up();

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame($path, $scan->fresh()->image_path);
    }

    public function test_legacy_image_migration_preserves_public_source_on_private_path_collision(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $exam = Exam::create([
            'title' => 'Legacy collision',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);
        $path = 'omr-scans/'.$this->org->id.'/collision.jpg';
        Storage::disk('public')->put($path, 'authoritative-public-image');
        Storage::disk('local')->put($path, 'different-private-image');
        OmrScan::create([
            'exam_id' => $exam->id,
            'organization_id' => $this->org->id,
            'uploaded_by' => $this->teacher->id,
            'image_path' => $path,
            'idempotency_key' => 'legacy-collision-test',
            'status' => 'reviewing',
        ]);

        $migration = require database_path('migrations/2026_08_08_000110_move_legacy_omr_images_to_private_storage.php');
        $migration->up();

        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('authoritative-public-image', Storage::disk('public')->get($path));
        $this->assertSame('different-private-image', Storage::disk('local')->get($path));
    }
}
