<?php

namespace Tests\Feature;

use App\Models\BNCcNode;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\KnowledgeArea;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionShare;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_tenant_scope_returns_no_rows_when_authenticated_user_has_no_context(): void
    {
        $organization = $this->organization('Tenant protegido');
        Discipline::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Disciplina secreta',
        ]);

        $teacher = $this->user(null, 'teacher');

        $this->actingAs($teacher);

        $this->assertSame(0, Discipline::query()->count());
    }

    public function test_inactive_current_membership_blocks_http_and_scoped_queries(): void
    {
        $organization = $this->organization('Tenant bloqueado');
        Discipline::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Disciplina protegida',
        ]);
        $teacher = $this->user($organization, 'teacher');
        $teacher->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role_in_org' => 'teacher',
                'status' => 'inactive',
                'joined_at' => now(),
            ],
        ]);

        $this->actingAs($teacher);

        $this->assertSame(0, Discipline::query()->count());
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_legacy_context_cannot_bypass_an_authoritative_membership_in_another_tenant(): void
    {
        $authorizedOrganization = $this->organization('Tenant autorizado');
        $staleOrganization = $this->organization('Tenant no FK legado');
        Discipline::withoutGlobalScopes()->create([
            'organization_id' => $staleOrganization->id,
            'name' => 'Disciplina que não pode vazar',
        ]);

        $teacher = $this->user($staleOrganization, 'teacher');
        $this->link($teacher, $authorizedOrganization, 'teacher');

        $this->actingAs($teacher);

        $this->assertFalse($teacher->canUseOrganizationContext($staleOrganization->id));
        $this->assertSame(0, Discipline::query()->count());
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Disciplina que não pode vazar');
        $this->get(route('institution.omr.index'))->assertOk();
    }

    public function test_global_admin_without_selected_context_receives_forbidden_instead_of_type_error(): void
    {
        $globalAdmin = $this->user(null, 'global_admin');

        $this->actingAs($globalAdmin)
            ->get(route('institution.students.index'))
            ->assertForbidden();

        $this->actingAs($globalAdmin)
            ->get(route('institution.teachers.index'))
            ->assertForbidden();
    }

    public function test_single_active_membership_is_selected_without_guessing_an_unrelated_tenant(): void
    {
        $organization = $this->organization('Tenant associado');
        $teacher = $this->user(null, 'teacher');
        $this->link($teacher, $organization, 'teacher');

        $this->actingAs($teacher)
            ->post(route('questions.store'), $this->essayPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
        ]);
    }

    public function test_class_rejects_teacher_and_student_from_another_tenant(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $admin = $this->user($organization, 'institution_admin');
        $class = $this->schoolClass($organization, 'Turma A');
        $foreignTeacher = $this->user($otherOrganization, 'teacher');
        $foreignStudent = $this->user($otherOrganization, 'student');

        $this->actingAs($admin)
            ->put(route('institution.classes.update', $class), [
                'name' => $class->name,
                'year' => $class->year,
                'teacher_ids' => [$foreignTeacher->id],
            ])
            ->assertSessionHasErrors('teacher_ids.0');

        $this->actingAs($admin)
            ->post(route('institution.classes.enroll', $class), [
                'student_user_id' => $foreignStudent->id,
            ])
            ->assertSessionHasErrors('student_user_id');

        $this->assertDatabaseMissing('class_teacher', [
            'school_class_id' => $class->id,
            'user_id' => $foreignTeacher->id,
        ]);
        $this->assertDatabaseCount('invites', 0);
    }

    public function test_student_class_sync_rejects_foreign_ids_and_preserves_other_tenant_links(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $admin = $this->user($organization, 'institution_admin');
        $student = $this->user($organization, 'student');
        $this->link($student, $organization, 'student');
        $localClass = $this->schoolClass($organization, 'Turma local');
        $foreignClass = $this->schoolClass($otherOrganization, 'Turma externa');
        $student->schoolClasses()->attach($foreignClass->id);

        $this->actingAs($admin)
            ->put(route('institution.students.update', $student), [
                'status' => 'active',
                'school_classes' => [$localClass->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_student', [
            'school_class_id' => $localClass->id,
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseHas('class_student', [
            'school_class_id' => $foreignClass->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($admin)
            ->put(route('institution.students.update', $student), [
                'status' => 'active',
                'school_classes' => [$foreignClass->id],
            ])
            ->assertSessionHasErrors('school_classes.0');
    }

    public function test_student_pages_do_not_render_profiles_or_classes_from_another_tenant(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $admin = $this->user($organization, 'institution_admin');
        $student = $this->user($organization, 'student');
        $this->link($student, $organization, 'student');
        $this->link($student, $otherOrganization, 'student');
        StudentProfile::create([
            'user_id' => $student->id,
            'organization_id' => $organization->id,
            'registration_number' => 'MATRICULA-A',
        ]);
        StudentProfile::create([
            'user_id' => $student->id,
            'organization_id' => $otherOrganization->id,
            'registration_number' => 'SEGREDO-B',
        ]);
        $localClass = $this->schoolClass($organization, 'Turma visível A');
        $foreignClass = $this->schoolClass($otherOrganization, 'Turma secreta B');
        $student->schoolClasses()->attach([$localClass->id, $foreignClass->id]);

        $this->actingAs($admin)
            ->get(route('institution.students.index'))
            ->assertOk()
            ->assertSee('MATRICULA-A')
            ->assertSee('Turma visível A')
            ->assertDontSee('SEGREDO-B')
            ->assertDontSee('Turma secreta B');

        $this->actingAs($admin)
            ->get(route('institution.students.edit', $student))
            ->assertOk()
            ->assertSee('MATRICULA-A')
            ->assertDontSee('SEGREDO-B')
            ->assertDontSee('Turma secreta B');
    }

    public function test_question_rejects_every_foreign_tenant_taxonomy(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $teacher = $this->user($organization, 'teacher');

        $knowledgeArea = KnowledgeArea::withoutGlobalScopes()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Externa',
        ]);
        $discipline = Discipline::withoutGlobalScopes()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Externa',
        ]);
        $bnccSkill = BNCcNode::create([
            'discipline_id' => $discipline->id,
            'type' => 'skill',
            'title' => 'Habilidade externa',
        ]);
        $customSkill = CustomSkill::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Personalizada externa',
        ]);

        $this->actingAs($teacher)
            ->post(route('questions.store'), $this->essayPayload([
                'knowledge_area_id' => $knowledgeArea->id,
                'discipline_id' => $discipline->id,
                'bncc_skills' => [$bnccSkill->id],
                'custom_skills' => [$customSkill->id],
            ]))
            ->assertSessionHasErrors([
                'knowledge_area_id',
                'discipline_id',
                'bncc_skills.0',
                'custom_skills.0',
            ]);

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_question_share_rejects_teacher_from_another_tenant_without_losing_existing_share(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $owner = $this->user($organization, 'teacher');
        $localTeacher = $this->user($organization, 'teacher');
        $foreignTeacher = $this->user($otherOrganization, 'teacher');
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $owner->id,
            'type' => 'essay',
            'visibility_scope' => 'shared_specific',
            'content' => ['statement' => 'Questão'],
            'stage' => 'em',
            'grade' => '1',
        ]);
        QuestionShare::create([
            'question_id' => $question->id,
            'shared_with_user_id' => $localTeacher->id,
        ]);

        $this->actingAs($owner)
            ->post(route('questions.storeShare', $question), [
                'teacher_ids' => [$foreignTeacher->id],
            ])
            ->assertSessionHasErrors('teacher_ids.0');

        $this->assertDatabaseHas('question_shares', [
            'question_id' => $question->id,
            'shared_with_user_id' => $localTeacher->id,
        ]);
        $this->assertDatabaseMissing('question_shares', [
            'question_id' => $question->id,
            'shared_with_user_id' => $foreignTeacher->id,
        ]);
    }

    public function test_unlinking_teacher_preserves_global_status_password_and_other_memberships(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $admin = $this->user($organization, 'institution_admin');
        $teacher = $this->user($organization, 'teacher');
        $this->link($teacher, $organization, 'teacher');
        $this->link($teacher, $otherOrganization, 'teacher');
        $password = $teacher->password;

        $this->actingAs($admin)
            ->delete(route('institution.teachers.destroy', $teacher))
            ->assertRedirect(route('institution.teachers.index'));

        $teacher->refresh();
        $this->assertSame('active', $teacher->status);
        $this->assertSame($password, $teacher->password);
        $this->assertSame($otherOrganization->id, $teacher->organization_id);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $organization->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $otherOrganization->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('institution.teachers.destroy', $teacher))
            ->assertRedirect(route('institution.teachers.index'));

        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_institution_cannot_reset_an_existing_teacher_global_password(): void
    {
        $organization = $this->organization('Tenant A');
        $admin = $this->user($organization, 'institution_admin');
        $teacher = $this->user($organization, 'teacher');
        $password = $teacher->password;

        $this->actingAs($admin)
            ->put(route('institution.teachers.update', $teacher), [
                'name' => 'Nome sequestrado',
                'email' => 'login-sequestrado@example.test',
                'status' => 'active',
                'password' => 'senha-alterada-sem-autorizacao',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $teacher->refresh();
        $this->assertSame($password, $teacher->password);
        $this->assertNotSame('Nome sequestrado', $teacher->name);
        $this->assertNotSame('login-sequestrado@example.test', $teacher->email);

        $this->actingAs($admin)
            ->get(route('institution.teachers.edit', $teacher))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSee('Status do vínculo institucional');
    }

    public function test_unlinking_student_preserves_the_global_account(): void
    {
        $organization = $this->organization('Tenant A');
        $otherOrganization = $this->organization('Tenant B');
        $admin = $this->user($organization, 'institution_admin');
        $student = $this->user($organization, 'student');
        $this->link($student, $organization, 'student');
        $this->link($student, $otherOrganization, 'student');
        $password = $student->password;

        $this->actingAs($admin)
            ->delete(route('institution.students.destroy', $student))
            ->assertRedirect(route('institution.students.index'));

        $student->refresh();
        $this->assertSame('active', $student->status);
        $this->assertSame($password, $student->password);
        $this->assertSame($otherOrganization->id, $student->organization_id);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $student->id,
            'organization_id' => $organization->id,
            'status' => 'inactive',
        ]);
    }

    private function organization(string $name): Organization
    {
        return Organization::create(['name' => $name, 'active' => true]);
    }

    private function user(?Organization $organization, string $type): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization?->id,
            'type' => $type,
            'status' => 'active',
        ]);
        $user->assignRole($type);

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

    private function schoolClass(Organization $organization, string $name): SchoolClass
    {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
            'name' => $name,
            'year' => '2026',
        ]);
    }

    private function essayPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'essay',
            'visibility_scope' => 'private',
            'statement' => 'Enunciado seguro',
            'stage' => 'em',
            'grade' => '1',
        ], $overrides);
    }
}
