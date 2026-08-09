<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckOmrApiAccess;
use App\Models\Discipline;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_teacher_assigns_optional_discipline_classes_and_direct_students_in_scope(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $schoolClass = $this->schoolClass($organization, 'Turma 8A');
        $discipline = $this->discipline($organization, 'Matemática');
        $exam = $this->exam($organization, $teacher);
        $this->assignTeacherScope($organization, $teacher, $student, $schoolClass, $discipline);

        $this->actingAs($teacher)
            ->post(route('exams.syncAudience', $exam), [
                'discipline_id' => $discipline->id,
                'class_ids' => [$schoolClass->id],
                'student_ids' => [$student->id],
            ])
            ->assertRedirect(route('exams.edit', $exam))
            ->assertSessionHasNoErrors();

        $this->assertSame($discipline->id, $exam->fresh()->discipline_id);
        $this->assertDatabaseHas('exam_school_class', [
            'exam_id' => $exam->id,
            'school_class_id' => $schoolClass->id,
        ]);
        $this->assertDatabaseHas('exam_student', [
            'organization_id' => $organization->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'assigned_by' => $teacher->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'exam_audience_updated',
            'model_id' => $exam->id,
        ]);
    }

    public function test_out_of_scope_audience_is_rejected_atomically(): void
    {
        $organization = $this->organization('Instituição A');
        $other = $this->organization('Instituição B');
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $unassignedStudent = $this->user($organization, 'student');
        $schoolClass = $this->schoolClass($organization, 'Turma autorizada');
        $discipline = $this->discipline($organization, 'Ciências');
        $foreignDiscipline = $this->discipline($other, 'Disciplina externa');
        $exam = $this->exam($organization, $teacher);
        $this->assignTeacherScope($organization, $teacher, $student, $schoolClass, $discipline);
        $exam->update(['discipline_id' => $discipline->id]);
        $exam->schoolClasses()->attach($schoolClass->id);
        $exam->students()->attach($student->id, [
            'organization_id' => $organization->id,
            'assigned_by' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->from(route('exams.edit', $exam))
            ->post(route('exams.syncAudience', $exam), [
                'discipline_id' => $foreignDiscipline->id,
                'student_ids' => [$unassignedStudent->id],
            ])
            ->assertRedirect(route('exams.edit', $exam))
            ->assertSessionHasErrors('student_ids');

        $this->assertSame($discipline->id, $exam->fresh()->discipline_id);
        $this->assertDatabaseHas('exam_school_class', [
            'exam_id' => $exam->id,
            'school_class_id' => $schoolClass->id,
        ]);
        $this->assertDatabaseHas('exam_student', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('exam_student', [
            'exam_id' => $exam->id,
            'student_id' => $unassignedStudent->id,
        ]);

        $this->actingAs($teacher)
            ->from(route('exams.edit', $exam))
            ->post(route('exams.syncAudience', $exam), [
                'discipline_id' => $foreignDiscipline->id,
                'student_ids' => [$student->id],
            ])
            ->assertRedirect(route('exams.edit', $exam))
            ->assertSessionHasErrors('discipline_id');

        $this->assertSame($discipline->id, $exam->fresh()->discipline_id);
    }

    public function test_draft_can_intentionally_have_no_discipline_or_audience(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $teacher);

        $this->actingAs($teacher)
            ->post(route('exams.syncAudience', $exam), [])
            ->assertRedirect(route('exams.edit', $exam))
            ->assertSessionHasNoErrors();

        $this->assertNull($exam->fresh()->discipline_id);
        $this->assertDatabaseCount('exam_school_class', 0);
        $this->assertDatabaseCount('exam_student', 0);
    }

    public function test_direct_student_audience_is_visible_without_class_or_access_code(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $exam = $this->exam($organization, $teacher, 'published');
        $exam->students()->attach($student->id, [
            'organization_id' => $organization->id,
            'assigned_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($exam->title);

        $this->actingAs($student)
            ->get(route('student.exam.show', $exam))
            ->assertOk();
    }

    public function test_historical_class_and_access_code_paths_remain_available(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $schoolClass = $this->schoolClass($organization, 'Turma histórica');
        $student->schoolClasses()->attach($schoolClass->id);
        DB::table('class_teacher')->insert([
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
            'assigned_at' => now(),
        ]);
        $exam = $this->exam($organization, $teacher, 'published');
        $exam->update(['access_code' => 'ABC123']);

        $this->actingAs($teacher)
            ->post(route('exams.syncClasses', $exam), ['class_ids' => [$schoolClass->id]])
            ->assertRedirect(route('exams.edit', $exam));

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($exam->title);

        $otherStudent = $this->user($organization, 'student');
        $this->actingAs($otherStudent)
            ->post(route('student.joinByCode'), ['access_code' => 'ABC123'])
            ->assertRedirect(route('student.exam.show', $exam));
    }

    public function test_mobile_download_unifies_class_and_direct_students_without_duplicates(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $classStudent = $this->user($organization, 'student');
        $directStudent = $this->user($organization, 'student');
        $schoolClass = $this->schoolClass($organization, 'Turma Mobile');
        $discipline = $this->discipline($organization, 'História');
        $classStudent->schoolClasses()->attach($schoolClass->id);
        $exam = $this->exam($organization, $teacher, 'published');
        $exam->update([
            'discipline_id' => $discipline->id,
            'settings' => [
                'application_mode' => 'hybrid',
                '_wizard' => ['current_step' => 'preview', 'revision' => 4],
            ],
        ]);
        $exam->schoolClasses()->attach($schoolClass->id);
        $exam->students()->attach([$classStudent->id, $directStudent->id], [
            'organization_id' => $organization->id,
            'assigned_by' => $teacher->id,
        ]);

        Sanctum::actingAs($teacher);
        $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->getJson('/api/v1/exams')
            ->assertOk()
            ->assertJsonPath('exams.0.discipline.id', $discipline->id);

        $response = $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->getJson("/api/v1/exams/{$exam->id}/download")
            ->assertOk()
            ->assertJsonPath('exam.discipline.id', $discipline->id)
            ->assertJsonCount(2, 'students');

        $this->assertEqualsCanonicalizing(
            [$classStudent->id, $directStudent->id],
            collect($response->json('students'))->pluck('id')->all(),
        );
        $this->assertArrayNotHasKey('_wizard', $response->json('exam.settings'));
    }

    public function test_duplication_preserves_authorized_discipline_and_rejects_same_tenant_idor(): void
    {
        $organization = $this->organization('Instituição A');
        $teacher = $this->user($organization, 'teacher');
        $otherTeacher = $this->user($organization, 'teacher');
        $discipline = $this->discipline($organization, 'Geografia');
        $exam = $this->exam($organization, $teacher);
        $exam->update(['discipline_id' => $discipline->id]);

        $this->actingAs($teacher)
            ->post(route('exams.duplicate', $exam))
            ->assertRedirect();

        $copy = Exam::withoutGlobalScopes()
            ->where('author_id', $teacher->id)
            ->where('id', '!=', $exam->id)
            ->sole();
        $this->assertSame($discipline->id, $copy->discipline_id);
        $this->assertSame('questions', $copy->settings['_wizard']['current_step']);
        $this->assertSame(['information'], $copy->settings['_wizard']['completed_steps']);

        $this->actingAs($otherTeacher)
            ->post(route('exams.duplicate', $exam))
            ->assertNotFound();

        $this->assertSame(2, Exam::withoutGlobalScopes()->count());
    }

    private function organization(string $name): Organization
    {
        return Organization::create(['name' => $name, 'active' => true]);
    }

    private function user(Organization $organization, string $role): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function schoolClass(Organization $organization, string $name): SchoolClass
    {
        return SchoolClass::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'year' => '2026',
        ]);
    }

    private function discipline(Organization $organization, string $name): Discipline
    {
        return Discipline::create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }

    private function exam(Organization $organization, User $teacher, string $status = 'draft'): Exam
    {
        return Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação de público',
            'status' => $status,
            'settings' => ['application_mode' => 'hybrid'],
        ]);
    }

    private function assignTeacherScope(
        Organization $organization,
        User $teacher,
        User $student,
        SchoolClass $schoolClass,
        Discipline $discipline,
    ): void {
        DB::table('class_teacher')->insert([
            'school_class_id' => $schoolClass->id,
            'user_id' => $teacher->id,
            'assigned_at' => now(),
        ]);
        DB::table('teacher_student')->insert([
            'organization_id' => $organization->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'linked_by' => $teacher->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discipline_teacher')->insert([
            'organization_id' => $organization->id,
            'discipline_id' => $discipline->id,
            'user_id' => $teacher->id,
            'assigned_by' => $teacher->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
