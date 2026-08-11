<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAttempt;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PedagogicalDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->organization = Organization::create(['name' => 'Escola Entregas', 'active' => true]);
        $this->teacher = User::factory()->create(['organization_id' => $this->organization->id, 'type' => 'teacher']);
        $this->teacher->assignRole('teacher');
        $this->student = User::factory()->create(['organization_id' => $this->organization->id, 'type' => 'student']);
        $this->student->assignRole('student');
        $this->class = SchoolClass::create([
            'organization_id' => $this->organization->id, 'owner_type' => 'user', 'owner_id' => $this->teacher->id,
            'name' => 'Turma Entregas', 'year' => 2026,
        ]);
        $this->class->teachers()->attach($this->teacher->id, ['assigned_at' => now()]);
        $this->class->students()->attach($this->student->id);
    }

    public function test_student_only_sees_published_assigned_and_available_content(): void
    {
        $lesson = $this->lesson();
        $draft = $this->lesson('draft');
        $future = $this->lesson();
        $future->update(['starts_at' => now()->addDay()]);
        $activity = $this->activity();

        $this->actingAs($this->student)->get(route('student.pedagogical.index'))
            ->assertOk()->assertSee($lesson->title)->assertSee($activity->title)->assertDontSee($draft->title)->assertDontSee($future->title);
        $this->actingAs($this->student)->get(route('student.pedagogical.lessons.show', $draft))->assertNotFound();
    }

    public function test_teacher_publication_delivers_lesson_and_activity_to_the_selected_class(): void
    {
        $question = $this->question('multiple_choice', [
            'statement' => 'Selecione a resposta.', 'options' => ['A', 'B'], 'correct_option' => 0,
        ]);
        $this->actingAs($this->teacher)->post(route('lessons.store'), [
            'title' => 'Aula publicada pelo fluxo', 'objectives' => 'Objetivo', 'content' => 'Conteúdo entregue',
            'status' => 'published', 'class_ids' => [$this->class->id],
        ])->assertRedirect();
        $this->actingAs($this->teacher)->post(route('activities.store'), [
            'title' => 'Atividade publicada pelo fluxo', 'instructions' => 'Instruções', 'max_attempts' => 1,
            'points' => 5, 'modality' => 'online', 'status' => 'published',
            'class_ids' => [$this->class->id], 'question_ids' => [$question->id],
        ])->assertRedirect();

        $this->actingAs($this->student)->get(route('student.pedagogical.index'))->assertOk()
            ->assertSee('Aula publicada pelo fluxo')->assertSee('Atividade publicada pelo fluxo');
        $this->assertNotNull(Lesson::where('title', 'Aula publicada pelo fluxo')->firstOrFail()->published_at);
        $this->assertNotNull(Activity::where('title', 'Atividade publicada pelo fluxo')->firstOrFail()->published_at);
    }

    public function test_lesson_progress_uses_snapshot_and_completion_is_idempotent(): void
    {
        $lesson = $this->lesson();
        $this->actingAs($this->student)->get(route('student.pedagogical.lessons.show', $lesson))
            ->assertOk()->assertSee('Conteúdo original');
        $lesson->update(['content' => 'Conteúdo alterado']);
        $this->actingAs($this->student)->get(route('student.pedagogical.lessons.show', $lesson))
            ->assertOk()->assertSee('Conteúdo original')->assertDontSee('Conteúdo alterado');
        $this->actingAs($this->student)->post(route('student.pedagogical.lessons.complete', $lesson))->assertRedirect();
        $this->actingAs($this->student)->post(route('student.pedagogical.lessons.complete', $lesson))->assertRedirect();
        $this->assertDatabaseCount('lesson_progress', 1);
        $this->assertDatabaseHas('lesson_progress', ['lesson_id' => $lesson->id, 'student_id' => $this->student->id, 'status' => 'completed']);
        $this->assertSame(1, AuditLog::query()->where('action', 'lesson_completed')->count());
        $this->actingAs($this->teacher)->delete(route('lessons.destroy', $lesson))->assertSessionHasErrors('lesson');
        $this->assertNull($lesson->fresh()->deleted_at);
        $this->class->students()->detach($this->student->id);
        $studentName = $this->student->name;
        $this->student->delete();
        $this->actingAs($this->teacher)->get(route('lessons.report', $lesson))->assertOk()
            ->assertSee('Concluída')->assertSee($studentName);
    }

    public function test_objective_activity_saves_resumes_and_grades_the_attempt_snapshot(): void
    {
        $question = $this->question('multiple_choice', ['statement' => 'Quanto é 2 + 2?', 'options' => ['3', '4'], 'correct_option' => 1]);
        $activity = $this->activity($question, 10);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity))->assertRedirect();
        $attempt = ActivityAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity))
            ->assertRedirect(route('student.pedagogical.activities.execute', [$activity->id, $attempt->id]));
        $this->assertDatabaseCount('activity_attempts', 1);

        $this->actingAs($this->student)->post(route('student.pedagogical.activities.save', [$activity, $attempt]), [
            'answers' => [$question->id => '1'],
        ])->assertRedirect();
        $question->update(['content' => ['statement' => 'Alterada', 'options' => ['4', '3'], 'correct_option' => 0]]);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.submit', [$activity, $attempt]), [
            'answers' => [$question->id => '1'],
        ])->assertRedirect(route('student.pedagogical.activities.result', [$activity->id, $attempt->id]));
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.submit', [$activity, $attempt]), [
            'answers' => [$question->id => '0'],
        ])->assertRedirect(route('student.pedagogical.activities.result', [$activity->id, $attempt->id]));
        $this->assertDatabaseHas('activity_attempts', ['id' => $attempt->id, 'status' => 'graded', 'score' => 10]);
        $this->assertSame(1, AuditLog::query()->where('action', 'activity_submitted')->count());
        $this->actingAs($this->student)->get(route('student.pedagogical.activities.result', [$activity, $attempt]))
            ->assertOk()->assertSee('10,0 / 10,0');
    }

    public function test_essay_activity_waits_for_authorized_manual_grading(): void
    {
        $question = $this->question('essay', ['statement' => 'Explique o ciclo da água.']);
        $activity = $this->activity($question, 8);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity));
        $attempt = ActivityAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.submit', [$activity, $attempt]), [
            'answers' => [$question->id => 'Evaporação e chuva.'],
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_attempts', ['id' => $attempt->id, 'status' => 'submitted']);
        $this->actingAs($this->student)->get(route('student.pedagogical.activities.result', [$activity, $attempt]))
            ->assertOk()->assertSee('aguardam correção');

        $this->actingAs($this->teacher)->post(route('activities.attempts.grade', [$activity, $attempt]), [
            'scores' => [$question->id => 7], 'feedback' => [$question->id => 'Boa resposta.'],
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_attempts', ['id' => $attempt->id, 'status' => 'graded', 'score' => 7, 'graded_by' => $this->teacher->id]);
        $this->actingAs($this->teacher)->get(route('activities.report', $activity))->assertOk()->assertSee('Boa resposta.');
        $this->actingAs($this->teacher)->delete(route('activities.destroy', $activity))->assertSessionHasErrors('activity');
        $this->assertNull($activity->fresh()->deleted_at);
    }

    public function test_points_are_distributed_in_cents_without_losing_the_perfect_total(): void
    {
        $questions = collect(range(1, 3))->map(fn ($number) => $this->question('multiple_choice', [
            'statement' => "Questão $number", 'options' => ['Correta', 'Errada'], 'correct_option' => 0,
        ]));
        $activity = $this->activity($questions->first(), 10);
        foreach ($questions->slice(1)->values() as $order => $question) {
            $activity->questions()->attach($question->id, ['order' => $order + 1, 'points' => 1]);
        }
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity));
        $attempt = ActivityAttempt::firstOrFail();
        $answers = $questions->mapWithKeys(fn ($question) => [$question->id => '0'])->all();
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.submit', [$activity, $attempt]), [
            'answers' => $answers,
        ])->assertRedirect();

        $this->assertSame('10.00', $attempt->fresh()->score);
        $this->assertSame([3.34, 3.33, 3.33], collect($attempt->fresh()->content_snapshot['questions'])->pluck('points')->all());
    }

    public function test_delivery_and_attempt_ids_are_tenant_and_student_scoped(): void
    {
        $question = $this->question('essay', ['statement' => 'Resposta']);
        $activity = $this->activity($question, 2);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity));
        $attempt = ActivityAttempt::firstOrFail();
        $other = User::factory()->create(['organization_id' => $this->organization->id, 'type' => 'student']);
        $other->assignRole('student');
        $this->actingAs($other)->get(route('student.pedagogical.activities.execute', [$activity, $attempt]))->assertNotFound();

        $foreignOrganization = Organization::create(['name' => 'Outra Escola', 'active' => true]);
        $foreignTeacher = User::factory()->create(['organization_id' => $foreignOrganization->id, 'type' => 'teacher']);
        $foreignLesson = Lesson::create([
            'organization_id' => $foreignOrganization->id, 'author_id' => $foreignTeacher->id,
            'title' => 'Aula externa', 'content' => 'Segredo', 'status' => 'published',
        ]);
        $this->actingAs($this->student)->get(route('student.pedagogical.lessons.show', $foreignLesson))->assertNotFound();
    }

    public function test_max_attempts_and_deadline_are_enforced_without_losing_history(): void
    {
        $activity = $this->activity(null, 0);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity));
        $attempt = ActivityAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.submit', [$activity, $attempt]))->assertRedirect();
        $this->assertDatabaseHas('activity_attempts', ['id' => $attempt->id, 'status' => 'submitted']);
        $this->actingAs($this->teacher)->post(route('activities.attempts.grade', [$activity, $attempt]), [
            'overall_score' => 0,
        ])->assertRedirect();
        $this->assertDatabaseHas('activity_attempts', ['id' => $attempt->id, 'status' => 'graded']);
        $this->actingAs($this->student)->post(route('student.pedagogical.activities.start', $activity))->assertStatus(409);
        $activity->update(['status' => 'archived']);
        $this->actingAs($this->student)->get(route('student.pedagogical.activities.result', [$activity, $attempt]))->assertOk();
    }

    private function lesson(string $status = 'published'): Lesson
    {
        $lesson = Lesson::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Aula '.uniqid(), 'objectives' => 'Aprender', 'content' => 'Conteúdo original',
            'status' => $status, 'published_at' => $status === 'published' ? now() : null,
        ]);
        $lesson->schoolClasses()->attach($this->class->id);

        return $lesson;
    }

    private function activity(?Question $question = null, float $points = 10): Activity
    {
        $activity = Activity::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Atividade '.uniqid(), 'instructions' => 'Responda com atenção.', 'max_attempts' => 1,
            'points' => $points, 'modality' => 'online', 'status' => 'published', 'published_at' => now(),
        ]);
        $activity->schoolClasses()->attach($this->class->id);
        if ($question) {
            $activity->questions()->attach($question->id, ['order' => 0, 'points' => $points]);
        }

        return $activity;
    }

    private function question(string $type, array $content): Question
    {
        return Question::create([
            'organization_id' => $this->organization->id, 'owner_id' => $this->teacher->id,
            'type' => $type, 'content' => $content, 'visibility_scope' => 'private',
        ]);
    }
}
