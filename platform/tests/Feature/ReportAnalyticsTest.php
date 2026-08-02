<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('teacher', 'web');
    }

    public function test_reports_normalize_scores_and_only_include_the_teachers_exams(): void
    {
        $organization = Organization::create([
            'name' => 'Escola Relatórios',
            'active' => true,
        ]);

        $teacher = $this->createTeacher($organization, 'teacher@reports.test');
        $otherTeacher = $this->createTeacher($organization, 'other@reports.test');
        $student = User::create([
            'name' => 'Estudante',
            'email' => 'student@reports.test',
            'password' => 'password',
            'organization_id' => $organization->id,
            'type' => 'student',
            'status' => 'active',
        ]);

        $firstExam = $this->createGradedExam($organization, $teacher, $student, 'Prova de 10 pontos', 10, 5);
        $secondExam = $this->createGradedExam($organization, $teacher, $student, 'Prova de 20 pontos', 20, 10);
        $foreignExam = $this->createGradedExam($organization, $otherTeacher, $student, 'Prova de outro professor', 100, 100);

        $response = $this->actingAs($teacher)->get(route('institution.reports'));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats): bool {
            return $stats['total_submissions'] === 2
                && $stats['graded'] === 2
                && $stats['average'] === 50.0
                && $stats['median'] === 50.0
                && $stats['min'] === 50.0
                && $stats['max'] === 50.0;
        });
        $response->assertViewHas('exams', function ($exams) use ($firstExam, $secondExam, $foreignExam): bool {
            return $exams->pluck('id')->sort()->values()->all()
                    === collect([$firstExam->id, $secondExam->id])->sort()->values()->all()
                && ! $exams->pluck('id')->contains($foreignExam->id);
        });
        $response->assertSee('50.0%', false);
        $response->assertDontSee('Prova de outro professor');
    }

    public function test_teacher_cannot_filter_reports_by_another_teachers_exam(): void
    {
        $organization = Organization::create([
            'name' => 'Escola Filtros',
            'active' => true,
        ]);

        $teacher = $this->createTeacher($organization, 'teacher-filter@reports.test');
        $otherTeacher = $this->createTeacher($organization, 'other-filter@reports.test');
        $student = User::create([
            'name' => 'Estudante',
            'email' => 'student-filter@reports.test',
            'password' => 'password',
            'organization_id' => $organization->id,
            'type' => 'student',
            'status' => 'active',
        ]);
        $foreignExam = $this->createGradedExam($organization, $otherTeacher, $student, 'Fora do escopo', 10, 8);

        $response = $this->actingAs($teacher)->get(route('institution.reports', [
            'exam_id' => $foreignExam->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('exam_id');
    }

    public function test_report_exposes_live_progress_and_discipline_breakdown(): void
    {
        $organization = Organization::create([
            'name' => 'Escola Tempo Real',
            'active' => true,
        ]);
        $teacher = $this->createTeacher($organization, 'teacher-live@reports.test');
        $student = User::create([
            'name' => 'Estudante ao vivo',
            'email' => 'student-live@reports.test',
            'password' => 'password',
            'organization_id' => $organization->id,
            'type' => 'student',
            'status' => 'active',
        ]);
        $discipline = Discipline::create([
            'organization_id' => $organization->id,
            'name' => 'Matemática',
        ]);
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'discipline_id' => $discipline->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão acompanhada',
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);
        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Aplicação ao vivo',
            'status' => 'published',
            'settings' => [],
        ]);
        $exam->questions()->attach($question->id, [
            'points' => 2,
            'order' => 1,
        ]);
        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        DB::table('exam_answers')->insert([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => json_encode(['raw' => 0]),
            'points_awarded' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get(route('institution.reports'));

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats): bool => $stats['in_progress'] === 1);
        $response->assertViewHas('activeSubmissions', function ($active): bool {
            return $active->count() === 1
                && $active->first()->answered === 1
                && $active->first()->progress === 100.0;
        });
        $response->assertSee('Aplicações em andamento');
    }

    private function createTeacher(Organization $organization, string $email): User
    {
        $teacher = User::create([
            'name' => 'Professor',
            'email' => $email,
            'password' => 'password',
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        return $teacher;
    }

    private function createGradedExam(
        Organization $organization,
        User $teacher,
        User $student,
        string $title,
        float $totalPoints,
        float $score
    ): Exam {
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => "Questão de {$title}",
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);

        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => $title,
            'status' => 'published',
            'settings' => [],
        ]);
        $exam->questions()->attach($question->id, [
            'points' => $totalPoints,
            'order' => 1,
        ]);

        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'score' => $score,
        ]);
        DB::table('exam_answers')->insert([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => json_encode(['selected' => 0]),
            'is_correct' => $score >= $totalPoints,
            'points_awarded' => $score,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $exam;
    }
}
