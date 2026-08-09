<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionResource;
use App\Models\QuestionResourceVersion;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ExamPrintService;
use App\Services\QuestionResourceService;
use App\Services\RevisionBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionResourceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private User $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Escola Recursos', 'active' => true]);
        $this->teacher = $this->teacher('autor@example.test');
        $this->otherTeacher = $this->teacher('colega@example.test');
        $this->actingAs($this->teacher);
    }

    public function test_resource_crud_creates_immutable_versions_and_does_not_publish_without_moderation(): void
    {
        $this->post(route('question-resources.store'), [
            'title' => 'Texto sobre biomas',
            'type' => 'text',
            'visibility_scope' => 'private',
            'body' => 'Versão inicial do texto.',
        ])->assertRedirect();

        $resource = QuestionResource::query()->sole();
        $versionOne = $resource->currentVersion()->sole();
        $this->assertSame(1, $versionOne->version_number);
        $this->assertSame('Texto sobre biomas', $versionOne->content['title']);
        $this->assertSame('Versão inicial do texto.', $versionOne->content['body']);

        $this->put(route('question-resources.update', $resource), [
            'title' => 'Texto sobre biomas brasileiros',
            'visibility_scope' => 'organization',
            'body' => 'Segunda versão do texto.',
        ])->assertRedirect();

        $resource->refresh();
        $this->assertSame(2, $resource->versions()->count());
        $this->assertSame('Versão inicial do texto.', $versionOne->fresh()->content['body']);
        $this->assertSame('Segunda versão do texto.', $resource->currentVersion->content['body']);

        $this->post(route('question-resources.store'), [
            'title' => 'Publicação sem moderação',
            'type' => 'text',
            'visibility_scope' => 'platform_public',
            'body' => 'Não deve ser publicada diretamente.',
        ])->assertSessionHasErrors('visibility_scope');
    }

    public function test_one_resource_is_reused_with_pinned_versions_and_duplicate_reuses_the_same_blob(): void
    {
        [$resource, $versionOne] = $this->resource('Texto compartilhado', 'Versão 1');
        $first = $this->question('Questão 1');
        $second = $this->question('Questão 2');
        $service = app(QuestionResourceService::class);

        $service->syncQuestion($first, [$resource->id], $this->teacher);
        $service->syncQuestion($second, [$resource->id], $this->teacher);
        $this->assertDatabaseCount('question_resources', 1);
        $this->assertDatabaseCount('question_question_resource', 2);

        $versionTwo = $service->createVersion($resource, [
            'body' => 'Versão 2', 'external_url' => null, 'alt_text' => null, 'metadata' => [],
        ], $this->teacher);
        $service->syncQuestion($first, [$resource->id], $this->teacher);
        $third = $this->question('Questão 3');
        $service->syncQuestion($third, [$resource->id], $this->teacher);

        $this->assertSame($versionOne->id, $first->resourceLinks()->sole()->question_resource_version_id);
        $this->assertSame($versionTwo->id, $third->resourceLinks()->sole()->question_resource_version_id);

        $this->post(route('questions.duplicate', $first))->assertRedirect();
        $copy = Question::query()->where('source_question_id', $first->id)->sole();
        $this->assertSame($resource->id, $copy->resourceLinks()->sole()->question_resource_id);
        $this->assertSame($versionOne->id, $copy->resourceLinks()->sole()->question_resource_version_id);
        $this->assertDatabaseCount('question_resources', 1);
        $this->assertSame($versionOne->storage_path, $copy->resourceLinks()->sole()->version->storage_path);

        $resource->update(['status' => 'archived']);
        $resource->delete();
        $this->post(route('questions.duplicate', $first))
            ->assertSessionHasErrors('resource_ids');
    }

    public function test_resource_visibility_must_cover_every_question_consumer_and_tenant(): void
    {
        [$resource] = $this->resource('Recurso privado', 'Conteúdo privado');
        $publicQuestion = $this->question('Questão institucional', 'org_public');
        $service = app(QuestionResourceService::class);

        $this->expectValidation(fn () => $service->syncQuestion($publicQuestion, [$resource->id], $this->teacher));

        $removableQuestion = $this->question('Questão que removerá o apoio');
        $service->syncQuestion($removableQuestion, [$resource->id], $this->teacher);
        $this->put(route('questions.update', $removableQuestion), [
            'type' => 'multiple_choice',
            'visibility_scope' => 'org_public',
            'statement' => 'Questão que removerá o apoio',
            'options' => ['A', 'B'],
            'correct_option' => 0,
            'stage' => 'ef_finais',
            'grade' => '6',
        ])->assertRedirect(route('questions.index'));
        $this->assertDatabaseMissing('question_question_resource', ['question_id' => $removableQuestion->id]);

        $resource->update(['visibility_scope' => 'organization']);
        $service->syncQuestion($publicQuestion, [$resource->id], $this->teacher);
        $this->assertDatabaseHas('question_question_resource', [
            'question_id' => $publicQuestion->id,
            'question_resource_id' => $resource->id,
        ]);

        $this->expectValidation(fn () => $service->assertLinkedQuestionsCompatible(
            $resource,
            'private',
            collect(),
        ));

        $sharedQuestion = $this->question('Questão compartilhada', 'shared_specific');
        $thirdTeacher = $this->teacher('terceiro@example.test');
        $sharedQuestion->shares()->createMany([
            ['shared_with_user_id' => $this->otherTeacher->id],
            ['shared_with_user_id' => $thirdTeacher->id],
        ]);
        [$sharedResource] = $this->resource('Apoio compartilhado', 'Apoio', 'shared_specific');
        $sharedResource->shares()->create(['shared_with_user_id' => $this->otherTeacher->id]);

        $this->expectValidation(fn () => $service->syncQuestion($sharedQuestion, [$sharedResource->id], $this->teacher));
        $sharedResource->shares()->create(['shared_with_user_id' => $thirdTeacher->id]);
        $service->syncQuestion($sharedQuestion, [$sharedResource->id], $this->teacher);

        $otherOrganization = Organization::create(['name' => 'Outra escola', 'active' => true]);
        $outsider = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $this->assertFalse($service->visibleTo($outsider)->whereKey($resource)->exists());

        $platformQuestion = $this->question('Questão pública da plataforma', 'platform_public');
        $this->expectValidation(fn () => $service->syncQuestion($platformQuestion, [$resource->id], $this->teacher));
        $this->expectValidation(fn () => $service->syncQuestion($platformQuestion, [$sharedResource->id], $this->teacher));
        [$platformResource] = $this->resource('Recurso público moderado', 'Conteúdo público', 'platform_public');
        $service->syncQuestion($platformQuestion, [$platformResource->id], $this->teacher);
        $this->assertSame($platformResource->id, $platformQuestion->resourceLinks()->sole()->question_resource_id);
    }

    public function test_private_upload_is_hashed_deduplicated_and_authorized(): void
    {
        Storage::fake('local');
        $firstUpload = UploadedFile::fake()->create('apoio.pdf', 10, 'application/pdf');

        $this->post(route('question-resources.store'), [
            'title' => 'Documento privado',
            'type' => 'document',
            'visibility_scope' => 'private',
            'file' => $firstUpload,
        ])->assertRedirect();

        $resource = QuestionResource::query()->sole();
        $version = $resource->currentVersion()->sole();
        Storage::disk('local')->assertExists($version->storage_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($version->storage_path)), $version->sha256);

        $this->put(route('question-resources.update', $resource), [
            'title' => 'Documento privado',
            'visibility_scope' => 'private',
            'file' => UploadedFile::fake()->create('apoio.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, $resource->versions()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('question-resources'));
        $this->get(route('question-resources.versions.download', [$resource, $version]))->assertOk();

        $this->actingAs($this->otherTeacher)
            ->get(route('question-resources.versions.download', [$resource, $version]))
            ->assertForbidden();
    }

    public function test_exam_and_revision_snapshots_keep_the_linked_resource_version(): void
    {
        Storage::fake('local');
        [$resource, $versionOne] = $this->resource('Fonte histórica', 'Conteúdo original');
        Storage::disk('local')->put('question-resources/history/map.png', 'historical-image');
        $resourceContent = $versionOne->content;
        $resourceContent['external_url'] = 'https://example.test/fonte-historica';
        $versionOne = app(QuestionResourceService::class)->createVersion($resource, $resourceContent, $this->teacher, [
            'storage_disk' => 'local',
            'storage_path' => 'question-resources/history/map.png',
            'mime_type' => 'image/png',
            'size_bytes' => 16,
            'sha256' => hash('sha256', 'historical-image'),
        ]);
        $question = $this->question('Questão com fonte');
        app(QuestionResourceService::class)->syncQuestion($question, [$resource->id], $this->teacher);
        $exam = Exam::create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação com fonte',
            'status' => 'published',
            'settings' => ['application_mode' => 'online'],
        ]);
        $exam->questions()->attach($question, ['points' => 2, 'order' => 1]);

        $copy = app(ExamPrintService::class)->generateCopies($exam, 1)->first();
        $revision = app(RevisionBuilderService::class)->createDraft($question, $this->teacher);
        app(QuestionResourceService::class)->createVersion($resource, [
            'body' => 'Conteúdo posterior', 'external_url' => null, 'alt_text' => null, 'metadata' => [],
        ], $this->teacher);

        $this->assertSame($versionOne->id, data_get($copy->question_snapshot, '0.resources.0.resource_version_id'));
        $this->assertSame('Conteúdo original', data_get($copy->question_snapshot, '0.resources.0.content.body'));
        $this->assertSame($versionOne->id, data_get($revision->items->first()->content, 'resources.0.resource_version_id'));
        $this->assertSame('Conteúdo original', data_get($revision->items->first()->content, 'resources.0.content.body'));
        $this->assertSame('question-resources/history/map.png', data_get($revision->items->first()->content, 'resources.0.file.storage_path'));

        $item = $revision->items()->firstOrFail();
        $this->actingAs($this->teacher)->put(route('revisions.items.update', [$revision, $item]), [
            'type' => $item->type,
            'prompt' => $item->prompt,
            'content_json' => json_encode([
                'options' => $item->content['options'],
                'resources' => [['resource_version_id' => 999999, 'file' => ['storage_path' => 'injetado']]],
            ]),
            'solution_json' => json_encode($item->solution),
            'difficulty' => 1,
            'points' => $item->points,
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame($versionOne->id, data_get($item->fresh()->content, 'resources.0.resource_version_id'));

        $student = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'student',
            'status' => 'active',
        ]);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        $class = SchoolClass::create([
            'organization_id' => $this->organization->id,
            'owner_type' => 'user',
            'owner_id' => $this->teacher->id,
            'name' => 'Turma dos recursos',
            'year' => 2026,
        ]);
        $class->students()->attach($student);
        $revision->update(['status' => 'published']);
        $revision->schoolClasses()->attach($class);
        $attempt = $revision->attempts()->create([
            'student_id' => $student->id,
            'organization_id' => $this->organization->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
        $item = $revision->items()->firstOrFail();

        $resourceUrl = route('student.revisions.resource', [$revision, $attempt, $item, $versionOne]);
        $this->actingAs($student)
            ->get(route('student.revisions.execute', [$revision, $attempt]))
            ->assertOk()
            ->assertSee($resourceUrl, false)
            ->assertSee('https://example.test/fonte-historica', false);
        $this->actingAs($student)
            ->get(route('student.revisions.resource', [$revision, $attempt, $item, $versionOne]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_student_can_open_only_a_resource_from_an_active_owned_attempt(): void
    {
        Storage::fake('local');
        [$resource, $version] = $this->resource('Imagem da questão', 'Observe a imagem');
        Storage::disk('local')->put('question-resources/test/image.png', 'image-bytes');
        $version = app(QuestionResourceService::class)->createVersion($resource, $version->content, $this->teacher, [
            'storage_disk' => 'local',
            'storage_path' => 'question-resources/test/image.png',
            'mime_type' => 'image/png',
            'size_bytes' => 11,
            'sha256' => hash('sha256', 'image-bytes'),
        ]);
        $question = $this->question('Questão visual');
        app(QuestionResourceService::class)->syncQuestion($question, [$resource->id], $this->teacher);
        $student = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'student',
            'status' => 'active',
        ]);
        $student->assignRole(Role::findOrCreate('student', 'web'));
        $exam = Exam::create([
            'organization_id' => $this->organization->id,
            'author_id' => $this->teacher->id,
            'title' => 'Avaliação visual',
            'status' => 'published',
            'settings' => ['application_mode' => 'online'],
        ]);
        $exam->questions()->attach($question, ['points' => 1, 'order' => 1]);
        $exam->students()->attach($student, [
            'organization_id' => $this->organization->id,
            'assigned_by' => $this->teacher->id,
        ]);
        ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
            'client_token' => (string) Str::uuid(),
        ]);

        $this->actingAs($student)
            ->get(route('student.exam.resource', [$exam, $version]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        [$unlinkedResource, $unlinkedVersion] = $this->resource('Arquivo não vinculado', 'Outro');
        $this->actingAs($student)
            ->get(route('student.exam.resource', [$exam, $unlinkedVersion]))
            ->assertNotFound();
    }

    /** @return array{QuestionResource, QuestionResourceVersion} */
    private function resource(string $title, string $body, string $visibility = 'private'): array
    {
        $resource = QuestionResource::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'title' => $title,
            'type' => 'text',
            'visibility_scope' => $visibility,
            'status' => 'active',
        ]);
        $version = app(QuestionResourceService::class)->createVersion($resource, [
            'title' => $title,
            'body' => $body,
            'external_url' => null,
            'alt_text' => null,
            'metadata' => [],
        ], $this->teacher);

        return [$resource, $version];
    }

    private function question(string $statement, string $visibility = 'private'): Question
    {
        return Question::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => $statement,
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => $visibility,
        ]);
    }

    private function teacher(string $email): User
    {
        $teacher = User::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'teacher',
            'status' => 'active',
            'email' => $email,
        ]);
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));

        return $teacher;
    }

    private function expectValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Era esperada uma falha de compatibilidade de visibilidade.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource_ids', $exception->errors());
        }
    }
}
