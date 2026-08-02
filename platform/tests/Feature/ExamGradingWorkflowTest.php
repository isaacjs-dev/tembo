<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamGradingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Escola de Teste',
            'active' => true,
        ]);
        $this->teacher = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'teacher',
        ]);
        $this->teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $this->student = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'student',
        ]);
        $this->exam = Exam::create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação de teste',
            'status' => 'published',
            'access_code' => 'T123',
            'settings' => [],
        ]);
    }

    public function test_grade_page_reads_online_and_omr_answer_formats_without_double_decoding(): void
    {
        $onlineQuestion = $this->createQuestion([
            'statement' => 'Questão online',
            'options' => ['Alternativa Alfa', 'Alternativa Beta'],
            'correct_option' => 1,
        ]);
        $omrQuestion = $this->createQuestion([
            'statement' => 'Questão OMR',
            'options' => ['Alternativa Gama', 'Alternativa Delta'],
            'correct_option' => 0,
        ]);
        $this->exam->questions()->attach($onlineQuestion->id, ['points' => 2, 'order' => 1]);
        $this->exam->questions()->attach($omrQuestion->id, ['points' => 3, 'order' => 2]);

        $submission = $this->createSubmission();
        ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $onlineQuestion->id,
            'answer_data' => ['raw' => 1],
            'is_correct' => true,
            'points_awarded' => 2,
        ]);
        ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $omrQuestion->id,
            'answer_data' => [
                'selected' => 0,
                'visual' => 'A',
                'original_raw' => 0,
            ],
            'is_correct' => true,
            'points_awarded' => 3,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('exams.gradeSubmission', [$this->exam, $submission]))
            ->assertOk()
            ->assertSee('Alternativa Beta')
            ->assertSee('Alternativa Gama');
    }

    public function test_teacher_can_store_complete_grading_feedback_and_detailed_audit_log(): void
    {
        $question = $this->createQuestion([
            'statement' => 'Explique o conceito.',
            'rubric' => [
                'title' => 'Rubrica conceitual',
                'description' => 'Critérios esperados',
                'criteria' => [
                    ['title' => 'Precisão', 'description' => null, 'points' => 3],
                    ['title' => 'Clareza', 'description' => null, 'points' => 2],
                ],
            ],
        ], 'essay');
        $this->exam->questions()->attach($question->id, ['points' => 5, 'order' => 1]);

        $submission = $this->createSubmission();
        $answer = ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => ['raw' => 'Resposta discursiva'],
            'is_correct' => null,
            'points_awarded' => 0,
        ]);

        $response = $this->actingAs($this->teacher)->post(
            route('exams.storeGrade', [$this->exam, $submission]),
            [
                'points' => [$answer->id => 4.5],
                'feedback' => [$answer->id => 'Boa argumentação; revise o exemplo final.'],
                'justification' => [$answer->id => 'Atendeu parcialmente ao segundo critério.'],
                'rubric_scores' => [$answer->id => [2.75, 1.75]],
                'general_feedback' => 'Bom desempenho geral.',
            ]
        );

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('exams.show', $this->exam));

        $gradedAnswer = $answer->fresh();
        $gradedSubmission = $submission->fresh();

        $this->assertSame('4.50', $gradedAnswer->points_awarded);
        $this->assertFalse($gradedAnswer->is_correct);
        $this->assertSame('Boa argumentação; revise o exemplo final.', $gradedAnswer->feedback);
        $this->assertSame('Atendeu parcialmente ao segundo critério.', $gradedAnswer->grading_justification);
        $this->assertEquals([2.75, 1.75], $gradedAnswer->rubric_scores);
        $this->assertSame('4.50', $gradedSubmission->score);
        $this->assertSame('graded', $gradedSubmission->status);
        $this->assertSame('Bom desempenho geral.', $gradedSubmission->feedback);

        $audit = AuditLog::where('action', 'submission_graded')->sole();
        $this->assertSame(ExamSubmission::class, $audit->model_type);
        $this->assertSame($submission->id, $audit->model_id);
        $this->assertSame($this->exam->id, $audit->payload['exam_id']);
        $this->assertSame($answer->id, $audit->payload['answers'][0]['answer_id']);
        $this->assertSame(5, $audit->payload['answers'][0]['maximum_points']);
        $this->assertSame(4.5, $audit->payload['answers'][0]['new']['points_awarded']);
    }

    public function test_grade_above_exam_pivot_maximum_is_rejected_without_partial_writes(): void
    {
        $question = $this->createQuestion([
            'statement' => 'Questão limitada',
            'options' => ['A', 'B'],
            'correct_option' => 0,
        ]);
        $this->exam->questions()->attach($question->id, ['points' => 2, 'order' => 1]);

        $submission = $this->createSubmission(['score' => 1]);
        $answer = ExamAnswer::create([
            'exam_submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer_data' => ['raw' => 0],
            'is_correct' => false,
            'points_awarded' => 1,
            'feedback' => 'Feedback anterior',
        ]);

        $this->actingAs($this->teacher)
            ->from(route('exams.gradeSubmission', [$this->exam, $submission]))
            ->post(route('exams.storeGrade', [$this->exam, $submission]), [
                'points' => [$answer->id => 3],
                'feedback' => [$answer->id => 'Não deve ser gravado'],
            ])
            ->assertSessionHasErrors(["points.{$answer->id}"]);

        $this->assertSame('1.00', $answer->fresh()->points_awarded);
        $this->assertSame('Feedback anterior', $answer->fresh()->feedback);
        $this->assertSame('1.00', $submission->fresh()->score);
        $this->assertSame(0, AuditLog::where('action', 'submission_graded')->count());
    }

    private function createQuestion(array $content, string $type = 'multiple_choice'): Question
    {
        return Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => $type,
            'visibility_scope' => 'private',
            'content' => $content,
            'stage' => 'ef_finais',
            'grade' => '6',
        ]);
    }

    private function createSubmission(array $overrides = []): ExamSubmission
    {
        return ExamSubmission::create(array_merge([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'status' => 'submitted',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'score' => 0,
        ], $overrides));
    }
}
