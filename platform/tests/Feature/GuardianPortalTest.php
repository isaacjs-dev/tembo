<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\GuardianStudentLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuardianPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student', 'guardian'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_guardian_only_sees_students_with_an_active_link(): void
    {
        $organization = $this->organization();
        $guardian = $this->user($organization, 'guardian');
        $linkedStudent = $this->user($organization, 'student', ['name' => 'Estudante Vinculado']);
        $otherStudent = $this->user($organization, 'student', ['name' => 'Outro Estudante']);

        GuardianStudentLink::create([
            'organization_id' => $organization->id,
            'guardian_id' => $guardian->id,
            'student_id' => $linkedStudent->id,
            'relationship' => 'Mãe',
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Estudante Vinculado')
            ->assertDontSee('Outro Estudante');

        $this->actingAs($guardian)
            ->get(route('guardian.students.show', $linkedStudent))
            ->assertOk();

        $this->actingAs($guardian)
            ->get(route('guardian.students.show', $otherStudent))
            ->assertForbidden();
    }

    public function test_guardian_portal_obeys_score_feedback_and_scheduled_release(): void
    {
        $organization = $this->organization();
        $guardian = $this->user($organization, 'guardian');
        $student = $this->user($organization, 'student');
        $teacher = $this->user($organization, 'teacher');

        GuardianStudentLink::create([
            'organization_id' => $organization->id,
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Responsável',
        ]);

        $releasedExam = $this->exam($organization, $teacher, 'Resultado liberado', [
            'show_score' => true,
            'show_feedback' => true,
            'show_answers' => false,
        ]);
        $scheduledExam = $this->exam($organization, $teacher, 'Resultado programado', [
            'show_score' => true,
            'show_feedback' => true,
            'show_answers' => true,
            'results_available_from' => now()->addDay()->toIso8601String(),
        ]);

        ExamSubmission::create([
            'exam_id' => $releasedExam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'finished_at' => now()->subMinute(),
            'score' => 8,
            'feedback' => 'Bom progresso.',
        ]);
        ExamSubmission::create([
            'exam_id' => $scheduledExam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'finished_at' => now(),
            'score' => 6,
            'feedback' => 'Comentário ainda privado.',
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian.students.show', $student))
            ->assertOk()
            ->assertSee('Resultado liberado')
            ->assertSee('8,0')
            ->assertSee('Bom progresso.')
            ->assertSee('Resultado programado')
            ->assertDontSee('6,0')
            ->assertDontSee('Comentário ainda privado.')
            ->assertSee('Liberação programada');
    }

    public function test_institution_admin_can_create_and_link_a_guardian_account(): void
    {
        $organization = $this->organization();
        $admin = $this->user($organization, 'institution_admin');
        $student = $this->user($organization, 'student');

        $this->actingAs($admin)
            ->post(route('institution.guardians.store'), [
                'student_id' => $student->id,
                'guardian_name' => 'Responsável Teste',
                'guardian_email' => 'responsavel@example.test',
                'guardian_password' => 'senha-provisoria-segura',
                'guardian_password_confirmation' => 'senha-provisoria-segura',
                'relationship' => 'Pai',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $guardian = User::where('email', 'responsavel@example.test')->firstOrFail();
        $this->assertSame('guardian', $guardian->type);
        $this->assertTrue($guardian->hasRole('guardian'));
        $this->assertDatabaseHas('guardian_student_links', [
            'organization_id' => $organization->id,
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Pai',
            'deleted_at' => null,
        ]);
    }

    public function test_institution_cannot_link_a_student_from_another_tenant(): void
    {
        $organization = $this->organization();
        $otherOrganization = $this->organization();
        $admin = $this->user($organization, 'institution_admin');
        $foreignStudent = $this->user($otherOrganization, 'student');

        $this->actingAs($admin)
            ->post(route('institution.guardians.store'), [
                'student_id' => $foreignStudent->id,
                'guardian_name' => 'Responsável Teste',
                'guardian_email' => 'isolado@example.test',
                'guardian_password' => 'senha-provisoria-segura',
                'guardian_password_confirmation' => 'senha-provisoria-segura',
                'relationship' => 'Responsável',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('guardian_student_links', [
            'student_id' => $foreignStudent->id,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'isolado@example.test']);
    }

    public function test_global_admin_user_update_keeps_type_and_rbac_role_in_sync(): void
    {
        $organization = $this->organization();
        $admin = $this->user($organization, 'global_admin');
        $target = $this->user($organization, 'student');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'type' => 'guardian',
                'organization_id' => $organization->id,
                'plan_id' => null,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('guardian', $target->type);
        $this->assertTrue($target->hasRole('guardian'));
        $this->assertFalse($target->hasRole('student'));
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Instituição '.uniqid(),
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(Organization $organization, string $type, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'organization_id' => $organization->id,
            'name' => ucfirst($type),
            'email' => $type.'-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ], $attributes));
        $user->assignRole($type);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function exam(
        Organization $organization,
        User $teacher,
        string $title,
        array $settings
    ): Exam {
        return Exam::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => $title,
            'status' => 'closed',
            'settings' => $settings,
        ]);
    }
}
