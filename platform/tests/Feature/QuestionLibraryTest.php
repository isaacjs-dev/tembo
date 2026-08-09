<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionResource;
use App\Models\User;
use App\Services\QuestionResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $otherOrganization;

    private User $teacher;

    private User $colleague;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Escola local', 'active' => true]);
        $this->otherOrganization = Organization::create(['name' => 'Escola externa', 'active' => true]);
        $this->teacher = $this->teacher($this->organization, 'professor@example.test', 'Professor Local');
        $this->colleague = $this->teacher($this->organization, 'colega@example.test', 'Professor Colega');
        $this->outsider = $this->teacher($this->otherOrganization, 'externo@example.test', 'Professor Externo');
        $this->actingAs($this->teacher);
    }

    public function test_question_library_separates_all_scopes_and_keeps_private_items_hidden(): void
    {
        $mine = $this->question($this->teacher, 'Questão pessoal', 'private');
        $shared = $this->question($this->colleague, 'Questão específica', 'shared_specific');
        $shared->shares()->create(['shared_with_user_id' => $this->teacher->id]);
        $institution = $this->question($this->colleague, 'Questão institucional', 'org_public');
        $platform = $this->question($this->outsider, 'Questão pública global', 'platform_public');
        $hidden = $this->question($this->outsider, 'Questão externa privada', 'private');
        $staleShare = $this->question($this->colleague, 'Questão privada com share obsoleto', 'private');
        $staleShare->shares()->create(['shared_with_user_id' => $this->teacher->id]);

        $this->assertQuestionScope('mine', [$mine->id]);
        $this->assertQuestionScope('shared', [$shared->id]);
        $this->assertQuestionScope('institution', [$institution->id]);
        $this->assertQuestionScope('platform', [$platform->id]);

        $this->get(route('questions.index', ['tab' => 'public']))
            ->assertOk()
            ->assertViewHas('tab', 'institution')
            ->assertViewHas('questions', fn ($items) => $items->modelKeys() === [$institution->id]);

        $this->get(route('questions.index', ['tab' => 'not-a-scope']))
            ->assertOk()
            ->assertViewHas('tab', 'mine');

        $this->get(route('questions.index', ['tab' => 'platform']))
            ->assertDontSee('Questão externa privada')
            ->assertDontSee('Questão privada com share obsoleto');
        $this->assertFalse($hidden->is($platform));
    }

    public function test_question_library_search_filters_and_paginates_without_loading_the_catalog(): void
    {
        foreach (range(1, 13) as $number) {
            $question = $this->question(
                $this->outsider,
                $number === 13 ? 'Fotossíntese avançada' : "Questão pública {$number}",
                'platform_public',
            );
            $question->update([
                'type' => $number === 13 ? 'essay' : 'multiple_choice',
                'level' => $number === 13 ? 'hard' : 'easy',
                'stage' => $number === 13 ? 'em' : 'ef_finais',
                'grade' => $number === 13 ? '2' : '6',
            ]);
        }

        $this->get(route('questions.index', ['tab' => 'platform']))
            ->assertOk()
            ->assertViewHas('questions', function ($items): bool {
                return $items->total() === 13 && $items->count() === 12 && $items->perPage() === 12;
            });

        $this->get(route('questions.index', [
            'tab' => 'platform',
            'search' => 'Fotossíntese',
            'type' => 'essay',
            'level' => 'hard',
            'stage' => 'em',
            'grade' => '2',
        ]))
            ->assertOk()
            ->assertViewHas('questions', fn ($items) => $items->total() === 1
                && data_get($items->first()->content, 'statement') === 'Fotossíntese avançada');

        $this->get(route('questions.index', [
            'tab' => 'platform',
            'discipline_id' => 999999,
            'search' => 'Fotossíntese',
        ]))
            ->assertOk()
            ->assertDontSee('question-discipline-filter')
            ->assertViewHas('questions', fn ($items) => $items->total() === 1);
    }

    public function test_platform_question_is_read_only_and_duplicates_into_a_traced_private_copy(): void
    {
        $foreignDiscipline = Discipline::create([
            'organization_id' => $this->otherOrganization->id,
            'name' => 'Biologia externa',
        ]);
        $resource = $this->resource($this->outsider, 'Apoio público', 'platform_public', 'Conteúdo público');
        $original = $this->question($this->outsider, 'Questão global para duplicar', 'platform_public');
        $original->update(['discipline_id' => $foreignDiscipline->id]);
        app(QuestionResourceService::class)->syncQuestion($original, [$resource->id], $this->outsider);
        $sourceVersion = $original->resourceLinks()->sole()->question_resource_version_id;

        $this->actingAs($this->outsider);
        $this->get(route('questions.edit', $original))->assertForbidden();
        $this->delete(route('questions.destroy', $original))->assertForbidden();
        $this->actingAs($this->teacher);
        $this->get(route('questions.edit', $original))->assertNotFound();
        $this->delete(route('questions.destroy', $original))->assertNotFound();

        $this->post(route('questions.duplicate', $original))->assertRedirect();

        $copy = Question::query()->where('source_question_id', $original->id)->sole();
        $this->assertSame($this->organization->id, $copy->organization_id);
        $this->assertSame($this->teacher->id, $copy->owner_id);
        $this->assertSame('private', $copy->visibility_scope);
        $this->assertNull($copy->discipline_id);
        $this->assertNull($copy->knowledge_area_id);
        $this->assertSame($resource->id, $copy->resourceLinks()->sole()->question_resource_id);
        $this->assertSame($sourceVersion, $copy->resourceLinks()->sole()->question_resource_version_id);
        $this->assertDatabaseHas('usage_events', [
            'user_id' => $this->teacher->id,
            'resource_key' => 'monthly_questions_created',
            'idempotency_key' => "question:create:{$copy->id}",
            'context_id' => $copy->id,
        ]);

        $this->post(route('questions.store'), $this->questionPayload('Autopublicação proibida', 'platform_public'))
            ->assertSessionHasErrors('visibility_scope');
    }

    public function test_resource_library_scopes_searches_content_and_author_and_enforces_policy(): void
    {
        $mine = $this->resource($this->teacher, 'Meu texto', 'private', 'Ciclo da água');
        $shared = $this->resource($this->colleague, 'Mapa compartilhado', 'shared_specific', 'Geografia');
        $shared->shares()->create(['shared_with_user_id' => $this->teacher->id]);
        $institution = $this->resource($this->colleague, 'Tabela institucional', 'organization', 'Estatística');
        $platform = $this->resource($this->outsider, 'Documento global', 'platform_public', 'Astronomia pública');
        $hidden = $this->resource($this->outsider, 'Documento secreto', 'private', 'Não revelar');
        $staleShare = $this->resource($this->colleague, 'Recurso privado obsoleto', 'private', 'Não listar');
        $staleShare->shares()->create(['shared_with_user_id' => $this->teacher->id]);

        $this->assertResourceScope('mine', [$mine->id]);
        $this->assertResourceScope('shared', [$shared->id]);
        $this->assertResourceScope('institution', [$institution->id]);
        $this->assertResourceScope('platform', [$platform->id]);

        $this->get(route('question-resources.index', ['scope' => 'platform', 'search' => 'Astronomia']))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->modelKeys() === [$platform->id]);
        $this->get(route('question-resources.index', ['scope' => 'platform', 'search' => 'Professor Externo']))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->modelKeys() === [$platform->id]);

        foreach (range(1, 12) as $number) {
            $this->resource(
                $this->outsider,
                "Recurso público {$number}",
                'platform_public',
                "Conteúdo público {$number}",
            );
        }
        $this->get(route('question-resources.index', ['scope' => 'platform']))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 13
                && $items->count() === 12
                && $items->perPage() === 12);

        $this->get(route('question-resources.edit', $platform))->assertForbidden();
        $this->delete(route('question-resources.destroy', $platform))->assertForbidden();
        $this->actingAs($this->outsider);
        $this->get(route('question-resources.edit', $platform))->assertForbidden();
        $this->delete(route('question-resources.destroy', $platform))->assertForbidden();
        $this->actingAs($this->teacher);
        $this->assertFalse($hidden->is($platform));
        $this->assertFalse(app(QuestionResourceService::class)->canView($this->teacher, $staleShare));

        $this->post(route('question-resources.store'), [
            'title' => 'Publicação direta',
            'type' => 'text',
            'visibility_scope' => 'platform_public',
            'body' => 'Aguarda moderação.',
        ])->assertSessionHasErrors('visibility_scope');
    }

    /** @param array<int, int> $expected */
    private function assertQuestionScope(string $scope, array $expected): void
    {
        $this->get(route('questions.index', ['tab' => $scope]))
            ->assertOk()
            ->assertViewHas('questions', fn ($items) => $items->modelKeys() === $expected);
    }

    /** @param array<int, int> $expected */
    private function assertResourceScope(string $scope, array $expected): void
    {
        $this->get(route('question-resources.index', ['scope' => $scope]))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->modelKeys() === $expected);
    }

    private function teacher(Organization $organization, string $email, string $name): User
    {
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
            'email' => $email,
            'name' => $name,
        ]);
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));

        return $teacher;
    }

    private function question(User $owner, string $statement, string $scope): Question
    {
        return Question::create([
            'organization_id' => $owner->organization_id,
            'owner_id' => $owner->id,
            'type' => 'multiple_choice',
            'visibility_scope' => $scope,
            'content' => [
                'statement' => $statement,
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'stage' => 'ef_finais',
            'grade' => '6',
        ]);
    }

    private function resource(User $owner, string $title, string $scope, string $body): QuestionResource
    {
        $resource = QuestionResource::create([
            'organization_id' => $owner->organization_id,
            'owner_id' => $owner->id,
            'title' => $title,
            'type' => 'text',
            'visibility_scope' => $scope,
            'status' => 'active',
        ]);
        app(QuestionResourceService::class)->createVersion($resource, [
            'title' => $title,
            'body' => $body,
            'external_url' => null,
            'alt_text' => null,
            'metadata' => [],
        ], $owner);

        return $resource;
    }

    /** @return array<string, mixed> */
    private function questionPayload(string $statement, string $scope): array
    {
        return [
            'type' => 'multiple_choice',
            'visibility_scope' => $scope,
            'statement' => $statement,
            'options' => ['A', 'B'],
            'correct_option' => 0,
            'stage' => 'ef_finais',
            'grade' => '6',
        ];
    }
}
