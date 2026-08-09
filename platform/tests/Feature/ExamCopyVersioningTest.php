<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckOmrApiAccess;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamPrintService;
use App\Services\OmrGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamCopyVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_are_individualized_snapshotted_and_numbered_across_batches(): void
    {
        [$exam, $question, $students] = $this->context();
        $service = app(ExamPrintService::class);
        $templateSnapshot = [
            'id' => 10,
            'version' => 3,
            'name' => 'Cartão histórico',
            'layout_config' => ['rows_per_column' => 20],
        ];

        $firstBatch = $service->generateCopies($exam, 2, [
            'output_type' => 'both',
            'card_template_version' => 3,
            'template_snapshot' => $templateSnapshot,
        ], null, $students->pluck('id')->all());

        $this->assertSame($students->pluck('id')->all(), $firstBatch->pluck('student_id')->all());
        $this->assertSame([1, 2], $firstBatch->pluck('copy_number')->all());
        $this->assertCount(1, $firstBatch->pluck('generation_uuid')->unique());
        $this->assertSame(1, $firstBatch->first()->exam_version);
        $this->assertEquals(2.0, $firstBatch->first()->question_snapshot[0]['points']);
        $this->assertSame(0, data_get($firstBatch->first()->question_snapshot, '0.content.correct_option'));
        $this->assertSame($templateSnapshot, $firstBatch->first()->template_snapshot);

        Sanctum::actingAs($exam->author);
        $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->getJson("/api/v1/exams/{$exam->id}/download")
            ->assertOk()
            ->assertJsonPath('exam.version', 1)
            ->assertJsonPath('copies.0.student_id', $students->first()->id)
            ->assertJsonPath('copies.0.exam_version', 1)
            ->assertJsonPath('copies.0.card_template_version', 3)
            ->assertJsonPath('copies.0.question_snapshot.0.correct_option', 0)
            ->assertJsonPath('copies.0.question_snapshot.0.points', 2);

        $question->update(['content' => [
            'statement' => 'Conteúdo alterado depois da primeira impressão',
            'options' => ['A', 'B'],
            'correct_option' => 1,
        ]]);
        $exam->questions()->updateExistingPivot($question->id, ['points' => 5]);
        $exam->unsetRelation('questions');

        $secondBatch = $service->generateCopies($exam->fresh(), 1, ['output_type' => 'exam']);
        $this->assertSame(3, $secondBatch->first()->copy_number);
        $this->assertSame(2, $secondBatch->first()->exam_version);
        $this->assertSame(2, $exam->fresh()->version);
        $this->assertSame(1, $firstBatch->first()->fresh()->exam_version);
        $this->assertSame(0, data_get($firstBatch->first()->fresh()->question_snapshot, '0.content.correct_option'));

        $this->getJson("/api/v1/exams/{$exam->id}/download")
            ->assertOk()
            ->assertJsonPath('exam.version', 2)
            ->assertJsonPath('copies.0.question_snapshot.0.correct_option', 0)
            ->assertJsonPath('copies.2.exam_version', 2)
            ->assertJsonPath('copies.2.question_snapshot.0.correct_option', 1);
    }

    public function test_historical_copy_grades_with_its_immutable_answer_key_and_points(): void
    {
        [$exam, $question] = $this->context();
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1)->first();

        $question->update(['content' => [
            'statement' => 'Gabarito atual alterado',
            'options' => ['A', 'B'],
            'correct_option' => 1,
        ]]);
        $exam->questions()->updateExistingPivot($question->id, ['points' => 9]);

        $result = app(OmrGradingService::class)->gradeAnswers($exam->id, $copy->id, [
            $question->id => 0,
        ]);

        $this->assertSame(2.0, $result['score']);
        $this->assertSame(2.0, $result['total_points']);
        $this->assertTrue($result['details'][$question->id]['correct']);
    }

    public function test_pdf_outputs_never_append_the_teacher_key_implicitly(): void
    {
        [$exam] = $this->context();
        $exam->load(['questions.discipline', 'organization']);
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1, ['output_type' => 'exam'])->first();
        $data = [
            'exam' => $exam,
            'copies' => collect([$copy]),
            'calibration' => [],
            'options' => [],
            'cardPagesByCopy' => [],
        ];

        $studentPdf = view('exams.pdf_advanced', $data + ['outputType' => 'exam'])->render();
        $this->assertStringContainsString('Caderno de Prova', $studentPdf);
        $this->assertStringNotContainsString('GABARITO DO PROFESSOR', $studentPdf);
        $this->assertStringNotContainsString('class="prof-key"', $studentPdf);
        $this->assertStringNotContainsString('CARTÃO RESPOSTA', $studentPdf);

        $teacherPdf = view('exams.pdf_advanced', $data + ['outputType' => 'answer_key'])->render();
        $this->assertStringContainsString('GABARITO DO PROFESSOR', $teacherPdf);
        $this->assertStringContainsString('class="prof-key"', $teacherPdf);
        $this->assertStringNotContainsString('Caderno de Prova', $teacherPdf);
        $this->assertStringNotContainsString('CARTÃO RESPOSTA', $teacherPdf);
    }

    /** @return array{Exam, Question, Collection<int, User>} */
    private function context(): array
    {
        $organization = Organization::create(['name' => 'Escola Cópias', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
        ]);
        $students = User::factory()->count(2)->create([
            'organization_id' => $organization->id,
            'type' => 'student',
        ]);
        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação versionada',
            'status' => 'published',
            'settings' => ['application_mode' => 'printed_omr'],
        ]);
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão original',
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 2, 'order' => 1]);

        return [$exam, $question, $students];
    }
}
