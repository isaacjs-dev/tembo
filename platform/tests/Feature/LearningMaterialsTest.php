<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\Organization;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LearningMaterialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_student_cannot_access_material_management(): void
    {
        $student = $this->user($this->organization(), 'student');

        $this->actingAs($student)
            ->get(route('learning-materials.index'))
            ->assertForbidden();
    }

    public function test_cross_tenant_and_other_author_updates_are_forbidden(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $otherTeacher = $this->user($organization, 'teacher');
        $otherOrganization = $this->organization();
        $foreignTeacher = $this->user($otherOrganization, 'teacher');

        $otherMaterial = $this->material($organization, $otherTeacher);
        $foreignMaterial = $this->material($otherOrganization, $foreignTeacher);

        $payload = [
            'title' => 'Tentativa de alteração',
            'body' => 'Conteúdo',
            'status' => 'draft',
        ];

        $this->actingAs($teacher)
            ->put(route('learning-materials.update', $otherMaterial), $payload)
            ->assertForbidden();
        $this->actingAs($teacher)
            ->put(route('learning-materials.update', $foreignMaterial), $payload)
            ->assertForbidden();

        $this->assertSame('Material', $otherMaterial->fresh()->title);
        $this->assertSame('Material', $foreignMaterial->fresh()->title);
    }

    public function test_teacher_can_publish_only_to_an_assigned_class(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $assignedClass = $this->schoolClass($organization);
        $unassignedClass = $this->schoolClass($organization);
        $assignedClass->teachers()->attach($teacher->id);

        $this->actingAs($teacher)
            ->post(route('learning-materials.store'), [
                'title' => 'Revisão de leitura',
                'body' => 'Leia o texto e registre as ideias principais.',
                'status' => 'published',
                'class_ids' => [$unassignedClass->id],
            ])
            ->assertSessionHasErrors('class_ids.0');

        $this->actingAs($teacher)
            ->post(route('learning-materials.store'), [
                'title' => 'Revisão de leitura',
                'body' => 'Leia o texto e registre as ideias principais.',
                'status' => 'published',
                'class_ids' => [$assignedClass->id],
            ])
            ->assertRedirect();

        $material = LearningMaterial::where('title', 'Revisão de leitura')->firstOrFail();
        $this->assertDatabaseHas('learning_material_school_class', [
            'learning_material_id' => $material->id,
            'school_class_id' => $assignedClass->id,
        ]);
    }

    public function test_student_sees_only_published_materials_from_own_classes(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $otherStudent = $this->user($organization, 'student');
        $class = $this->schoolClass($organization);
        $otherClass = $this->schoolClass($organization);
        $class->students()->attach($student->id);
        $otherClass->students()->attach($otherStudent->id);

        $visible = $this->material($organization, $teacher, [
            'title' => 'Material visível',
            'status' => 'published',
        ]);
        $visible->schoolClasses()->attach($class->id);
        $draft = $this->material($organization, $teacher, ['title' => 'Rascunho secreto']);
        $draft->schoolClasses()->attach($class->id);
        $otherClassMaterial = $this->material($organization, $teacher, [
            'title' => 'Material de outra turma',
            'status' => 'published',
        ]);
        $otherClassMaterial->schoolClasses()->attach($otherClass->id);

        $this->actingAs($student)
            ->get(route('student.learning.index'))
            ->assertOk()
            ->assertSee('Material visível')
            ->assertDontSee('Rascunho secreto')
            ->assertDontSee('Material de outra turma');

        $this->actingAs($student)
            ->get(route('student.learning.show', $otherClassMaterial))
            ->assertNotFound();
    }

    public function test_material_matching_incorrect_discipline_ranks_first_with_explanation(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $class = $this->schoolClass($organization);
        $class->students()->attach($student->id);
        $discipline = Discipline::create([
            'organization_id' => $organization->id,
            'name' => 'Matemática',
        ]);

        $generic = $this->material($organization, $teacher, [
            'title' => 'Leitura complementar',
            'status' => 'published',
        ]);
        $generic->schoolClasses()->attach($class->id);
        $matching = $this->material($organization, $teacher, [
            'title' => 'Revisão de frações',
            'status' => 'published',
            'discipline_id' => $discipline->id,
        ]);
        $matching->schoolClasses()->attach($class->id);

        $exam = Exam::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação',
            'status' => 'published',
            'settings' => [],
        ]);
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => ['statement' => 'Questão', 'options' => ['A', 'B'], 'correct_option' => 0],
            'visibility_scope' => 'private',
            'discipline_id' => $discipline->id,
        ]);
        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'score' => 0,
        ]);
        ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => ['raw' => 1],
            'is_correct' => false,
            'points_awarded' => 0,
        ]);

        $response = $this->actingAs($student)
            ->get(route('student.learning.index'))
            ->assertOk()
            ->assertSee('Recomendado porque identificamos 1 resposta(s) incorreta(s) em Matemática.');

        $this->assertLessThan(
            strpos($response->getContent(), 'Leitura complementar'),
            strpos($response->getContent(), 'Revisão de frações'),
        );
    }

    public function test_opening_and_completing_material_is_tracked_and_reported(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $class = $this->schoolClass($organization);
        $class->students()->attach($student->id);
        $material = $this->material($organization, $teacher, [
            'title' => 'Revisão acompanhada',
            'status' => 'published',
        ]);
        $material->schoolClasses()->attach($class->id);

        $this->actingAs($student)
            ->get(route('student.learning.show', $material))
            ->assertOk()
            ->assertSee('Marcar revisão como concluída');
        $this->actingAs($student)
            ->get(route('student.learning.show', $material))
            ->assertOk();

        $progress = LearningMaterialProgress::firstOrFail();
        $this->assertSame('opened', $progress->status);
        $this->assertSame(2, $progress->view_count);

        $this->actingAs($student)
            ->post(route('student.learning.complete', $material))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->actingAs($student)
            ->post(route('student.learning.complete', $material))
            ->assertRedirect();

        $progress->refresh();
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertSame(
            1,
            AuditLog::where('action', 'learning_material_completed')
                ->where('model_id', $material->id)
                ->count()
        );

        $this->actingAs($student)
            ->get(route('student.learning.index'))
            ->assertOk()
            ->assertSee('Revisão concluída');

        $this->actingAs($teacher)
            ->get(route('learning-materials.index'))
            ->assertOk()
            ->assertSee('1 estudante(s) abriram')
            ->assertSee('1 concluíram');
    }

    public function test_recommendations_ignore_submission_history_from_a_previous_tenant(): void
    {
        $currentOrganization = $this->organization();
        $currentTeacher = $this->user($currentOrganization, 'teacher');
        $student = $this->user($currentOrganization, 'student');
        $class = $this->schoolClass($currentOrganization);
        $class->students()->attach($student->id);
        $discipline = Discipline::create([
            'organization_id' => $currentOrganization->id,
            'name' => 'Matemática atual',
        ]);
        $material = $this->material($currentOrganization, $currentTeacher, [
            'title' => 'Conteúdo da instituição atual',
            'status' => 'published',
            'discipline_id' => $discipline->id,
        ]);
        $material->schoolClasses()->attach($class->id);

        $oldOrganization = $this->organization();
        $oldTeacher = $this->user($oldOrganization, 'teacher');
        $oldExam = Exam::withoutGlobalScopes()->create([
            'organization_id' => $oldOrganization->id,
            'author_id' => $oldTeacher->id,
            'title' => 'Avaliação da instituição anterior',
            'status' => 'closed',
            'settings' => [],
        ]);
        $oldQuestion = Question::create([
            'organization_id' => $oldOrganization->id,
            'owner_id' => $oldTeacher->id,
            'type' => 'multiple_choice',
            'content' => ['statement' => 'Questão antiga', 'options' => ['A', 'B'], 'correct_option' => 0],
            'visibility_scope' => 'private',
            'discipline_id' => $discipline->id,
        ]);
        $oldSubmission = ExamSubmission::create([
            'exam_id' => $oldExam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'score' => 0,
        ]);
        ExamAnswer::create([
            'exam_submission_id' => $oldSubmission->id,
            'question_id' => $oldQuestion->id,
            'answer_data' => ['raw' => 1],
            'is_correct' => false,
            'points_awarded' => 0,
        ]);

        $this->actingAs($student)
            ->get(route('student.learning.index'))
            ->assertOk()
            ->assertSee('Material publicado para uma de suas turmas.')
            ->assertDontSee('resposta(s) incorreta(s) em Matemática atual');
    }

    public function test_institution_admin_can_restore_only_material_from_own_tenant(): void
    {
        $organization = $this->organization();
        $organization->update(['can_access_trash' => true]);
        $admin = $this->user($organization, 'institution_admin');
        $teacher = $this->user($organization, 'teacher');
        $material = $this->material($organization, $teacher);
        $material->delete();

        $foreignOrganization = $this->organization();
        $foreignTeacher = $this->user($foreignOrganization, 'teacher');
        $foreignMaterial = $this->material($foreignOrganization, $foreignTeacher);
        $foreignMaterial->delete();

        $this->actingAs($admin)
            ->post(route('institution.trash.restore'), [
                'model_type' => 'learning_material',
                'model_id' => $foreignMaterial->id,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('institution.trash.restore'), [
                'model_type' => 'learning_material',
                'model_id' => $material->id,
            ])
            ->assertRedirect();

        $this->assertFalse($material->fresh()->trashed());
        $this->assertTrue($foreignMaterial->fresh()->trashed());
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Organization '.uniqid(),
            'active' => true,
        ]);
    }

    private function user(Organization $organization, string $type): User
    {
        $user = User::create([
            'organization_id' => $organization->id,
            'name' => ucfirst($type),
            'email' => $type.'-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ]);
        $user->assignRole($type);

        return $user;
    }

    private function schoolClass(Organization $organization): SchoolClass
    {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
            'name' => 'Turma '.uniqid(),
            'year' => '2026',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function material(
        Organization $organization,
        User $author,
        array $attributes = [],
    ): LearningMaterial {
        return LearningMaterial::create(array_merge([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => 'Material',
            'body' => 'Conteúdo do material.',
            'status' => 'draft',
        ], $attributes));
    }
}
