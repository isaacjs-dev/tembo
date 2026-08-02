<?php

namespace Tests\Feature\OMR;

use App\Models\Exam;
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
}
