<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InstitutionRole;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function createOrg(array $extra = []): array
    {
        $plan = Plan::create([
            'name' => 'Plan '.uniqid(),
            'slug' => 'p-'.uniqid(),
            'price' => 99,
            'tier_level' => 1,
            'status' => 'active',
        ]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_teachers', 'limit_value' => 50]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_students', 'limit_value' => 200]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_classes', 'limit_value' => 20]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_extra_profiles', 'limit_value' => 5]);

        $org = Organization::create(array_merge(['name' => 'Org '.uniqid(), 'active' => true], $extra));
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'a-'.uniqid().'@t.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'type' => 'institution_admin',
            'status' => 'active',
        ]);
        $admin->assignRole('institution_admin');

        return [$org, $admin];
    }

    // ─── IDOR: Acessar recurso de outra organização ───

    #[Test]
    public function admin_cannot_access_class_from_other_org(): void
    {
        [$org1, $admin1] = $this->createOrg();
        [$org2, $admin2] = $this->createOrg();

        $class = SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $org2->id,
            'owner_type' => 'organization',
            'owner_id' => $org2->id,
            'name' => 'Turma Alheia',
            'year' => '2026',
        ]);

        // Admin1 tenta editar turma da org2
        $response = $this->actingAs($admin1)->get(route('institution.classes.edit', $class->id));
        $response->assertStatus(404);
    }

    #[Test]
    public function admin_cannot_delete_class_from_other_org(): void
    {
        [$org1, $admin1] = $this->createOrg();
        [$org2, $admin2] = $this->createOrg();

        $class = SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $org2->id,
            'owner_type' => 'organization',
            'owner_id' => $org2->id,
            'name' => 'Turma Alheia',
            'year' => '2026',
        ]);

        $response = $this->actingAs($admin1)->delete(route('institution.classes.destroy', $class->id));
        $response->assertStatus(404);

        $this->assertDatabaseHas('school_classes', ['id' => $class->id]);
    }

    #[Test]
    public function admin_cannot_edit_role_from_other_org(): void
    {
        [$org1, $admin1] = $this->createOrg();
        [$org2, $admin2] = $this->createOrg();

        $role = InstitutionRole::create([
            'organization_id' => $org2->id,
            'name' => 'Cargo Alheio',
        ]);

        $response = $this->actingAs($admin1)->get(route('institution.roles.edit', $role->id));
        $response->assertStatus(404);
    }

    #[Test]
    public function admin_cannot_delete_role_from_other_org(): void
    {
        [$org1, $admin1] = $this->createOrg();
        [$org2, $admin2] = $this->createOrg();

        $role = InstitutionRole::create([
            'organization_id' => $org2->id,
            'name' => 'Cargo Alheio',
        ]);

        $response = $this->actingAs($admin1)->delete(route('institution.roles.destroy', $role->id));
        $response->assertStatus(404);

        $this->assertDatabaseHas('institution_roles', ['id' => $role->id]);
    }

    // ─── Escalação de Privilégios: Teacher não pode fazer ações de admin ───

    #[Test]
    public function teacher_cannot_create_class(): void
    {
        [$org, $admin] = $this->createOrg();

        $teacher = User::create([
            'name' => 'Prof',
            'email' => 'esc-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->post(route('institution.classes.store'), [
            'name' => 'Turma Hacker',
            'year' => '2026',
        ]);

        // Pode falhar com 403 ou com o check canAddClass (depende de middleware)
        // Pelo menos não pode ter criado sem permissão
        $response->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_manage_billing(): void
    {
        [$org, $admin] = $this->createOrg();

        $teacher = User::create([
            'name' => 'Prof',
            'email' => 'bill-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $newPlan = Plan::create([
            'name' => 'Premium',
            'slug' => 'prem-'.uniqid(),
            'price' => 299,
            'tier_level' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs($teacher)->post(route('institution.billing.changePlan'), [
            'plan_id' => $newPlan->id,
        ]);

        // Teacher deve receber 403
        $response->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_create_institution_roles(): void
    {
        [$org, $admin] = $this->createOrg();

        $teacher = User::create([
            'name' => 'Prof',
            'email' => 'role-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->post(route('institution.roles.store'), [
            'name' => 'Hacker Role',
            'permissions' => ['manage_teachers', 'manage_students'],
        ]);

        $this->assertDatabaseMissing('institution_roles', ['name' => 'Hacker Role']);
    }

    // ─── Audit Log Coverage ───

    #[Test]
    public function class_creation_generates_audit_log(): void
    {
        [$org, $admin] = $this->createOrg();

        $this->actingAs($admin)->post(route('institution.classes.store'), [
            'name' => 'Turma Auditada',
            'year' => '2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'model_type' => SchoolClass::class,
            'user_id' => $admin->id,
        ]);
    }

    #[Test]
    public function class_deletion_generates_audit_log(): void
    {
        [$org, $admin] = $this->createOrg();

        $class = SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'owner_type' => 'organization',
            'owner_id' => $org->id,
            'name' => 'Turma Del',
            'year' => '2026',
        ]);

        $this->actingAs($admin)->delete(route('institution.classes.destroy', $class->id));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'model_type' => SchoolClass::class,
            'model_id' => $class->id,
        ]);
    }

    #[Test]
    public function plan_change_generates_audit_log(): void
    {
        [$org, $admin] = $this->createOrg();
        $previousPlanId = $org->subscriptions()->where('status', 'active')->value('plan_id');

        $newPlan = Plan::create([
            'name' => 'Premium',
            'slug' => 'audit-'.uniqid(),
            'price' => 199,
            'tier_level' => 2,
            'status' => 'active',
        ]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_teachers', 'limit_value' => 100]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_students', 'limit_value' => 500]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_classes', 'limit_value' => 50]);

        $this->actingAs($admin)->post(route('institution.billing.changePlan'), [
            'plan_id' => $newPlan->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'plan_changed',
            'user_id' => $admin->id,
            'organization_id' => $org->id,
        ]);
        $log = AuditLog::where('action', 'plan_changed')->latest('id')->firstOrFail();
        $this->assertSame($previousPlanId, $log->before_json['plan_id']);
        $this->assertSame('active', $log->before_json['status']);
        $this->assertSame($newPlan->id, $log->after_json['plan_id']);
    }

    #[Test]
    public function role_creation_generates_audit_log(): void
    {
        [$org, $admin] = $this->createOrg();

        $this->actingAs($admin)->post(route('institution.roles.store'), [
            'name' => 'Coord Auditado',
            'permissions' => ['view_teachers'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'model_type' => InstitutionRole::class,
            'user_id' => $admin->id,
        ]);
    }

    // ─── AuditLog Model Helpers ───

    #[Test]
    public function action_label_returns_friendly_name(): void
    {
        $log = new AuditLog(['action' => 'transfer_initiated']);
        $this->assertEquals('Transferência Iniciada', $log->action_label);

        $log2 = new AuditLog(['action' => 'plan_changed']);
        $this->assertEquals('Plano Alterado', $log2->action_label);

        $log3 = new AuditLog(['action' => 'force_deleted']);
        $this->assertEquals('Exclusão Permanente', $log3->action_label);

        $log4 = new AuditLog(['action' => 'unknown_action']);
        $this->assertEquals('Unknown action', $log4->action_label);
    }

    #[Test]
    public function model_label_returns_basename(): void
    {
        $log = new AuditLog(['model_type' => SchoolClass::class]);
        $this->assertEquals('SchoolClass', $log->model_label);

        $log2 = new AuditLog(['model_type' => null]);
        $this->assertEquals('—', $log2->model_label);
    }
}
