<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected $plan;

    protected $org;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'global_admin']);
        Role::firstOrCreate(['name' => 'institution_admin']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        $this->plan = Plan::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'features' => []]);
        $this->org = Organization::create(['name' => 'Org Test']);
    }

    public function test_blocks_teachers_from_accessing_global_admin_pages()
    {
        $teacher = User::factory()->create(['type' => 'teacher', 'organization_id' => $this->org->id]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get('/admin/plans');
        $response->assertStatus(403);
    }

    public function test_blocks_students_from_accessing_institution_dashboards()
    {
        $student = User::factory()->create(['type' => 'student', 'organization_id' => $this->org->id]);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/institution/dashboard');
        $response->assertStatus(403);
    }

    public function test_redirects_global_admin_to_correct_initial_dashboard()
    {
        $globalAdmin = User::factory()->create(['type' => 'global_admin']);
        $globalAdmin->assignRole('global_admin');

        $response = $this->actingAs($globalAdmin)->get('/dashboard');
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_allows_teachers_to_access_their_dashboard()
    {
        $teacher = User::factory()->create(['type' => 'teacher', 'organization_id' => $this->org->id]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_teacher_update_bug_debug()
    {
        $admin = User::factory()->create([
            'type' => 'institution_admin',
            'organization_id' => $this->org->id,
        ]);
        $admin->assignRole('institution_admin');

        $teacher = User::factory()->create([
            'type' => 'teacher',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        $this->withoutExceptionHandling();
        $response = $this->actingAs($admin)->put('/institution/teachers/'.$teacher->id, [
            'name' => 'Edited Name',
            'email' => $teacher->email,
            'status' => 'inactive',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $teacher->id,
            'name' => 'Edited Name',
            'status' => 'inactive',
        ]);
    }
}
