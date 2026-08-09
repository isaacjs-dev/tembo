<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Organization;
use App\Models\Revision;
use App\Models\RevisionAttempt;
use App\Models\RevisionItem;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\RevisionGraderService;
use App\Services\RevisionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private User $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['teacher', 'student', 'institution_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->organization = Organization::create(['name' => 'Escola Revisões', 'active' => true]);
        $this->teacher = User::factory()->create(['organization_id' => $this->organization->id, 'type' => 'teacher']);
        $this->teacher->assignRole('teacher');
        $this->student = User::factory()->create(['organization_id' => $this->organization->id, 'type' => 'student']);
        $this->student->assignRole('student');
        $this->class = SchoolClass::create([
            'organization_id' => $this->organization->id,
            'owner_type' => 'user',
            'owner_id' => $this->teacher->id,
            'name' => '1º Ano A',
            'year' => 2026,
        ]);
        $this->class->teachers()->attach($this->teacher->id, ['assigned_at' => now()]);
        $this->class->students()->attach($this->student->id);
    }

    public function test_json_import_is_validated_and_atomic(): void
    {
        $revision = $this->revision('draft');
        $payload = json_encode([
            'schema_version' => 1,
            'items' => [
                ['type' => 'multiple_choice', 'prompt' => 'Quanto é 2 + 2?', 'content' => ['options' => ['3', '4']], 'solution' => ['correct_option' => 1]],
                ['type' => 'short_answer', 'prompt' => 'Capital do ES?', 'content' => [], 'solution' => ['accepted_answers' => ['Vitória', 'Vitoria']]],
            ],
        ]);

        app(RevisionImportService::class)->import($revision, $this->teacher, $payload);
        $this->assertSame(2, $revision->items()->count());
        $this->assertDatabaseHas('revision_imports', ['revision_id' => $revision->id, 'status' => 'imported', 'items_imported' => 2]);

        try {
            app(RevisionImportService::class)->import($revision, $this->teacher, json_encode([
                'schema_version' => 1,
                'items' => [['type' => 'multiple_choice', 'prompt' => 'Inválida', 'content' => ['options' => ['uma']], 'solution' => []]],
            ]));
            $this->fail('A importação inválida deveria falhar.');
        } catch (ValidationException) {
            $this->assertSame(2, $revision->items()->count());
            $this->assertDatabaseHas('revision_imports', ['revision_id' => $revision->id, 'status' => 'rejected']);
        }
    }

    public function test_short_answers_ignore_accents_case_and_punctuation(): void
    {
        $item = new RevisionItem(['type' => 'short_answer', 'points' => 2, 'solution' => ['accepted_answers' => ['Vitória']]]);
        $grade = app(RevisionGraderService::class)->grade($item, '  VITORIA!!! ');

        $this->assertTrue($grade['is_correct']);
        $this->assertSame(2.0, $grade['points_awarded']);
    }

    public function test_student_completes_revision_with_snapshot_and_private_gamification(): void
    {
        $revision = $this->revision();
        $item = $revision->items()->create([
            'type' => 'multiple_choice', 'order' => 0, 'prompt' => 'Quanto é 2 + 2?',
            'content' => ['options' => ['3', '4']], 'solution' => ['correct_option' => 1], 'points' => 2,
        ]);

        $this->actingAs($this->student)->post(route('student.revisions.start', $revision))->assertRedirect();
        $attempt = RevisionAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.revisions.answer', [$revision, $attempt, $item]), ['answer' => 1])->assertRedirect();

        $item->update(['prompt' => 'Enunciado alterado depois da resposta']);
        $this->actingAs($this->student)->post(route('student.revisions.complete', [$revision, $attempt]))
            ->assertRedirect(route('student.revisions.result', [$revision, $attempt]));

        $response = $attempt->responses()->firstOrFail();
        $this->assertSame('Quanto é 2 + 2?', $response->item_snapshot['prompt']);
        $this->assertDatabaseHas('revision_attempts', ['id' => $attempt->id, 'status' => 'completed', 'score' => 10]);
        $this->assertDatabaseHas('student_gamification_profiles', ['student_id' => $this->student->id, 'xp' => 100, 'level' => 2]);
    }

    public function test_configured_pre_exam_revision_blocks_until_completed(): void
    {
        $exam = Exam::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Prova de Matemática', 'status' => 'published', 'access_code' => 'REV123',
            'settings' => ['application_mode' => 'online', 'attempts' => 1],
        ]);
        $exam->schoolClasses()->attach($this->class->id);
        $revision = $this->revision();
        $revision->update(['is_required' => true, 'timing' => 'before', 'block_exam' => true]);
        $revision->sources()->create(['source_type' => $exam->getMorphClass(), 'source_id' => $exam->id]);

        $this->actingAs($this->student)->post(route('student.exam.start', $exam))
            ->assertSessionHasErrors('revision');

        $revision->attempts()->create([
            'student_id' => $this->student->id, 'organization_id' => $this->organization->id,
            'attempt_number' => 1, 'status' => 'completed', 'started_at' => now(), 'last_activity_at' => now(), 'completed_at' => now(),
        ]);
        $this->actingAs($this->student)->post(route('student.exam.start', $exam))
            ->assertRedirect(route('student.exam.execution', ['exam' => $exam->id, 'attempt' => 1]));
    }

    public function test_revision_management_and_student_access_are_tenant_isolated(): void
    {
        $otherOrganization = Organization::create(['name' => 'Outra Escola', 'active' => true]);
        $otherTeacher = User::factory()->create(['organization_id' => $otherOrganization->id, 'type' => 'teacher']);
        $otherRevision = Revision::create([
            'organization_id' => $otherOrganization->id, 'author_id' => $otherTeacher->id,
            'title' => 'Revisão privada de outro tenant', 'status' => 'published',
        ]);

        $this->actingAs($this->teacher)->get(route('revisions.edit', $otherRevision))->assertForbidden();
        $this->actingAs($this->student)->get(route('student.revisions.show', $otherRevision))->assertNotFound();
    }

    private function revision(string $status = 'published'): Revision
    {
        $revision = Revision::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Revisão de Matemática', 'status' => $status, 'max_attempts' => 2,
            'feedback_mode' => 'immediate', 'gamification_enabled' => true,
        ]);
        $revision->schoolClasses()->attach($this->class->id);

        return $revision;
    }
}
