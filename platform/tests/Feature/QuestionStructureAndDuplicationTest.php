<?php

namespace Tests\Feature;

use App\Models\BNCcNode;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\KnowledgeArea;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionStructureAndDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $teacher;

    private KnowledgeArea $knowledgeArea;

    private Discipline $discipline;

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
        $this->actingAs($this->teacher);

        $this->knowledgeArea = KnowledgeArea::create([
            'organization_id' => $this->organization->id,
            'name' => 'Linguagens',
        ]);
        $this->discipline = Discipline::create([
            'organization_id' => $this->organization->id,
            'name' => 'Língua Portuguesa',
        ]);
    }

    public function test_multiple_choice_requires_two_options_and_a_filled_correct_option(): void
    {
        $base = $this->basePayload('multiple_choice');

        $this->post(route('questions.store'), array_merge($base, [
            'options' => ['Única opção', '', ''],
            'correct_option' => 0,
        ]))->assertSessionHasErrors('options');

        $this->post(route('questions.store'), array_merge($base, [
            'options' => ['Primeira', 'Segunda', ''],
            'correct_option' => 2,
        ]))->assertSessionHasErrors('correct_option');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_multiple_choice_normalizes_blank_gaps_and_remaps_correct_index(): void
    {
        $this->post(route('questions.store'), array_merge($this->basePayload('multiple_choice'), [
            'options' => ['Alternativa A', '', 'Alternativa C'],
            'correct_option' => 2,
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('questions.index'));

        $question = Question::sole();
        $this->assertSame(['Alternativa A', 'Alternativa C'], $question->content['options']);
        $this->assertSame(1, $question->content['correct_option']);
    }

    public function test_true_false_requires_an_explicit_answer(): void
    {
        $this->post(route('questions.store'), $this->basePayload('true_false'))
            ->assertSessionHasErrors('tf_answer');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_essay_rubric_is_stored_and_duplicate_preserves_taxonomy_and_skill_relations(): void
    {
        $bnccSkill = BNCcNode::create([
            'discipline_id' => $this->discipline->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF67LP01',
            'title' => 'Analisar estratégias argumentativas',
        ]);
        $customSkill = CustomSkill::create([
            'organization_id' => $this->organization->id,
            'name' => 'Construção de argumentos',
        ]);

        $this->post(route('questions.store'), array_merge($this->basePayload('essay'), [
            'knowledge_area_id' => $this->knowledgeArea->id,
            'discipline_id' => $this->discipline->id,
            'level' => 'hard',
            'bncc_skills' => [$bnccSkill->id],
            'custom_skills' => [$customSkill->id],
            'rubric_title' => 'Texto argumentativo',
            'rubric_description' => 'Avaliação por evidências',
            'rubric_criteria' => [
                [
                    'title' => 'Tese',
                    'description' => 'Apresenta uma tese clara.',
                    'points' => 2.5,
                ],
                [
                    'title' => 'Evidências',
                    'description' => 'Sustenta a tese com evidências.',
                    'points' => 3.5,
                ],
            ],
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('questions.index'));

        $original = Question::sole();
        $this->assertSame('Texto argumentativo', $original->content['rubric']['title']);
        $this->assertSame(2.5, $original->content['rubric']['criteria'][0]['points']);

        $this->post(route('questions.duplicate', $original))
            ->assertRedirect();

        $copy = Question::where('source_question_id', $original->id)->sole();
        $this->assertSame($this->teacher->id, $copy->owner_id);
        $this->assertSame('private', $copy->visibility_scope);
        $this->assertSame($original->content, $copy->content);
        $this->assertSame($original->knowledge_area_id, $copy->knowledge_area_id);
        $this->assertSame($original->discipline_id, $copy->discipline_id);
        $this->assertSame($original->level, $copy->level);
        $this->assertSame($original->stage, $copy->stage);
        $this->assertSame($original->grade, $copy->grade);
        $this->assertEquals([$bnccSkill->id], $copy->bnccSkills->modelKeys());
        $this->assertEquals([$customSkill->id], $copy->customSkills->modelKeys());
    }

    private function basePayload(string $type): array
    {
        return [
            'type' => $type,
            'visibility_scope' => 'private',
            'statement' => 'Enunciado de teste',
            'stage' => 'ef_finais',
            'grade' => '6',
        ];
    }
}
