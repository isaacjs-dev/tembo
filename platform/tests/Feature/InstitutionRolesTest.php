<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckInstitutionPermission;
use App\Models\InstitutionRole;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InstitutionRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function createOrg(): Organization
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

        $org = Organization::create(['name' => 'Org '.uniqid(), 'active' => true]);
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return $org;
    }

    private function createAdmin(Organization $org): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'a-'.uniqid().'@t.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'type' => 'institution_admin',
            'status' => 'active',
        ]);
        $admin->assignRole('institution_admin');

        return $admin;
    }

    // ─── CRUD Tests ───

    #[Test]
    public function admin_can_create_institution_role(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->post(route('institution.roles.store'), [
            'name' => 'Coordenador',
            'description' => 'Coordenador pedagógico',
            'permissions' => ['view_teachers', 'view_students', 'view_reports'],
        ]);

        $response->assertRedirect(route('institution.roles.index'));

        $role = InstitutionRole::where('name', 'Coordenador')->first();
        $this->assertNotNull($role);
        $this->assertEquals($org->id, $role->organization_id);
        $this->assertEquals('coordenador', $role->slug);

        $perms = $role->getPermissionKeys();
        $this->assertCount(3, $perms);
        $this->assertContains('view_teachers', $perms);
        $this->assertContains('view_students', $perms);
        $this->assertContains('view_reports', $perms);
    }

    #[Test]
    public function admin_can_update_role_permissions(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'Pedagogo',
        ]);
        $role->syncPermissions(['view_students']);

        $response = $this->actingAs($admin)->put(route('institution.roles.update', $role), [
            'name' => 'Pedagogo',
            'permissions' => ['view_students', 'manage_students', 'view_reports'],
        ]);

        $response->assertRedirect(route('institution.roles.index'));

        $perms = $role->fresh()->getPermissionKeys();
        $this->assertCount(3, $perms);
        $this->assertContains('manage_students', $perms);
    }

    #[Test]
    public function admin_can_delete_role(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'Secretário',
        ]);

        $response = $this->actingAs($admin)->delete(route('institution.roles.destroy', $role));

        $response->assertRedirect(route('institution.roles.index'));
        $this->assertDatabaseMissing('institution_roles', ['id' => $role->id]);
    }

    // ─── Permission Checking ───

    #[Test]
    public function role_has_permission_returns_correctly(): void
    {
        $org = $this->createOrg();
        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'Test Role',
        ]);
        $role->syncPermissions(['view_teachers', 'view_students']);

        $this->assertTrue($role->hasPermission('view_teachers'));
        $this->assertTrue($role->hasPermission('view_students'));
        $this->assertFalse($role->hasPermission('manage_teachers'));
    }

    #[Test]
    public function sync_permissions_replaces_all(): void
    {
        $org = $this->createOrg();
        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'Sync Test',
        ]);
        $role->syncPermissions(['view_teachers', 'view_students']);
        $this->assertCount(2, $role->getPermissionKeys());

        $role->syncPermissions(['manage_exams']);
        $perms = $role->getPermissionKeys();
        $this->assertCount(1, $perms);
        $this->assertContains('manage_exams', $perms);
    }

    // ─── Assign/Remove ───

    #[Test]
    public function assign_role_to_user_pivot(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'Coord',
        ]);

        $teacher = User::create([
            'name' => 'T',
            'email' => 'assign-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $teacher->organizations()->attach($org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $service = app(InstitutionRoleService::class);
        $service->assignToUser($admin, $teacher->id, $org->id, $role->id);

        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $org->id,
            'institution_role_id' => $role->id,
        ]);
    }

    #[Test]
    public function delete_role_clears_member_assignments(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => 'ToDelete',
        ]);

        $teacher = User::create([
            'name' => 'T',
            'email' => 'del-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $teacher->organizations()->attach($org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
            'institution_role_id' => $role->id,
        ]);

        $service = app(InstitutionRoleService::class);
        $service->delete($role);

        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $org->id,
            'institution_role_id' => null,
        ]);
    }

    // ─── Middleware ───

    #[Test]
    public function institution_admin_bypasses_permission_check(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        // Admin should always pass inst_perm middleware
        $middleware = new CheckInstitutionPermission;
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, fn () => response('ok'), 'manage_teachers');
        $this->assertEquals(200, $response->getStatusCode());
    }
}
