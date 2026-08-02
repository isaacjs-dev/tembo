<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('teacher', 'web');
        $this->organization = Organization::create([
            'name' => 'Escola Configuração',
            'active' => true,
        ]);
        $this->teacher = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');
    }

    public function test_creation_stores_complete_rules_as_a_draft(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('exams.store'), [
            'title' => 'Avaliação híbrida',
            'status' => 'published',
            'settings_form' => '1',
            'application_mode' => 'hybrid',
            'instructions' => 'Use apenas lápis e registre os cálculos.',
            'time_limit' => 45,
            'attempts' => 2,
            'available_from' => '2026-08-01T08:00',
            'available_until' => '2026-08-01T18:00',
            'results_available_from' => '2026-08-02T08:00',
            'shuffle_questions' => '1',
            'shuffle_options' => '1',
            'show_score' => '1',
            'show_feedback' => '1',
        ]);

        $exam = Exam::withoutGlobalScopes()->sole();
        $response->assertRedirect(route('exams.edit', $exam));
        $this->assertSame('draft', $exam->status);
        $this->assertSame('hybrid', $exam->settings['application_mode']);
        $this->assertSame(45, $exam->settings['time_limit']);
        $this->assertSame(2, $exam->settings['attempts']);
        $this->assertTrue($exam->settings['shuffle_questions']);
        $this->assertTrue($exam->settings['shuffle_options']);
        $this->assertTrue($exam->settings['show_score']);
        $this->assertFalse($exam->settings['show_answers']);
        $this->assertTrue($exam->settings['show_feedback']);
        $this->assertSame(1, AuditLog::where('action', 'exam_created')->count());
    }

    public function test_an_empty_or_zero_point_exam_cannot_be_published(): void
    {
        $exam = $this->makeExam();

        $this->actingAs($this->teacher)
            ->put(route('exams.update', $exam), [
                'title' => $exam->title,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('status');

        $question = $this->makeQuestion();
        $exam->questions()->attach($question->id, ['points' => 0, 'order' => 1]);

        $this->actingAs($this->teacher)
            ->put(route('exams.update', $exam), [
                'title' => $exam->title,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_quick_publish_preserves_settings_and_generates_an_access_code(): void
    {
        $exam = $this->makeExam([
            'application_mode' => 'online',
            'attempts' => 3,
            'show_score' => false,
            'show_answers' => false,
            'show_feedback' => true,
        ]);
        $question = $this->makeQuestion();
        $exam->questions()->attach($question->id, ['points' => 5, 'order' => 1]);

        $response = $this->actingAs($this->teacher)
            ->put(route('exams.update', $exam), [
                'title' => $exam->title,
                'status' => 'published',
            ]);

        $response->assertSessionHasNoErrors();
        $exam->refresh();
        $this->assertSame('published', $exam->status);
        $this->assertSame('online', $exam->settings['application_mode']);
        $this->assertSame(3, $exam->settings['attempts']);
        $this->assertFalse($exam->settings['show_score']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $exam->access_code);
    }

    public function test_scheduled_results_and_paper_mode_are_enforced_by_the_access_service(): void
    {
        $this->travelTo(now()->startOfMinute());
        $exam = $this->makeExam([
            'application_mode' => 'paper',
            'show_score' => true,
            'show_answers' => true,
            'show_feedback' => true,
            'results_available_from' => now()->addHour()->toIso8601String(),
        ]);
        $service = app(ExamAccessService::class);

        $this->assertFalse($service->supportsOnline($exam));
        $this->assertSame([
            'show_score' => false,
            'show_answers' => false,
            'show_feedback' => false,
        ], $service->releaseSettings($exam));

        $this->travel(61)->minutes();
        $this->assertTrue($service->releaseSettings($exam)['show_score']);
        $this->assertTrue($service->releaseSettings($exam)['show_answers']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function makeExam(array $settings = []): Exam
    {
        return Exam::create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação configurável',
            'status' => 'draft',
            'settings' => $settings,
        ]);
    }

    private function makeQuestion(): Question
    {
        return Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Questão objetiva',
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);
    }
}
