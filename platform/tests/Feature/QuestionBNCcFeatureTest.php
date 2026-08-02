<?php

namespace Tests\Feature;

use App\Models\BNCcNode;
use App\Models\Discipline;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionBNCcFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected Organization $org;

    protected Discipline $discipline;

    protected BNCcNode $skill1;

    protected BNCcNode $skill2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Org Test',
            'subdomain' => 'org-test-bncc',
            'active' => true,
        ]);

        Role::findOrCreate('teacher', 'web');
        $this->teacher = User::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->discipline = Discipline::create([
            'organization_id' => $this->org->id,
            'name' => 'Matemática Teste',
        ]);

        $this->skill1 = BNCcNode::create([
            'discipline_id' => $this->discipline->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06MA01',
            'title' => 'Title 1',
        ]);

        $this->skill2 = BNCcNode::create([
            'discipline_id' => $this->discipline->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06MA02',
            'title' => 'Title 2',
        ]);
    }

    public function test_can_create_question_with_stage_grade_and_bncc_skills()
    {
        $payload = [
            'type' => 'essay',
            'visibility_scope' => 'private',
            'statement' => 'Qual é o valor de x?',
            'discipline_id' => $this->discipline->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'bncc_skills' => [
                $this->skill1->id,
                $this->skill2->id,
            ],
        ];

        $response = $this->actingAs($this->teacher)
            ->post(route('questions.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('questions.index'));

        $this->assertDatabaseHas('questions', [
            'owner_id' => $this->teacher->id,
            'type' => 'essay',
            'stage' => 'ef_finais',
            'grade' => '6',
        ]);

        $question = Question::where('owner_id', $this->teacher->id)->first();

        $this->assertCount(2, $question->bnccSkills);
        $this->assertTrue($question->bnccSkills->contains($this->skill1->id));
        $this->assertTrue($question->bnccSkills->contains($this->skill2->id));
    }

    public function test_cannot_create_question_with_invalid_bncc_skill_id()
    {
        $payload = [
            'type' => 'essay',
            'visibility_scope' => 'private',
            'statement' => 'Teste Invalido',
            'stage' => 'ef_finais',
            'grade' => '6',
            'bncc_skills' => [
                999999, // ID que não existe
            ],
        ];

        $response = $this->actingAs($this->teacher)
            ->post(route('questions.store'), $payload);

        $response->assertSessionHasErrors(['bncc_skills.0']);

        $this->assertDatabaseMissing('questions', [
            'statement' => 'Teste Invalido', // como é array, não vai ser gravado na coluna string, mas procuramos count = 0
        ]);
        $this->assertEquals(0, Question::count());
    }

    public function test_can_update_question_and_sync_bncc_skills()
    {
        // Criar questão inicial com 1 skill
        $question = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'essay',
            'visibility_scope' => 'private',
            'content' => ['statement' => 'Original'],
            'stage' => 'ef_finais',
            'grade' => '6',
        ]);
        $question->bnccSkills()->sync([$this->skill1->id]);

        $this->assertCount(1, $question->fresh()->bnccSkills);

        // Atualizar enviando as duas skills
        $payload = [
            'type' => 'essay',
            'visibility_scope' => 'org_public',
            'statement' => 'Updated',
            'stage' => 'em',
            'grade' => '1',
            'bncc_skills' => [
                $this->skill1->id,
                $this->skill2->id,
            ],
        ];

        $response = $this->actingAs($this->teacher)
            ->put(route('questions.update', $question->id), $payload);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'stage' => 'em',
            'grade' => '1',
            'visibility_scope' => 'org_public',
        ]);

        $this->assertCount(2, $question->fresh()->bnccSkills);
    }
}
