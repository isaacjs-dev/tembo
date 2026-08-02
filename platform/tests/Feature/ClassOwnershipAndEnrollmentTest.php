<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClassOwnershipService;
use App\Services\UserLinkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassOwnershipAndEnrollmentTest extends TestCase
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

    private function createClass(Organization $org, string $name = 'Turma A'): SchoolClass
    {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'owner_type' => 'organization',
            'owner_id' => $org->id,
            'name' => $name,
            'year' => '2026',
        ]);
    }

    // ─── Class Creation with Ownership ───

    #[Test]
    public function class_is_created_with_organization_ownership(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->post(route('institution.classes.store'), [
            'name' => 'Nova Turma',
            'year' => '2026',
        ]);

        $response->assertRedirect(route('institution.classes.index'));

        $class = SchoolClass::withoutGlobalScopes()->where('name', 'Nova Turma')->first();
        $this->assertNotNull($class);
        $this->assertEquals('organization', $class->owner_type);
        $this->assertEquals($org->id, $class->owner_id);
    }

    // ─── Teacher Assignment ───

    #[Test]
    public function teachers_can_be_assigned_to_class(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $class = $this->createClass($org);

        $teacher = User::create([
            'name' => 'Prof Test',
            'email' => 'tc-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($admin)->put(route('institution.classes.update', $class->id), [
            'name' => $class->name,
            'year' => $class->year,
            'teacher_ids' => [$teacher->id],
        ]);

        $response->assertRedirect(route('institution.classes.index'));

        $this->assertDatabaseHas('class_teacher', [
            'school_class_id' => $class->id,
            'user_id' => $teacher->id,
        ]);
    }

    // ─── Class Enrollment via Invite ───

    #[Test]
    public function enrollment_creates_class_enrollment_invite(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $class = $this->createClass($org);

        $student = User::create([
            'name' => 'Aluno Test',
            'email' => 'enroll-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'student',
            'status' => 'active',
        ]);
        $student->assignRole('student');

        $response = $this->actingAs($admin)->post(route('institution.classes.enroll', $class->id), [
            'student_user_id' => $student->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('invites', [
            'invite_type' => 'class_enrollment',
            'invitee_user_id' => $student->id,
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $class->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function accepting_enrollment_adds_student_to_class(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $class = $this->createClass($org);

        $student = User::create([
            'name' => 'Aluno Accept',
            'email' => 'accept-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'type' => 'student',
            'status' => 'active',
        ]);
        $student->assignRole('student');

        $invite = Invite::create([
            'inviter_id' => $admin->id,
            'organization_id' => $org->id,
            'invitee_email' => $student->email,
            'invitee_user_id' => $student->id,
            'target_role' => 'student',
            'invite_type' => 'class_enrollment',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $class->id,
        ]);

        $linker = app(UserLinkerService::class);
        $linker->acceptInvite($invite, $student);

        // Aluno está na turma
        $this->assertTrue($class->students()->where('users.id', $student->id)->exists());

        // Aluno vinculado à org
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $student->id,
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        // Convite aceito
        $this->assertEquals('accepted', $invite->fresh()->status);
    }

    // ─── Ownership Transfer ───

    #[Test]
    public function transfer_creates_transfer_invite(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $org->update(['owner_user_id' => $admin->id]);
        $class = $this->createClass($org);

        $org2 = $this->createOrg();
        $admin2 = $this->createAdmin($org2);
        $org2->update(['owner_user_id' => $admin2->id]);

        $service = app(ClassOwnershipService::class);
        $invite = $service->initiateTransfer($class, $admin, 'organization', $org2->id);

        $this->assertEquals('class_ownership_transfer', $invite->invite_type);
        $this->assertEquals(SchoolClass::class, $invite->target_entity_type);
        $this->assertEquals($class->id, $invite->target_entity_id);
        $this->assertEquals('pending', $invite->status);
    }

    #[Test]
    public function accepting_transfer_changes_class_owner(): void
    {
        $org1 = $this->createOrg();
        $admin1 = $this->createAdmin($org1);
        $org1->update(['owner_user_id' => $admin1->id]);
        $class = $this->createClass($org1);

        $org2 = $this->createOrg();
        $admin2 = $this->createAdmin($org2);
        $org2->update(['owner_user_id' => $admin2->id]);

        // Criar convite de transferência
        $invite = Invite::create([
            'inviter_id' => $admin1->id,
            'organization_id' => $org1->id,
            'invitee_email' => $admin2->email,
            'invitee_user_id' => $admin2->id,
            'target_role' => 'owner',
            'invite_type' => 'class_ownership_transfer',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $class->id,
        ]);

        $service = app(ClassOwnershipService::class);
        $service->acceptTransfer($invite, $admin2);

        $class->refresh();
        $this->assertEquals('organization', $class->owner_type);
        $this->assertEquals($org2->id, $class->owner_id);
        $this->assertEquals($org2->id, $class->organization_id);

        // Log existe
        $this->assertDatabaseHas('class_ownership_logs', [
            'school_class_id' => $class->id,
            'previous_owner_type' => 'organization',
            'previous_owner_id' => $org1->id,
            'new_owner_type' => 'organization',
            'new_owner_id' => $org2->id,
        ]);

        // Convite aceito
        $this->assertEquals('accepted', $invite->fresh()->status);
    }

    #[Test]
    public function duplicate_transfer_is_blocked(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $org->update(['owner_user_id' => $admin->id]);
        $class = $this->createClass($org);

        $org2 = $this->createOrg();
        $admin2 = $this->createAdmin($org2);
        $org2->update(['owner_user_id' => $admin2->id]);

        $service = app(ClassOwnershipService::class);
        $service->initiateTransfer($class, $admin, 'organization', $org2->id);

        $this->expectException(ValidationException::class);
        $service->initiateTransfer($class, $admin, 'organization', $org2->id);
    }

    #[Test]
    public function non_owner_cannot_transfer(): void
    {
        $org = $this->createOrg();
        $class = $this->createClass($org);

        $teacher = User::create([
            'name' => 'Prof',
            'email' => 'non-owner-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $org2 = $this->createOrg();

        $this->expectException(ValidationException::class);

        $service = app(ClassOwnershipService::class);
        $service->initiateTransfer($class, $teacher, 'organization', $org2->id);
    }

    // ─── isOwnedBy Helper ───

    #[Test]
    public function is_owned_by_returns_true_for_org_admin(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $class = $this->createClass($org);

        $this->assertTrue($class->isOwnedBy($admin));
    }

    #[Test]
    public function is_owned_by_returns_false_for_non_admin(): void
    {
        $org = $this->createOrg();
        $class = $this->createClass($org);

        $teacher = User::create([
            'name' => 'T',
            'email' => 'x-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $this->assertFalse($class->isOwnedBy($teacher));
    }
}
