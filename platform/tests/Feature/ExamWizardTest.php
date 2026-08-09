<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamWizardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('teacher', 'web');
        $this->organization = Organization::create([
            'name' => 'Escola Wizard',
            'active' => true,
        ]);
        $this->teacher = $this->teacher($this->organization);
    }

    public function test_creation_initializes_a_recoverable_eight_step_wizard(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('exams.store'), [
                'title' => 'Avaliação recuperável',
                'status' => 'draft',
                'settings_form' => true,
                'application_mode' => 'hybrid',
                'attempts' => 1,
            ])
            ->assertRedirect();

        $exam = Exam::withoutGlobalScopes()->sole();
        $wizard = $exam->settings['_wizard'];
        $this->assertSame(1, $wizard['version']);
        $this->assertSame('questions', $wizard['current_step']);
        $this->assertSame(['information'], $wizard['completed_steps']);
        $this->assertSame(1, $wizard['revision']);

        $page = $this->actingAs($this->teacher)->get(route('exams.edit', $exam))->assertOk();
        foreach (['Informações', 'Questões', 'Público', 'Aplicação', 'Aparência', 'Cartão-resposta', 'Pré-visualização', 'Publicação'] as $label) {
            $page->assertSee($label);
        }
        $page->assertSee(route('exams.autosaveDraft', $exam), false);
        $page->assertSee('Seu rascunho é salvo enquanto você avança');
    }

    public function test_information_autosave_persists_data_progress_and_audit(): void
    {
        $exam = $this->exam();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'information',
                'payload' => [
                    'title' => 'Título salvo automaticamente',
                    'instructions' => 'Orientações recuperáveis.',
                ],
                'revision' => 0,
                'complete' => true,
                'target_step' => 'questions',
            ])
            ->assertOk()
            ->assertJsonPath('wizard.current_step', 'questions')
            ->assertJsonPath('wizard.completed_steps.0', 'information')
            ->assertJsonPath('wizard.revision', 1);

        $exam->refresh();
        $this->assertSame('Título salvo automaticamente', $exam->title);
        $this->assertSame('Orientações recuperáveis.', $exam->settings['instructions']);
        $this->assertSame(1, AuditLog::where('action', 'exam_wizard_step_saved')->count());
    }

    public function test_step_autosaves_merge_settings_without_publishing_the_draft(): void
    {
        $exam = $this->exam(['existing_contract' => 'preserved']);

        $application = $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'application',
                'payload' => [
                    'application_mode' => 'online',
                    'time_limit' => 60,
                    'attempts' => 2,
                    'available_from' => '2026-08-10T08:00',
                    'available_until' => '2026-08-10T10:00',
                ],
                'revision' => 0,
            ])
            ->assertOk();

        $appearance = $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'appearance',
                'payload' => [
                    'shuffle_questions' => true,
                    'shuffle_options' => false,
                ],
                'revision' => $application->json('wizard.revision'),
            ])
            ->assertOk();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'publication',
                'payload' => [
                    'show_score' => true,
                    'show_answers' => false,
                    'show_feedback' => true,
                    'results_available_from' => null,
                ],
                'revision' => $appearance->json('wizard.revision'),
            ])
            ->assertOk();

        $exam->refresh();
        $this->assertSame('draft', $exam->status);
        $this->assertSame('preserved', $exam->settings['existing_contract']);
        $this->assertSame('online', $exam->settings['application_mode']);
        $this->assertSame(60, $exam->settings['time_limit']);
        $this->assertTrue($exam->settings['shuffle_questions']);
        $this->assertFalse($exam->settings['shuffle_options']);
        $this->assertTrue($exam->settings['show_score']);
        $this->assertFalse($exam->settings['show_answers']);
    }

    public function test_stale_revision_returns_conflict_without_overwriting_newer_data(): void
    {
        $exam = $this->exam();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'information',
                'payload' => ['title' => 'Versão mais nova', 'instructions' => null],
                'revision' => 0,
            ])
            ->assertOk();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'information',
                'payload' => ['title' => 'Versão obsoleta', 'instructions' => null],
                'revision' => 0,
            ])
            ->assertStatus(409)
            ->assertJsonValidationErrors('revision');

        $this->assertSame('Versão mais nova', $exam->fresh()->title);
    }

    public function test_autosave_rejects_invalid_dates_and_another_authors_exam(): void
    {
        $exam = $this->exam();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'application',
                'payload' => [
                    'application_mode' => 'online',
                    'attempts' => 1,
                    'available_from' => '2026-08-10T10:00',
                    'available_until' => '2026-08-10T09:00',
                ],
                'revision' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('available_until');

        $otherTeacher = $this->teacher($this->organization);
        $this->actingAs($otherTeacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'questions',
                'payload' => [],
                'revision' => 0,
            ])
            ->assertNotFound();

        $this->assertSame('Avaliação em rascunho', $exam->fresh()->title);
    }

    public function test_content_steps_cannot_be_completed_without_scored_questions(): void
    {
        $exam = $this->exam();

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'questions',
                'payload' => [],
                'revision' => 0,
                'complete' => true,
                'target_step' => 'audience',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('questions');

        $question = Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => ['statement' => 'Questão válida', 'options' => ['A', 'B'], 'correct_option' => 0],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 1, 'order' => 1]);

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'questions',
                'payload' => [],
                'revision' => 0,
                'complete' => true,
                'target_step' => 'audience',
            ])
            ->assertOk()
            ->assertJsonPath('wizard.current_step', 'audience');
    }

    public function test_published_exam_rejects_autosave_without_mutation(): void
    {
        $exam = $this->exam();
        $exam->update(['status' => 'published']);

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'information',
                'payload' => ['title' => 'Alteração silenciosa', 'instructions' => null],
                'revision' => 0,
            ])
            ->assertConflict();

        $this->assertSame('Avaliação em rascunho', $exam->fresh()->title);
    }

    public function test_traditional_update_bumps_revision_and_blocks_a_stale_tab(): void
    {
        $exam = $this->exam([
            '_wizard' => [
                'version' => 1,
                'current_step' => 'information',
                'completed_steps' => [],
                'revision' => 3,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->actingAs($this->teacher)
            ->put(route('exams.update', $exam), [
                'title' => 'Salvo pelo formulário',
                'status' => 'draft',
                'wizard_revision' => 3,
                'wizard_step' => 'information',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, data_get($exam->fresh()->settings, '_wizard.revision'));

        $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'information',
                'payload' => ['title' => 'Aba obsoleta', 'instructions' => null],
                'revision' => 3,
            ])
            ->assertConflict()
            ->assertJsonValidationErrors('revision');
    }

    public function test_publication_checkpoint_and_update_share_the_latest_revision(): void
    {
        $exam = $this->exam();
        $question = Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => ['statement' => 'Questão válida', 'options' => ['A', 'B'], 'correct_option' => 0],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 2, 'order' => 1]);

        $checkpoint = $this->actingAs($this->teacher)
            ->patchJson(route('exams.autosaveDraft', $exam), [
                'step' => 'publication',
                'payload' => [
                    'show_score' => false,
                    'show_answers' => true,
                    'show_feedback' => false,
                    'results_available_from' => null,
                ],
                'revision' => 0,
                'complete' => true,
            ])
            ->assertOk();

        $this->actingAs($this->teacher)
            ->put(route('exams.update', $exam), [
                'title' => $exam->title,
                'status' => 'published',
                'wizard_revision' => $checkpoint->json('wizard.revision'),
                'wizard_step' => 'publication',
            ])
            ->assertSessionHasNoErrors();

        $exam->refresh();
        $this->assertSame('published', $exam->status);
        $this->assertFalse($exam->settings['show_score']);
        $this->assertTrue($exam->settings['show_answers']);
        $this->assertFalse($exam->settings['show_feedback']);
        $this->assertSame(2, data_get($exam->settings, '_wizard.revision'));
    }

    /** @param array<string, mixed> $settings */
    private function exam(array $settings = []): Exam
    {
        return Exam::create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação em rascunho',
            'status' => 'draft',
            'settings' => $settings,
        ]);
    }

    private function teacher(Organization $organization): User
    {
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $teacher->assignRole('teacher');

        return $teacher;
    }
}
