<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Organization;
use App\Models\PublicCatalogEntry;
use App\Models\PublicCatalogReport;
use App\Models\PublicCatalogSubmission;
use App\Models\Question;
use App\Models\QuestionResource;
use App\Models\User;
use App\Services\PublicCatalogService;
use App\Services\QuestionLibraryService;
use App\Services\QuestionResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicCatalogModerationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $otherOrganization;

    private User $author;

    private User $colleague;

    private User $outsider;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Escola autora', 'active' => true]);
        $this->otherOrganization = Organization::create(['name' => 'Escola leitora', 'active' => true]);
        $this->author = $this->teacher($this->organization, 'autor@example.test');
        $this->colleague = $this->teacher($this->organization, 'colega@example.test');
        $this->outsider = $this->teacher($this->otherOrganization, 'leitor@example.test');
        $this->moderator = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'global_admin',
            'status' => 'active',
            'email' => 'moderador@example.test',
        ]);
        $this->moderator->assignRole(Role::findOrCreate('global_admin', 'web'));
    }

    public function test_submission_approves_an_immutable_snapshot_clone_and_records_facts_without_credits(): void
    {
        $question = $this->question($this->author, 'Enunciado enviado');
        $key = (string) Str::uuid();

        $this->actingAs($this->author)
            ->post(route('public-catalog.submissions.store'), $this->submissionPayload('question', $question->id, $key))
            ->assertRedirect(route('public-catalog.index'));

        $submission = PublicCatalogSubmission::query()->sole();
        $this->assertSame('pending', $submission->status);
        $this->assertSame('Enunciado enviado', data_get($submission->snapshot_json, 'content.statement'));
        $this->assertDatabaseHas('public_catalog_submission_events', [
            'submission_id' => $submission->id,
            'action' => 'submitted',
            'to_status' => 'pending',
        ]);

        $question->update(['content' => [...$question->content, 'statement' => 'Enunciado editado depois']]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.decide', $submission), [
                'decision' => 'approved',
                'reason' => 'Conteúdo autoral, íntegro e adequado ao catálogo.',
            ])
            ->assertRedirect(route('admin.public-catalog.index'));

        $entry = PublicCatalogEntry::query()->sole();
        /** @var Question $published */
        $published = $entry->entryable;
        $this->assertNotSame($question->id, $published->id);
        $this->assertSame($question->id, $published->source_question_id);
        $this->assertSame('platform_public', $published->visibility_scope);
        $this->assertSame('Enunciado enviado', data_get($published->content, 'statement'));
        $this->assertSame('Enunciado editado depois', data_get($question->fresh()->content, 'statement'));
        $this->assertSame('private', $question->fresh()->visibility_scope);
        $this->assertDatabaseHas('public_catalog_reputation_events', [
            'user_id' => $this->author->id,
            'event_key' => 'submission_approved',
        ]);
        $this->assertDatabaseCount('usage_events', 0);
        $this->assertDatabaseHas('public_catalog_submission_events', [
            'submission_id' => $submission->id,
            'action' => 'approved',
            'to_status' => 'approved',
        ]);
    }

    public function test_submission_is_owner_scoped_idempotent_and_moderation_is_global_admin_only(): void
    {
        $question = $this->question($this->author, 'Somente do autor');
        $key = (string) Str::uuid();

        $this->actingAs($this->colleague)
            ->get(route('public-catalog.submissions.create', ['type' => 'question', 'id' => $question->id]))
            ->assertNotFound();
        $this->get(route('admin.public-catalog.index'))->assertForbidden();

        $payload = $this->submissionPayload('question', $question->id, $key);
        $this->actingAs($this->author)->post(route('public-catalog.submissions.store'), $payload)->assertRedirect();
        $this->post(route('public-catalog.submissions.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('public_catalog_submissions', 1);
        $this->assertDatabaseCount('public_catalog_submission_events', 1);

        $submission = PublicCatalogSubmission::query()->sole();
        $this->actingAs($this->colleague)
            ->post(route('public-catalog.submissions.withdraw', $submission), ['reason' => 'Tentativa indevida de retirada.'])
            ->assertNotFound();
        $this->actingAs($this->author)
            ->post(route('public-catalog.submissions.withdraw', $submission), ['reason' => 'Quero revisar o conteúdo antes de reenviar.'])
            ->assertRedirect();
        $this->assertSame('withdrawn', $submission->fresh()->status);
    }

    public function test_rejection_requires_reason_and_resubmission_keeps_history(): void
    {
        $question = $this->question($this->author, 'Questão para revisar');
        $first = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.decide', $first), [
                'decision' => 'rejected',
                'reason' => 'O enunciado precisa de contexto pedagógico adicional.',
            ])
            ->assertRedirect();
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertDatabaseMissing('public_catalog_entries', ['submission_id' => $first->id]);

        $question->update(['content' => [...$question->content, 'statement' => 'Questão revisada com contexto']]);
        $second = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame($first->id, $second->previous_submission_id);
        $this->assertNotSame($first->content_hash, $second->content_hash);
    }

    public function test_exact_duplicate_is_blocked_and_near_duplicate_is_only_flagged(): void
    {
        $firstQuestion = $this->question($this->author, 'Fotossíntese transforma energia luminosa');
        $first = app(PublicCatalogService::class)->submit($firstQuestion, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $exact = $this->question($this->colleague, 'Fotossíntese transforma energia luminosa');
        try {
            app(PublicCatalogService::class)->submit($exact, $this->colleague, [
                'rights_basis' => 'own_work',
                'idempotency_key' => (string) Str::uuid(),
            ]);
            $this->fail('A duplicata exata deveria ser bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target', $exception->errors());
        }

        $similar = $this->question($this->colleague, '  FOTOSSÍNTESE   transforma energia luminosa  ');
        $similar->update(['level' => 'hard']);
        $flagged = app(PublicCatalogService::class)->submit($similar, $this->colleague, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->assertContains($first->id, $flagged->duplicate_candidates_json);
        $this->assertSame('pending', $flagged->status);
    }

    public function test_question_dependencies_are_pinned_and_revalidated_at_approval(): void
    {
        $resourceSource = $this->resource($this->author, 'Texto público', 'Conteúdo estável');
        $resourceSubmission = app(PublicCatalogService::class)->submit($resourceSource, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($resourceSubmission, $this->moderator, 'approved', 'Recurso adequado e com autoria confirmada.');
        /** @var QuestionResource $publicResource */
        $publicResource = $resourceSubmission->fresh('entry.entryable')->entry->entryable;

        $question = $this->question($this->author, 'Questão com recurso obrigatório');
        app(QuestionResourceService::class)->syncQuestion($question, [$publicResource->id], $this->author);
        $submission = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $publicResource->publicCatalogEntries()->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Suspensão de teste por direito autoral.',
        ]);

        $this->expectException(ValidationException::class);
        app(PublicCatalogService::class)->decide($submission, $this->moderator, 'approved', 'Tentativa após suspensão do apoio.');
    }

    public function test_report_is_public_only_and_upheld_report_suspends_new_use_but_preserves_history(): void
    {
        $question = $this->question($this->author, 'Conteúdo que será denunciado');
        $submission = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($submission, $this->moderator, 'approved', 'Conteúdo inicialmente aprovado após verificação.');
        $entry = $submission->fresh('entry.entryable')->entry;
        /** @var Question $published */
        $published = $entry->entryable;

        $this->actingAs($this->outsider)
            ->post(route('public-catalog.reports.store'), [
                'type' => 'question',
                'target_id' => $published->id,
                'reason_code' => 'copyright',
                'details' => 'Há indícios verificáveis de uso sem autorização do autor original.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect();
        $report = PublicCatalogReport::query()->sole();

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.reports.resolve', $report), [
                'decision' => 'upheld',
                'resolution' => 'A documentação comprovou ausência de autorização para publicação.',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $entry->fresh()->status);
        $this->assertSame('removed', $submission->fresh()->status);
        $this->assertSame('upheld', $report->fresh()->status);
        $this->assertNotNull($published->fresh());
        $this->actingAs($this->outsider)
            ->get(route('questions.index', ['tab' => 'platform']))
            ->assertOk()
            ->assertDontSee('Conteúdo que será denunciado');
        $this->get(route('public-catalog.reports.create', ['type' => 'question', 'id' => $published->id]))
            ->assertNotFound();
    }

    public function test_rights_validation_and_queue_limits_are_enforced_server_side(): void
    {
        $question = $this->question($this->author, 'Conteúdo licenciado');

        $this->actingAs($this->author)
            ->post(route('public-catalog.submissions.store'), [
                ...$this->submissionPayload('question', $question->id, (string) Str::uuid()),
                'rights_basis' => 'licensed',
            ])
            ->assertSessionHasErrors('rights_notes');

        config(['public_catalog.max_pending_submissions' => 1]);
        app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'licensed',
            'rights_notes' => 'Licença educacional concedida pelo titular em 01/08/2026.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $second = $this->question($this->author, 'Segundo conteúdo pendente');
        $this->expectException(ValidationException::class);
        app(PublicCatalogService::class)->submit($second, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function test_moderator_cannot_review_own_submission_or_repeat_a_terminal_decision(): void
    {
        $question = $this->question($this->moderator, 'Conteúdo do próprio moderador');
        $submission = app(PublicCatalogService::class)->submit($question, $this->moderator, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.decide', $submission), [
                'decision' => 'approved',
                'reason' => 'Tentativa de autorrevisão que deve ser bloqueada.',
            ])
            ->assertForbidden();

        $otherModerator = User::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'type' => 'global_admin',
            'status' => 'active',
        ]);
        $otherModerator->assignRole(Role::findOrCreate('global_admin', 'web'));
        app(PublicCatalogService::class)->decide($submission, $otherModerator, 'rejected', 'Conteúdo recusado após revisão independente.');

        $this->actingAs($otherModerator)
            ->post(route('admin.public-catalog.decide', $submission), [
                'decision' => 'approved',
                'reason' => 'Retry inválido após decisão terminal anterior.',
            ])
            ->assertStatus(409);
        $this->assertDatabaseCount('public_catalog_submission_events', 2);
    }

    public function test_reports_cannot_target_private_or_own_content_and_retries_do_not_duplicate(): void
    {
        $private = $this->question($this->author, 'Questão privada invisível');
        $this->actingAs($this->outsider)
            ->get(route('public-catalog.reports.create', ['type' => 'question', 'id' => $private->id]))
            ->assertNotFound();

        $submission = app(PublicCatalogService::class)->submit($private, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($submission, $this->moderator, 'approved', 'Conteúdo aprovado para testar denúncias.');
        /** @var Question $published */
        $published = $submission->fresh('entry.entryable')->entry->entryable;

        $this->actingAs($this->author)
            ->get(route('public-catalog.reports.create', ['type' => 'question', 'id' => $published->id]))
            ->assertStatus(422);

        $payload = [
            'type' => 'question',
            'target_id' => $published->id,
            'reason_code' => 'incorrect',
            'details' => 'A resposta indicada apresenta inconsistência verificável no conteúdo.',
            'idempotency_key' => (string) Str::uuid(),
        ];
        $this->actingAs($this->outsider)->post(route('public-catalog.reports.store'), $payload)->assertRedirect();
        $this->post(route('public-catalog.reports.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('public_catalog_reports', 1);

        $this->post(route('public-catalog.reports.store'), [
            ...$payload,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('details');
    }

    public function test_private_file_evidence_is_admin_only_and_hash_checked(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('catalog/evidence.txt', 'evidência íntegra');
        $resource = QuestionResource::query()->create([
            'organization_id' => $this->author->organization_id,
            'owner_id' => $this->author->id,
            'title' => 'Documento comprobatório',
            'type' => 'document',
            'visibility_scope' => 'private',
            'status' => 'active',
        ]);
        $resource->versions()->create([
            'version_number' => 1,
            'content' => ['title' => 'Documento comprobatório', 'body' => null],
            'content_hash' => hash('sha256', 'evidência íntegra'),
            'storage_disk' => 'local',
            'storage_path' => 'catalog/evidence.txt',
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', 'evidência íntegra'),
            'created_by' => $this->author->id,
        ]);
        $submission = app(PublicCatalogService::class)->submit($resource->fresh(), $this->author, [
            'rights_basis' => 'authorized',
            'rights_notes' => 'Autorização documentada no arquivo anexado.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->author)
            ->get(route('admin.public-catalog.evidence', $submission))
            ->assertForbidden();
        $this->actingAs($this->moderator)
            ->get(route('admin.public-catalog.evidence', $submission))
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        Storage::disk('local')->put('catalog/evidence.txt', 'arquivo adulterado');
        $this->get(route('admin.public-catalog.evidence', $submission))->assertStatus(409);
    }

    public function test_legacy_public_backfill_is_idempotent_and_creates_a_moderation_history(): void
    {
        $question = $this->question($this->author, 'Questão pública histórica');
        $question->update(['visibility_scope' => 'platform_public']);
        $resource = $this->resource($this->author, 'Recurso público histórico', 'Conteúdo histórico');
        $resource->update(['visibility_scope' => 'platform_public']);

        $migration = require database_path('migrations/2026_08_09_000910_backfill_legacy_public_catalog_entries.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('public_catalog_submissions', 2);
        $this->assertDatabaseCount('public_catalog_entries', 2);
        $this->assertDatabaseCount('public_catalog_submission_events', 2);
        $this->assertSame(2, PublicCatalogSubmission::query()->where('rights_basis', 'legacy_import')->count());
        $this->assertSame(2, PublicCatalogEntry::query()->where('status', 'published')->count());
    }

    public function test_report_resolution_requires_an_independent_moderator(): void
    {
        $question = $this->question($this->author, 'Content reported by its moderator');
        $submission = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($submission, $this->moderator, 'approved', 'Approved for independent moderation test.');
        /** @var Question $published */
        $published = $submission->fresh('entry.entryable')->entry->entryable;
        $ownReport = app(PublicCatalogService::class)->report($published, $this->moderator, [
            'reason_code' => 'incorrect',
            'details' => 'The moderator filed this report and must not be able to decide it.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.reports.resolve', $ownReport), [
                'decision' => 'upheld',
                'resolution' => 'A reporter cannot uphold their own report.',
            ])->assertForbidden();
        $this->assertSame('open', $ownReport->fresh()->status);

        $otherModerator = User::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'type' => 'global_admin',
            'status' => 'active',
        ]);
        $otherModerator->assignRole(Role::findOrCreate('global_admin', 'web'));
        $moderatorQuestion = $this->question($this->moderator, 'Content published by a moderator');
        $moderatorSubmission = app(PublicCatalogService::class)->submit($moderatorQuestion, $this->moderator, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($moderatorSubmission, $otherModerator, 'approved', 'Independently reviewed moderator content.');
        /** @var Question $moderatorPublished */
        $moderatorPublished = $moderatorSubmission->fresh('entry.entryable')->entry->entryable;
        $publisherReport = app(PublicCatalogService::class)->report($moderatorPublished, $this->outsider, [
            'reason_code' => 'copyright',
            'details' => 'Independent report against content whose publisher is a moderator.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.reports.resolve', $publisherReport), [
                'decision' => 'dismissed',
                'resolution' => 'The publisher cannot dismiss a report against their own content.',
            ])->assertForbidden();
        $this->assertSame('open', $publisherReport->fresh()->status);
    }

    public function test_suspended_public_items_cannot_create_new_uses_but_historical_links_survive(): void
    {
        $questionSource = $this->question($this->author, 'Public question later suspended');
        $questionSubmission = app(PublicCatalogService::class)->submit($questionSource, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($questionSubmission, $this->moderator, 'approved', 'Approved before controlled suspension.');
        /** @var Question $publicQuestion */
        $publicQuestion = $questionSubmission->fresh('entry.entryable')->entry->entryable;
        $questionSubmission->entry()->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Controlled test suspension.',
        ]);

        $exam = Exam::query()->create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->author->id,
            'title' => 'Exam rejecting suspended content',
            'status' => 'draft',
            'settings' => [],
        ]);
        $this->actingAs($this->author)
            ->post(route('exams.addQuestion', $exam), ['question_id' => $publicQuestion->id, 'points' => 1])
            ->assertNotFound();
        $this->assertFalse(app(QuestionLibraryService::class)->forScope($this->author, 'mine')
            ->whereKey($publicQuestion)->exists());
        $this->assertDatabaseMissing('exam_questions', ['exam_id' => $exam->id, 'question_id' => $publicQuestion->id]);

        $resourceSource = $this->resource($this->author, 'Public resource later suspended', 'Stable support content');
        $resourceSubmission = app(PublicCatalogService::class)->submit($resourceSource, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(PublicCatalogService::class)->decide($resourceSubmission, $this->moderator, 'approved', 'Resource approved before controlled suspension.');
        /** @var QuestionResource $publicResource */
        $publicResource = $resourceSubmission->fresh('entry.entryable')->entry->entryable;
        $historicalQuestion = $this->question($this->author, 'Question with historical resource link');
        app(QuestionResourceService::class)->syncQuestion($historicalQuestion, [$publicResource->id], $this->author);
        $resourceSubmission->entry()->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Controlled test suspension.',
        ]);

        $newQuestion = $this->question($this->author, 'Question attempting a new suspended link');
        try {
            app(QuestionResourceService::class)->syncQuestion($newQuestion, [$publicResource->id], $this->author);
            $this->fail('A suspended resource must not create a new link.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_ids', $exception->errors());
        }
        app(QuestionResourceService::class)->syncQuestion($historicalQuestion, [$publicResource->id], $this->author);
        $this->assertTrue($historicalQuestion->resources()->whereKey($publicResource)->exists());
        $this->assertFalse(app(QuestionResourceService::class)->forLibraryScope($this->author, 'mine')
            ->whereKey($publicResource)->exists());
    }

    public function test_resource_fingerprint_ignores_storage_path_and_version_number(): void
    {
        $sha = hash('sha256', 'identical binary file');
        $first = $this->fileResource($this->author, 'Identical map', 'uploads/first-uuid.png', $sha, 7);
        $second = $this->fileResource($this->colleague, 'Identical map', 'uploads/second-uuid.png', $sha, 19);
        app(PublicCatalogService::class)->submit($first, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        try {
            app(PublicCatalogService::class)->submit($second, $this->colleague, [
                'rights_basis' => 'own_work',
                'idempotency_key' => (string) Str::uuid(),
            ]);
            $this->fail('Identical files stored at different paths must be deduplicated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target', $exception->errors());
        }
    }

    public function test_submission_history_and_withdrawal_are_scoped_to_the_selected_workspace(): void
    {
        $this->author->organizations()->syncWithoutDetaching([
            $this->organization->id => ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()],
            $this->otherOrganization->id => ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()],
        ]);
        $question = $this->question($this->author, 'Origin workspace snapshot');
        $submission = app(PublicCatalogService::class)->submit($question, $this->author, [
            'rights_basis' => 'own_work',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->author)
            ->withSession(['workspace_id' => $this->otherOrganization->id])
            ->get(route('public-catalog.index'))
            ->assertOk()
            ->assertDontSee('Origin workspace snapshot');
        $this->withSession(['workspace_id' => $this->otherOrganization->id])
            ->post(route('public-catalog.submissions.withdraw', $submission), [
                'reason' => 'Withdrawal attempt from a different institutional workspace.',
            ])->assertNotFound();
        $this->assertSame('pending', $submission->fresh()->status);
    }

    private function submissionPayload(string $type, int $id, string $key): array
    {
        return [
            'type' => $type,
            'target_id' => $id,
            'rights_basis' => 'own_work',
            'rights_confirmed' => '1',
            'terms_accepted' => '1',
            'idempotency_key' => $key,
        ];
    }

    private function teacher(Organization $organization, string $email): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
            'email' => $email,
        ]);
        $user->assignRole(Role::findOrCreate('teacher', 'web'));

        return $user;
    }

    private function question(User $owner, string $statement): Question
    {
        return Question::query()->create([
            'organization_id' => $owner->organization_id,
            'owner_id' => $owner->id,
            'type' => 'multiple_choice',
            'visibility_scope' => 'private',
            'content' => [
                'statement' => $statement,
                'options' => ['Alternativa A', 'Alternativa B'],
                'correct_option' => 0,
            ],
            'level' => 'medium',
            'stage' => 'ef_finais',
            'grade' => '6',
        ]);
    }

    private function resource(User $owner, string $title, string $body): QuestionResource
    {
        $resource = QuestionResource::query()->create([
            'organization_id' => $owner->organization_id,
            'owner_id' => $owner->id,
            'title' => $title,
            'type' => 'text',
            'visibility_scope' => 'private',
            'status' => 'active',
        ]);
        $resource->versions()->create([
            'version_number' => 1,
            'content' => ['title' => $title, 'body' => $body],
            'content_hash' => hash('sha256', $body),
            'created_by' => $owner->id,
        ]);

        return $resource;
    }

    private function fileResource(User $owner, string $title, string $path, string $sha, int $versionNumber): QuestionResource
    {
        $resource = QuestionResource::query()->create([
            'organization_id' => $owner->organization_id,
            'owner_id' => $owner->id,
            'title' => $title,
            'type' => 'image',
            'visibility_scope' => 'private',
            'status' => 'active',
        ]);
        $content = ['title' => $title, 'body' => null, 'alt_text' => 'Educational map'];
        $resource->versions()->create([
            'version_number' => $versionNumber,
            'content' => $content,
            'content_hash' => hash('sha256', json_encode(['content' => $content, 'file_sha256' => $sha], JSON_THROW_ON_ERROR)),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 24,
            'sha256' => $sha,
            'created_by' => $owner->id,
        ]);

        return $resource;
    }
}
