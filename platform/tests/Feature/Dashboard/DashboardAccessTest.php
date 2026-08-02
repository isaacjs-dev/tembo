<?php

namespace Tests\Feature\Dashboard;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $plan;

    protected $org;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'global_admin']);
        Role::firstOrCreate(['name' => 'institution_admin']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        $this->plan = Plan::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'features' => []]);
        $this->org = Organization::create(['name' => 'Org Test']);
    }

    public function test_institution_admin_can_load_dashboard_with_kpis()
    {
        $admin = User::factory()->create([
            'type' => 'institution_admin',
            'organization_id' => $this->org->id,
        ]);
        $admin->assignRole('institution_admin');

        $response = $this->actingAs($admin)->get('/institution/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('stats');
    }

    public function test_teacher_can_load_custom_dashboard_with_author_stats()
    {
        $teacher = User::factory()->create([
            'type' => 'teacher',
            'organization_id' => $this->org->id,
        ]);
        $teacher->assignRole('teacher');

        // Teacher's path routes to '/dashboard' handled by DashboardController
        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        $response->assertViewHas('recentExams');
    }

    public function test_student_can_load_their_portal_dashboard()
    {
        $student = User::factory()->create([
            'type' => 'student',
            'organization_id' => $this->org->id,
        ]);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('availableExams');
    }
}
