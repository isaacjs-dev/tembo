<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\Organization;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private User $student;

    private SchoolClass $studentClass;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->organization = Organization::create([
            'name' => 'Escola Portal',
            'subdomain' => 'portal-'.Str::lower(Str::random(8)),
            'active' => true,
        ]);
        $this->teacher = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'teacher',
        ]);
        $this->teacher->assignRole('teacher');
        $this->student = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'student',
        ]);
        $this->student->assignRole('student');
        $this->studentClass = $this->makeClass('Turma do aluno');
        $this->studentClass->students()->attach($this->student->id);
    }

    public function test_student_cannot_open_exam_from_another_class_without_code_grant(): void
    {
        $otherClass = $this->makeClass('Outra turma');
        $exam = $this->makeExam(['school_class' => $otherClass]);

        $this->actingAs($this->student)
            ->get(route('student.exam.show', $exam))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->post(route('student.exam.start', $exam))
            ->assertForbidden();
    }

    public function test_access_code_creates_a_session_grant_and_dashboard_form_uses_join_route(): void
    {
        $exam = $this->makeExam([
            'school_class' => $this->makeClass('Turma sem matrícula'),
            'access_code' => 'ABC123',
        ]);

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('student.joinByCode').'"', false);

        $this->actingAs($this->student)
            ->post(route('student.joinByCode'), ['access_code' => 'abc123'])
            ->assertRedirect(route('student.exam.show', $exam))
            ->assertSessionHas("student_exam_grants.{$exam->id}");

        $this->actingAs($this->student)
            ->get(route('student.exam.show', $exam))
            ->assertOk();
    }

    public function test_future_and_expired_availability_windows_are_enforced(): void
    {
        $future = $this->makeExam([
            'settings' => [
                'attempts' => 1,
                'available_from' => now()->addHour()->toIso8601String(),
            ],
        ]);
        $expired = $this->makeExam([
            'settings' => [
                'attempts' => 1,
                'available_until' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exam.show', $future))
            ->assertForbidden();
        $this->actingAs($this->student)
            ->post(route('student.exam.start', $expired))
            ->assertForbidden();
    }

    public function test_started_code_grant_survives_a_new_session_but_remains_private(): void
    {
        $exam = $this->makeExam([
            'school_class' => $this->makeClass('Turma por código'),
            'access_code' => 'KEEP42',
            'settings' => ['attempts' => 1, 'show_results' => true],
        ]);
        $question = $this->addQuestion($exam, 'multiple_choice', [
            'statement' => 'Questão persistente',
            'options' => ['Correta', 'Incorreta'],
            'correct_option' => 0,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.joinByCode'), ['access_code' => 'KEEP42'])
            ->assertRedirect(route('student.exam.show', $exam));
        $submission = $this->startAttempt($exam);
        $this->submitAttempt($exam, $submission, [$question->id => 0]);

        $this->flushSession();

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($exam->title);
        $this->actingAs($this->student)
            ->get(route('student.exam.results', $exam))
            ->assertOk()
            ->assertSee('Questão persistente');

        $otherStudent = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'student',
        ]);
        $otherStudent->assignRole('student');

        $this->flushSession();
        $this->actingAs($otherStudent)
            ->get(route('student.exam.show', $exam))
            ->assertForbidden();
    }

    public function test_true_false_uses_zero_one_indices_and_is_graded_correctly(): void
    {
        $exam = $this->makeExam([
            'settings' => ['attempts' => 1, 'show_results' => true],
        ]);
        $question = $this->addQuestion($exam, 'true_false', [
            'statement' => 'A afirmação é verdadeira.',
            'options' => ['Verdadeiro', 'Falso'],
            'correct_option' => 0,
        ], 2);
        $submission = $this->startAttempt($exam);

        $this->actingAs($this->student)
            ->postJson(route('student.exam.submit', $exam), [
                'client_token' => $submission->client_token,
                'answers' => [$question->id => 0],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'graded')
            ->assertJsonPath('score', 2);

        $answer = ExamAnswer::where('exam_submission_id', $submission->id)->firstOrFail();
        $this->assertTrue($answer->is_correct);
        $this->assertSame('2.00', $answer->points_awarded);
    }

    public function test_autosave_is_idempotent_and_updates_one_answer_row(): void
    {
        $exam = $this->makeExam();
        $question = $this->addQuestion($exam, 'multiple_choice', [
            'statement' => 'Escolha uma opção.',
            'options' => ['A', 'B'],
            'correct_option' => 1,
        ]);
        $submission = $this->startAttempt($exam);

        $endpoint = route('student.exam.autosave', $exam);
        $this->actingAs($this->student)->patchJson($endpoint, [
            'client_token' => $submission->client_token,
            'answers' => [$question->id => 0],
        ])->assertOk()->assertJsonPath('status', 'saved');

        $this->actingAs($this->student)->patchJson($endpoint, [
            'client_token' => $submission->client_token,
            'answers' => [$question->id => 1],
        ])->assertOk()->assertJsonPath('answered_count', 1);

        $this->assertSame(
            1,
            ExamAnswer::where('exam_submission_id', $submission->id)
                ->where('question_id', $question->id)
                ->count()
        );
        $this->assertSame(
            1,
            ExamAnswer::where('exam_submission_id', $submission->id)
                ->firstOrFail()
                ->answer_data['raw']
        );
    }

    public function test_autosave_rejects_a_question_that_does_not_belong_to_the_exam(): void
    {
        $exam = $this->makeExam();
        $otherExam = $this->makeExam();
        $foreignQuestion = $this->addQuestion($otherExam, 'multiple_choice', [
            'statement' => 'Questão de outra prova.',
            'options' => ['A', 'B'],
            'correct_option' => 0,
        ]);
        $submission = $this->startAttempt($exam);

        $this->actingAs($this->student)
            ->patchJson(route('student.exam.autosave', $exam), [
                'client_token' => $submission->client_token,
                'answers' => [$foreignQuestion->id => 0],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors("answers.{$foreignQuestion->id}");

        $this->assertDatabaseCount('exam_answers', 0);
    }

    public function test_essay_answer_is_preserved_for_manual_grading(): void
    {
        $exam = $this->makeExam();
        $question = $this->addQuestion($exam, 'essay', [
            'statement' => 'Explique seu raciocínio.',
        ], 5);
        $submission = $this->startAttempt($exam);
        $essay = "Primeiro argumento.\nSegundo argumento.";

        $this->actingAs($this->student)
            ->postJson(route('student.exam.submit', $exam), [
                'client_token' => $submission->client_token,
                'answers' => [$question->id => $essay],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'submitted');

        $answer = ExamAnswer::where('exam_submission_id', $submission->id)->firstOrFail();
        $this->assertSame($essay, $answer->answer_data['raw']);
        $this->assertNull($answer->is_correct);
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_server_timeout_ignores_late_changes_and_grades_saved_answer(): void
    {
        $exam = $this->makeExam(['settings' => ['attempts' => 1, 'time_limit' => 10]]);
        $question = $this->addQuestion($exam, 'multiple_choice', [
            'statement' => 'Qual é a correta?',
            'options' => ['Correta', 'Errada'],
            'correct_option' => 0,
        ], 3);
        $submission = $this->startAttempt($exam);
        ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => ['raw' => 0],
            'points_awarded' => 0,
        ]);
        $submission->update(['deadline_at' => now()->subSecond()]);

        $this->actingAs($this->student)
            ->postJson(route('student.exam.submit', $exam), [
                'client_token' => $submission->client_token,
                'answers' => [$question->id => 1],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'graded');

        $this->assertSame('3.00', $submission->fresh()->score);
        $this->assertSame(
            0,
            ExamAnswer::where('exam_submission_id', $submission->id)->firstOrFail()->answer_data['raw']
        );
    }

    public function test_attempt_limit_creates_numbered_attempts_and_blocks_the_next_one(): void
    {
        $exam = $this->makeExam(['settings' => ['attempts' => 2]]);
        $question = $this->addQuestion($exam, 'multiple_choice', [
            'statement' => 'Questão',
            'options' => ['A', 'B'],
            'correct_option' => 0,
        ]);

        $first = $this->startAttempt($exam);
        $this->submitAttempt($exam, $first, [$question->id => 0]);
        $second = $this->startAttempt($exam);
        $this->submitAttempt($exam, $second, [$question->id => 0]);

        $this->assertSame(1, $first->attempt_number);
        $this->assertSame(2, $second->attempt_number);

        $this->actingAs($this->student)
            ->from(route('student.exam.show', $exam))
            ->post(route('student.exam.start', $exam))
            ->assertRedirect(route('student.exam.show', $exam))
            ->assertSessionHasErrors('attempt');
    }

    public function test_submit_retry_with_same_client_token_is_idempotent(): void
    {
        $exam = $this->makeExam();
        $question = $this->addQuestion($exam, 'multiple_choice', [
            'statement' => 'Questão',
            'options' => ['Correta', 'Errada'],
            'correct_option' => 0,
        ], 4);
        $submission = $this->startAttempt($exam);
        $endpoint = route('student.exam.submit', $exam);

        $this->actingAs($this->student)->postJson($endpoint, [
            'client_token' => $submission->client_token,
            'answers' => [$question->id => 0],
        ])->assertOk()->assertJsonPath('idempotent', false);

        $this->actingAs($this->student)->postJson($endpoint, [
            'client_token' => $submission->client_token,
            'answers' => [$question->id => 1],
        ])->assertOk()->assertJsonPath('idempotent', true);

        $this->assertSame('4.00', $submission->fresh()->score);
        $this->assertSame(1, ExamAnswer::where('exam_submission_id', $submission->id)->count());
        $this->assertSame(
            0,
            ExamAnswer::where('exam_submission_id', $submission->id)->firstOrFail()->answer_data['raw']
        );
    }

    public function test_granular_release_hides_answers_and_does_not_leak_score_when_disabled(): void
    {
        $hiddenExam = $this->makeExam([
            'settings' => [
                'attempts' => 1,
                'show_results' => false,
                'show_score' => false,
                'show_answers' => false,
                'show_feedback' => false,
            ],
        ]);
        $hiddenQuestion = $this->addQuestion($hiddenExam, 'multiple_choice', [
            'statement' => 'Segredo',
            'options' => ['Gabarito secreto', 'Distrator'],
            'correct_option' => 0,
        ]);
        $hiddenSubmission = $this->startAttempt($hiddenExam);

        $this->actingAs($this->student)
            ->postJson(route('student.exam.submit', $hiddenExam), [
                'client_token' => $hiddenSubmission->client_token,
                'answers' => [$hiddenQuestion->id => 0],
            ])
            ->assertOk()
            ->assertJsonMissingPath('score');

        $this->actingAs($this->student)
            ->get(route('student.exam.results', $hiddenExam))
            ->assertRedirect(route('student.dashboard'));

        $scoreOnlyExam = $this->makeExam([
            'settings' => [
                'attempts' => 1,
                'show_results' => false,
                'show_score' => true,
                'show_answers' => false,
                'show_feedback' => false,
            ],
        ]);
        $scoreQuestion = $this->addQuestion($scoreOnlyExam, 'multiple_choice', [
            'statement' => 'Enunciado restrito',
            'options' => ['Resposta restrita', 'Outra'],
            'correct_option' => 0,
        ]);
        $scoreSubmission = $this->startAttempt($scoreOnlyExam);
        $this->submitAttempt($scoreOnlyExam, $scoreSubmission, [$scoreQuestion->id => 0]);

        $this->actingAs($this->student)
            ->get(route('student.exam.results', $scoreOnlyExam))
            ->assertOk()
            ->assertSee('Sua nota')
            ->assertSee('Respostas não liberadas')
            ->assertDontSee('Enunciado restrito')
            ->assertDontSee('Resposta restrita');
    }

    private function makeClass(string $name): SchoolClass
    {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'owner_type' => 'organization',
            'owner_id' => $this->organization->id,
            'name' => $name,
            'year' => '2026',
        ]);
    }

    /**
     * @param  array{school_class?: SchoolClass, settings?: array, access_code?: string}  $overrides
     */
    private function makeExam(array $overrides = []): Exam
    {
        $settings = array_merge([
            'attempts' => 1,
            'time_limit' => null,
            'show_results' => true,
        ], $overrides['settings'] ?? []);

        $exam = Exam::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação '.Str::random(6),
            'status' => 'published',
            'access_code' => $overrides['access_code'] ?? strtoupper(Str::random(6)),
            'settings' => $settings,
        ]);
        $exam->schoolClasses()->attach(($overrides['school_class'] ?? $this->studentClass)->id);

        return $exam;
    }

    private function addQuestion(
        Exam $exam,
        string $type,
        array $content,
        float $points = 1,
    ): Question {
        $question = Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => $type,
            'content' => $content,
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question->id, [
            'points' => $points,
            'order' => $exam->questions()->count() + 1,
        ]);

        return $question;
    }

    private function startAttempt(Exam $exam): ExamSubmission
    {
        $this->actingAs($this->student)
            ->post(route('student.exam.start', $exam))
            ->assertRedirect();

        return ExamSubmission::where('exam_id', $exam->id)
            ->where('user_id', $this->student->id)
            ->orderByDesc('attempt_number')
            ->firstOrFail();
    }

    /**
     * @param  array<int, mixed>  $answers
     */
    private function submitAttempt(Exam $exam, ExamSubmission $submission, array $answers): void
    {
        $this->actingAs($this->student)
            ->postJson(route('student.exam.submit', $exam), [
                'client_token' => $submission->client_token,
                'answers' => $answers,
            ])
            ->assertOk();
    }
}
