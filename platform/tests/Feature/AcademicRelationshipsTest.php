<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AcademicRelationshipService;
use App\Services\UserLinkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AcademicRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_teacher_relationships_are_tenant_aware_persistent_and_idempotent(): void
    {
        $organization = $this->organization('Instituição A');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $discipline = $this->discipline($organization, 'Matemática');
        $student->schoolClasses()->attach($schoolClass->id);

        $this->actingAs($admin);
        $service = app(AcademicRelationshipService::class);
        $service->syncTeacher(
            $teacher,
            $organization,
            [$schoolClass->id],
            [$student->id],
            [$discipline->id],
            $admin,
        );
        $service->syncTeacher(
            $teacher,
            $organization,
            [$schoolClass->id],
            [$student->id],
            [$discipline->id],
            $admin,
        );

        $this->assertDatabaseHas('class_teacher', [
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('discipline_teacher', [
            'organization_id' => $organization->id,
            'discipline_id' => $discipline->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertSame(1, DB::table('class_teacher')->count());
        $this->assertSame(1, DB::table('teacher_student')->count());
        $this->assertSame(1, DB::table('discipline_teacher')->count());
    }

    public function test_foreign_relationship_is_rejected_without_losing_existing_assignments(): void
    {
        $organization = $this->organization('Instituição A');
        $otherOrganization = $this->organization('Instituição B');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $discipline = $this->discipline($organization, 'História');
        $foreignDiscipline = $this->discipline($otherOrganization, 'Conteúdo externo');

        $this->actingAs($admin);
        $service = app(AcademicRelationshipService::class);
        $service->syncTeacher(
            $teacher,
            $organization,
            [$schoolClass->id],
            [$student->id],
            [$discipline->id],
            $admin,
        );

        try {
            $service->syncTeacher(
                $teacher,
                $organization,
                [],
                [],
                [$foreignDiscipline->id],
                $admin,
            );
            $this->fail('A disciplina de outro workspace deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discipline_ids', $exception->errors());
        }

        $this->assertDatabaseHas('class_teacher', [
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('discipline_teacher', [
            'organization_id' => $organization->id,
            'discipline_id' => $discipline->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('discipline_teacher', [
            'organization_id' => $organization->id,
            'discipline_id' => $foreignDiscipline->id,
            'user_id' => $teacher->id,
        ]);
    }

    public function test_class_creation_rolls_back_when_a_discipline_belongs_to_another_workspace(): void
    {
        $organization = $this->organization('Instituição A');
        $otherOrganization = $this->organization('Instituição B');
        $this->enableClassCreation($organization);
        $admin = $this->member($organization, 'institution_admin');
        $foreignDiscipline = $this->discipline($otherOrganization, 'Disciplina externa');

        $this->actingAs($admin)
            ->post(route('institution.classes.store'), [
                'name' => 'Turma que não deve persistir',
                'year' => '2026',
                'discipline_ids' => [$foreignDiscipline->id],
            ])
            ->assertSessionHasErrors('discipline_ids');

        $this->assertDatabaseMissing('school_classes', [
            'organization_id' => $organization->id,
            'name' => 'Turma que não deve persistir',
        ]);
    }

    public function test_class_update_persists_teacher_and_discipline_and_derives_students(): void
    {
        $organization = $this->organization('Instituição A');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $discipline = $this->discipline($organization, 'Ciências');
        $student->schoolClasses()->attach($schoolClass->id);

        $this->actingAs($admin)
            ->put(route('institution.classes.update', $schoolClass), [
                'name' => 'Turma atualizada',
                'year' => '2027',
                'teacher_ids' => [$teacher->id],
                'discipline_ids' => [$discipline->id],
            ])
            ->assertRedirect(route('institution.classes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_teacher', [
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('class_discipline', [
            'organization_id' => $organization->id,
            'school_class_id' => $schoolClass->id,
            'discipline_id' => $discipline->id,
        ]);
        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_teacher_edit_syncs_only_contextual_relations_and_preserves_account(): void
    {
        $organization = $this->organization('Instituição A');
        $otherOrganization = $this->organization('Instituição B');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $this->link($teacher, $otherOrganization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $discipline = $this->discipline($organization, 'Língua Portuguesa');
        $originalPassword = $teacher->password;

        $this->actingAs($admin)
            ->put(route('institution.teachers.update', $teacher), [
                'status' => 'active',
                '_sync_academic_relations' => '1',
                'class_ids' => [$schoolClass->id],
                'student_ids' => [$student->id],
                'discipline_ids' => [$discipline->id],
            ])
            ->assertRedirect(route('institution.teachers.index'))
            ->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertSame($originalPassword, $teacher->password);
        $this->assertDatabaseHas('user_organization', [
            'organization_id' => $otherOrganization->id,
            'user_id' => $teacher->id,
            'role_in_org' => 'teacher',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_accepting_enrollment_links_assigned_teacher_and_rejects_mismatched_workspace(): void
    {
        $organization = $this->organization('Instituição A');
        $otherOrganization = $this->organization('Instituição B');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $foreignClass = $this->schoolClass($otherOrganization, 'Turma externa');
        DB::table('class_teacher')->insert([
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
            'assigned_at' => now(),
        ]);

        $invite = $this->enrollmentInvite($admin, $student, $organization, $schoolClass);
        $this->actingAs($student);
        app(UserLinkerService::class)->acceptInvite($invite, $student);

        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);

        $mismatchedInvite = $this->enrollmentInvite($admin, $student, $organization, $foreignClass);
        try {
            app(UserLinkerService::class)->acceptInvite($mismatchedInvite, $student);
            $this->fail('O convite com workspace divergente deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invite', $exception->errors());
        }

        $this->assertSame('pending', $mismatchedInvite->fresh()->status);
        $this->assertDatabaseMissing('class_student', [
            'school_class_id' => $foreignClass->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_personal_class_owner_is_linked_to_enrolled_student_and_enrollment_is_unique(): void
    {
        $organization = $this->organization('Workspace pessoal', 'personal');
        $teacher = $this->member($organization, 'teacher');
        $organization->update(['owner_user_id' => $teacher->id]);
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization, 'Turma pessoal', 'user', $teacher->id);
        $student->schoolClasses()->attach($schoolClass->id);

        $this->actingAs($teacher);
        app(AcademicRelationshipService::class)->linkClassStudentTeachers($schoolClass, $student, $teacher);

        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);

        $inserted = DB::table('class_student')->insertOrIgnore([
            'school_class_id' => $schoolClass->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $inserted);
        $this->assertSame(1, DB::table('class_student')
            ->where('school_class_id', $schoolClass->id)
            ->where('user_id', $student->id)
            ->count());
    }

    public function test_derivation_ignores_cross_tenant_or_inactive_pivot_members(): void
    {
        $organization = $this->organization('Instituição A');
        $otherOrganization = $this->organization('Instituição B');
        $admin = $this->member($organization, 'institution_admin');
        $teacher = $this->member($organization, 'teacher');
        $inactiveTeacher = $this->member($organization, 'teacher');
        $foreignTeacher = $this->member($otherOrganization, 'teacher');
        $student = $this->member($organization, 'student');
        $foreignStudent = $this->member($otherOrganization, 'student');
        $schoolClass = $this->schoolClass($organization);

        $inactiveTeacher->organizations()->updateExistingPivot($organization->id, ['status' => 'inactive']);
        DB::table('class_teacher')->insert([
            [
                'school_class_id' => $schoolClass->id,
                'user_id' => $teacher->id,
                'assigned_at' => now(),
            ],
            [
                'school_class_id' => $schoolClass->id,
                'user_id' => $inactiveTeacher->id,
                'assigned_at' => now(),
            ],
            [
                'school_class_id' => $schoolClass->id,
                'user_id' => $foreignTeacher->id,
                'assigned_at' => now(),
            ],
        ]);
        $student->schoolClasses()->attach($schoolClass->id);
        $foreignStudent->schoolClasses()->attach($schoolClass->id);

        $this->actingAs($admin);
        app(AcademicRelationshipService::class)->linkClassStudentTeachers($schoolClass, $student, $admin);

        $this->assertDatabaseHas('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $inactiveTeacher->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $foreignTeacher->id,
        ]);
        $this->assertDatabaseMissing('teacher_student', [
            'organization_id' => $organization->id,
            'student_id' => $foreignStudent->id,
        ]);
    }

    public function test_ordinary_member_cannot_materialize_academic_relationships(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->member($organization, 'teacher');
        $student = $this->member($organization, 'student');
        $schoolClass = $this->schoolClass($organization);
        $student->schoolClasses()->attach($schoolClass->id);
        DB::table('class_teacher')->insert([
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($student);
        try {
            app(AcademicRelationshipService::class)
                ->linkClassStudentTeachers($schoolClass, $student, $student);
            $this->fail('Um membro comum não deveria materializar relações acadêmicas.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('teacher_student', [
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
    }

    private function organization(string $name, string $workspaceType = 'institution'): Organization
    {
        return Organization::create([
            'name' => $name,
            'workspace_type' => $workspaceType,
            'active' => true,
        ]);
    }

    private function enableClassCreation(Organization $organization): void
    {
        $plan = Plan::create([
            'name' => 'Plano acadêmico '.uniqid(),
            'slug' => 'academic-'.uniqid(),
            'price' => 0,
            'tier_level' => 1,
            'status' => 'active',
        ]);
        PlanLimit::create([
            'plan_id' => $plan->id,
            'resource_key' => 'max_classes',
            'limit_value' => 20,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }

    private function member(Organization $organization, string $role): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => $role,
            'status' => 'active',
        ]);
        $user->assignRole($role);
        $this->link($user, $organization, $role);

        return $user;
    }

    private function link(User $user, Organization $organization, string $role): void
    {
        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role_in_org' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);
    }

    private function schoolClass(
        Organization $organization,
        string $name = 'Turma A',
        string $ownerType = 'organization',
        ?int $ownerId = null,
    ): SchoolClass {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId ?? $organization->id,
            'name' => $name,
            'year' => '2026',
        ]);
    }

    private function discipline(Organization $organization, string $name): Discipline
    {
        return Discipline::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }

    private function enrollmentInvite(
        User $inviter,
        User $student,
        Organization $organization,
        SchoolClass $schoolClass,
    ): Invite {
        return Invite::create([
            'inviter_id' => $inviter->id,
            'organization_id' => $organization->id,
            'invitee_email' => $student->email,
            'invitee_user_id' => $student->id,
            'target_role' => 'student',
            'invite_type' => 'class_enrollment',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $schoolClass->id,
        ]);
    }
}
