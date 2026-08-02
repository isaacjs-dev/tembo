<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createGlobalAdmin(): User
    {
        $user = User::factory()->create(['type' => 'global_admin']);
        $user->assignRole('global_admin');

        return $user;
    }

    /* ────────────────────────────────────────────
     * 1. Audit Logs
     * ──────────────────────────────────────────── */

    public function test_auditable_trait_creates_log_on_plan_created(): void
    {
        $admin = $this->createGlobalAdmin();
        $this->actingAs($admin);

        Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 10,
            'original_price' => 10,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'model_type' => Plan::class,
            'user_id' => $admin->id,
        ]);
    }

    public function test_auditable_trait_logs_old_and_new_on_update(): void
    {
        $admin = $this->createGlobalAdmin();
        $this->actingAs($admin);

        $plan = Plan::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'price' => 10,
            'original_price' => 10,
            'status' => 'active',
        ]);

        $plan->update(['name' => 'New Name']);

        $log = AuditLog::where('action', 'updated')
            ->where('model_type', Plan::class)
            ->where('model_id', $plan->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Old Name', $log->payload['old']['name']);
        $this->assertEquals('New Name', $log->payload['new']['name']);
    }

    public function test_audit_log_list_requires_admin_role(): void
    {
        $teacher = User::factory()->create(['type' => 'teacher']);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = $this->createGlobalAdmin();

        AuditLog::log('created', Plan::class, 1, ['new' => ['name' => 'Test']]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Logs de Auditoria');
    }

    /* ────────────────────────────────────────────
     * 2. Dashboard Admin
     * ──────────────────────────────────────────── */

    public function test_admin_dashboard_loads_kpis(): void
    {
        $admin = $this->createGlobalAdmin();
        User::factory()->count(3)->create(['type' => 'teacher', 'status' => 'active']);
        User::factory()->count(2)->create(['type' => 'student', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Painel Administrativo')
            ->assertSee('Professores')
            ->assertSee('Ações Rápidas');
    }

    public function test_dashboard_requires_admin_role(): void
    {
        $teacher = User::factory()->create(['type' => 'teacher']);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_audit_log_helper_creates_entry(): void
    {
        $admin = $this->createGlobalAdmin();
        $this->actingAs($admin);

        AuditLog::log('login', User::class, $admin->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'model_type' => User::class,
            'model_id' => $admin->id,
            'user_id' => $admin->id,
        ]);
    }
}
