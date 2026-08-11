<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Exam;
use App\Models\InstitutionRole;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Revision;
use App\Models\RevisionAttempt;
use App\Models\RevisionItem;
use App\Models\SchoolClass;
use App\Models\StudentGamificationProfile;
use App\Models\User;
use App\Services\RevisionGraderService;
use App\Services\RevisionImportService;
use App\Services\RevisionWorkflowService;
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
                [
                    'type' => 'multiple_choice',
                    'prompt' => 'Quanto é 2 + 2?',
                    'content' => [
                        'options' => ['3', '4'],
                        'resources' => [['resource_version_id' => 999]],
                    ],
                    'solution' => ['correct_option' => 1],
                ],
                ['type' => 'short_answer', 'prompt' => 'Capital do ES?', 'content' => [], 'solution' => ['accepted_answers' => ['Vitória', 'Vitoria']]],
            ],
        ]);

        app(RevisionImportService::class)->import($revision, $this->teacher, $payload);
        $this->assertSame(2, $revision->items()->count());
        $this->assertArrayNotHasKey('resources', $revision->items()->first()->content);
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

    public function test_attempt_uses_immutable_snapshot_and_completion_is_idempotent(): void
    {
        $revision = $this->revision();
        $item = $revision->items()->create([
            'type' => 'multiple_choice', 'order' => 0, 'prompt' => 'Versao original',
            'content' => ['options' => ['Errada', 'Certa']], 'solution' => ['correct_option' => 1], 'points' => 2,
        ]);

        $this->actingAs($this->student)->post(route('student.revisions.start', $revision))->assertRedirect();
        $attempt = RevisionAttempt::firstOrFail();
        $item->update(['prompt' => 'Versao posterior', 'solution' => ['correct_option' => 0], 'points' => 20]);

        $this->actingAs($this->student)
            ->post(route('student.revisions.answer', [$revision, $attempt, $item->id]), ['answer' => 1])
            ->assertRedirect();
        $item->delete();
        $this->actingAs($this->student)->get(route('student.revisions.execute', [$revision, $attempt]))
            ->assertOk()->assertSee('Versao original')->assertDontSee('Versao posterior');

        $this->actingAs($this->student)->post(route('student.revisions.complete', [$revision, $attempt]))->assertRedirect();
        $firstXp = StudentGamificationProfile::where('organization_id', $this->organization->id)
            ->where('student_id', $this->student->id)->value('xp');
        $this->actingAs($this->student)->post(route('student.revisions.complete', [$revision, $attempt]))->assertRedirect();

        $this->assertSame(100, $firstXp);
        $this->assertSame($firstXp, StudentGamificationProfile::where('organization_id', $this->organization->id)
            ->where('student_id', $this->student->id)->value('xp'));
        $this->assertSame('Versao original', $attempt->fresh()->responses()->firstOrFail()->item_snapshot['prompt']);
        $this->assertSame('2.00', $attempt->fresh()->total_points);
    }

    public function test_legacy_attempt_is_backfilled_once_and_is_workspace_isolated(): void
    {
        $revision = $this->revision();
        $item = $revision->items()->create([
            'type' => 'short_answer', 'order' => 0, 'prompt' => 'Item legado',
            'content' => [], 'solution' => ['accepted_answers' => ['sim']], 'points' => 1,
        ]);
        $attempt = $revision->attempts()->create([
            'student_id' => $this->student->id, 'organization_id' => $this->organization->id,
            'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now(),
        ]);
        $snapshot = app(RevisionGraderService::class)->snapshot($item);
        $attempt->responses()->create([
            'revision_item_id' => $item->id, 'snapshot_item_key' => null, 'answer' => ['value' => 'sim'],
            'is_correct' => true, 'points_awarded' => 1, 'item_snapshot' => $snapshot,
            'feedback' => 'Correta', 'answered_at' => now(),
        ]);
        $item->delete();

        $this->actingAs($this->student)->get(route('student.revisions.execute', [$revision, $attempt]))
            ->assertOk()->assertSee('Item legado')->assertSee('Atualizar resposta');
        $this->assertNotNull($attempt->fresh()->snapshot_hash);
        $this->assertSame($snapshot['id'], $attempt->responses()->firstOrFail()->snapshot_item_key);

        $other = Organization::create(['name' => 'Outro contexto', 'active' => true]);
        $this->student->organizations()->attach($this->organization->id, ['role_in_org' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $this->student->organizations()->attach($other->id, ['role_in_org' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($this->student)->withSession(['workspace_id' => $other->id])
            ->get(route('student.revisions.execute', [$revision, $attempt]))->assertNotFound();
        $this->actingAs($this->student)->withSession(['workspace_id' => $other->id])
            ->get(route('student.revisions.result', [$revision, $attempt]))->assertNotFound();
    }

    public function test_revision_with_attempts_rejects_mutation_import_and_delete(): void
    {
        $revision = $this->revision();
        $revision->items()->create([
            'type' => 'short_answer', 'order' => 0, 'prompt' => 'Protegido',
            'content' => [], 'solution' => ['accepted_answers' => ['ok']], 'points' => 1,
        ]);
        $revision->attempts()->create([
            'student_id' => $this->student->id, 'organization_id' => $this->organization->id,
            'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now(),
        ]);

        foreach ([
            fn () => app(RevisionWorkflowService::class)->mutate($revision, fn (Revision $locked) => $locked->update(['title' => 'Mutado'])),
            fn () => app(RevisionImportService::class)->import($revision, $this->teacher, json_encode([
                'schema_version' => 1,
                'items' => [['type' => 'short_answer', 'prompt' => 'Novo', 'solution' => ['accepted_answers' => ['x']]]],
            ], JSON_THROW_ON_ERROR), 'replace'),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A revisao com tentativa deveria ser imutavel.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('revision', $exception->errors());
            }
        }

        $this->actingAs($this->teacher)->delete(route('revisions.destroy', $revision))
            ->assertSessionHasErrors('revision');
        $this->assertNotSoftDeleted($revision);
        $this->assertSame('Revisao de Matematica', str($revision->fresh()->title)->ascii()->toString());
    }

    public function test_publication_records_hash_and_later_mutation_returns_to_draft(): void
    {
        $revision = $this->revision('draft');
        $revision->items()->create([
            'type' => 'short_answer', 'order' => 0, 'prompt' => 'Publicavel',
            'content' => [], 'solution' => ['accepted_answers' => ['ok']], 'points' => 1,
        ]);

        $this->actingAs($this->teacher)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertRedirect();
        $this->assertNotNull($revision->fresh()->approved_content_hash);
        $this->assertNotNull($revision->fresh()->approved_at);

        app(RevisionWorkflowService::class)->mutate($revision->fresh(), fn (Revision $locked) => $locked->update(['title' => 'Nova versao']));
        $this->assertDatabaseHas('revisions', [
            'id' => $revision->id, 'status' => 'draft', 'approved_content_hash' => null, 'approved_at' => null,
        ]);
    }

    public function test_reviewer_decisions_cannot_be_bypassed_by_the_author(): void
    {
        $revision = $this->revision('changes_requested');
        $revision->items()->create([
            'type' => 'short_answer', 'order' => 0, 'prompt' => 'Fluxo revisado',
            'content' => [], 'solution' => ['accepted_answers' => ['ok']], 'points' => 1,
        ]);

        $this->actingAs($this->teacher)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->teacher)->post(route('revisions.status', $revision), ['status' => 'in_review'])
            ->assertRedirect();
        $this->actingAs($this->teacher)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertForbidden();

        $reviewer = $this->member('coordinator');
        $this->actingAs($reviewer)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertRedirect();

        $ownRevision = Revision::create([
            'organization_id' => $this->organization->id, 'author_id' => $reviewer->id,
            'title' => 'Revisao do proprio revisor', 'status' => 'in_review',
        ]);
        $ownRevision->items()->create([
            'type' => 'short_answer', 'order' => 0, 'prompt' => 'Autorrevisao proibida',
            'content' => [], 'solution' => ['accepted_answers' => ['ok']], 'points' => 1,
        ]);
        $this->actingAs($reviewer)->post(route('revisions.status', $ownRevision), ['status' => 'published'])
            ->assertForbidden();
        $ownRevision->update(['status' => 'published']);
        $this->actingAs($reviewer)->post(route('revisions.status', $ownRevision), ['status' => 'suspended'])
            ->assertForbidden();
        $this->assertSame($reviewer->id, $revision->fresh()->reviewed_by);
        $this->actingAs($reviewer)->post(route('revisions.status', $revision), ['status' => 'suspended'])
            ->assertRedirect();
        $this->actingAs($this->teacher)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertForbidden();
        $this->actingAs($reviewer)->post(route('revisions.status', $revision), ['status' => 'published'])
            ->assertRedirect();
    }

    public function test_due_date_and_manual_item_semantics_are_enforced(): void
    {
        $revision = $this->revision();
        $revision->update(['due_at' => now()->subMinute()]);
        $this->actingAs($this->student)->get(route('student.revisions.show', $revision))->assertNotFound();

        $draft = $this->revision('draft');
        $this->actingAs($this->teacher)->post(route('revisions.items.store', $draft), [
            'type' => 'multiple_choice', 'prompt' => 'Invalida', 'content_json' => json_encode(['options' => ['so uma']]),
            'solution_json' => json_encode([]), 'difficulty' => 1, 'points' => 1, 'is_active' => 1,
        ])->assertSessionHasErrors('solution_json');
        $this->assertSame(0, $draft->items()->count());
    }

    public function test_pedagogical_routes_honor_built_in_and_custom_permissions(): void
    {
        foreach (['director', 'coordinator', 'pedagogue'] as $role) {
            $member = $this->member($role);
            $this->actingAs($member)->get(route('lessons.index'))->assertOk();
        }

        $customRole = InstitutionRole::create(['organization_id' => $this->organization->id, 'name' => 'Curadoria']);
        $customRole->syncPermissions(['view_pedagogical_content']);
        $custom = $this->member('teacher', $customRole->id);
        $lesson = Lesson::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Aula visivel', 'objectives' => 'Objetivo auditavel', 'content' => 'Conteudo auditavel',
            'status' => 'published', 'generate_review' => false,
        ]);
        $activity = Activity::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Atividade visivel', 'instructions' => 'Instrucao auditavel', 'max_attempts' => 1,
            'points' => 1, 'modality' => 'online', 'status' => 'published', 'generate_review' => false,
        ]);
        $this->actingAs($custom)->get(route('activities.index'))->assertOk()
            ->assertSee('Visualizar')->assertDontSee('Nova atividade')->assertDontSee('>Editar<', false);
        $this->actingAs($custom)->get(route('lessons.index'))->assertOk()
            ->assertSee('Visualizar')->assertDontSee('Nova aula')->assertDontSee('>Editar<', false);
        $this->actingAs($custom)->get(route('lessons.show', $lesson))->assertOk()->assertSee('Conteudo auditavel');
        $this->actingAs($custom)->get(route('activities.show', $activity))->assertOk()->assertSee('Instrucao auditavel');
        $this->actingAs($custom)->get(route('lessons.create'))->assertForbidden();
        $this->actingAs($custom)->post(route('lessons.store'), $this->lessonPayload('Sem permissao'))
            ->assertForbidden();

        $foreignOrganization = Organization::create(['name' => 'Tenant nao selecionado', 'active' => true]);
        $foreignTeacher = User::factory()->create(['organization_id' => $foreignOrganization->id, 'type' => 'teacher']);
        $foreignLesson = Lesson::create([
            'organization_id' => $foreignOrganization->id, 'author_id' => $foreignTeacher->id,
            'title' => 'Aula externa', 'status' => 'draft', 'generate_review' => false,
        ]);
        $global = User::factory()->create(['organization_id' => null, 'type' => 'global_admin']);
        $this->actingAs($global)->withSession(['workspace_id' => $this->organization->id])
            ->get(route('lessons.edit', $foreignLesson))->assertForbidden();
    }

    public function test_activity_writes_are_atomic_and_generated_revision_preserves_source_author(): void
    {
        $foreignOrganization = Organization::create(['name' => 'Externa', 'active' => true]);
        $foreignOwner = User::factory()->create(['organization_id' => $foreignOrganization->id, 'type' => 'teacher']);
        $foreignQuestion = Question::create([
            'organization_id' => $foreignOrganization->id, 'owner_id' => $foreignOwner->id,
            'type' => 'essay', 'content' => ['statement' => 'Fora do tenant'], 'visibility_scope' => 'private',
        ]);
        $payload = [
            'title' => 'Atividade parcial', 'instructions' => null, 'max_attempts' => 1, 'points' => 1,
            'modality' => 'online', 'status' => 'draft', 'class_ids' => [$this->class->id],
            'question_ids' => [$foreignQuestion->id],
        ];
        $this->actingAs($this->teacher)->post(route('activities.store'), $payload)->assertForbidden();
        $this->assertDatabaseMissing('activities', ['title' => 'Atividade parcial']);

        $lesson = Lesson::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Aula da professora', 'content' => 'Conteudo', 'status' => 'draft', 'generate_review' => false,
        ]);
        $lesson->schoolClasses()->attach($this->class->id);
        $admin = $this->member('institution_admin');
        $this->actingAs($admin)->put(route('lessons.update', $lesson), $this->lessonPayload($lesson->title, true))
            ->assertRedirect();
        $generated = Revision::whereHas('sources', fn ($query) => $query
            ->where('source_type', $lesson->getMorphClass())->where('source_id', $lesson->id))->firstOrFail();
        $this->assertSame($this->teacher->id, $generated->author_id);
        $this->assertSame($admin->id, $generated->items()->firstOrFail()->updated_by);
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

    public function test_expired_required_revision_does_not_block_exam_forever(): void
    {
        $exam = Exam::create([
            'organization_id' => $this->organization->id, 'author_id' => $this->teacher->id,
            'title' => 'Avaliacao apos prazo da revisao', 'status' => 'published', 'access_code' => 'EXP123',
            'settings' => ['application_mode' => 'online', 'attempts' => 1],
        ]);
        $exam->schoolClasses()->attach($this->class->id);
        $revision = $this->revision();
        $revision->update([
            'is_required' => true, 'timing' => 'before', 'block_exam' => true, 'due_at' => now()->subMinute(),
        ]);
        $revision->sources()->create(['source_type' => $exam->getMorphClass(), 'source_id' => $exam->id]);

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

    private function member(string $role, ?int $institutionRoleId = null): User
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => in_array($role, ['institution_admin', 'student'], true) ? $role : 'teacher',
            'status' => 'active',
        ]);
        $user->assignRole(in_array($role, ['institution_admin', 'student'], true) ? $role : 'teacher');
        $user->organizations()->attach($this->organization->id, [
            'role_in_org' => $role,
            'status' => 'active',
            'joined_at' => now(),
            'institution_role_id' => $institutionRoleId,
        ]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function lessonPayload(string $title, bool $generateReview = false): array
    {
        return [
            'title' => $title,
            'objectives' => null,
            'content' => 'Conteudo pedagogico',
            'starts_at' => null,
            'status' => 'draft',
            'generate_review' => $generateReview ? 1 : 0,
            'class_ids' => [$this->class->id],
        ];
    }
}
