<?php

namespace Tests\Feature\OMR;

use App\Models\Exam;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Question;
use App\Models\User;
use App\Services\OmrGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OmrGradingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $teacher;

    protected User $student;

    protected Exam $exam;

    protected OmrGradingService $gradingService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'global_admin']);
        Role::firstOrCreate(['name' => 'institution_admin']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 10, 'features' => []]);
        $this->org = Organization::create(['name' => 'Escola Teste']);

        $this->teacher = User::factory()->create([
            'type' => 'teacher',
            'organization_id' => $this->org->id,
        ]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'type' => 'student',
            'organization_id' => $this->org->id,
        ]);
        $this->student->assignRole('student');

        $this->gradingService = new OmrGradingService;
    }

    private function createExamWithQuestions(): Exam
    {
        $exam = Exam::create([
            'title' => 'Prova Mista',
            'organization_id' => $this->org->id,
            'author_id' => $this->teacher->id,
            'status' => 'published',
        ]);

        // Q1: Múltipla escolha - correta = C (index 2)
        $q1 = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Qual a capital do Brasil?',
                'options' => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador'],
                'correct_option' => 2,
            ],
        ]);

        // Q2: V/F - correta = 1 (verdadeiro)
        $q2 = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'true_false',
            'content' => [
                'statement' => 'A água ferve a 100°C ao nível do mar.',
                'correct_option' => 1,
            ],
        ]);

        // Q3: V/F - correta = 0 (falso)
        $q3 = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'true_false',
            'content' => [
                'statement' => 'A Terra é plana.',
                'correct_option' => 0,
            ],
        ]);

        // Q4: Múltipla escolha 5 opções - correta = E (index 4)
        $q4 = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Maior planeta do sistema solar?',
                'options' => ['Marte', 'Vênus', 'Saturno', 'Netuno', 'Júpiter'],
                'correct_option' => 4,
            ],
        ]);

        // Q5: Múltipla escolha 2 opções (A,B) - correta = B (index 1)
        $q5 = Question::create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'PHP é compilado?',
                'options' => ['Sim', 'Não'],
                'correct_option' => 1,
            ],
        ]);

        $exam->questions()->attach([
            $q1->id => ['points' => 2, 'order' => 1],
            $q2->id => ['points' => 1, 'order' => 2],
            $q3->id => ['points' => 1, 'order' => 3],
            $q4->id => ['points' => 2, 'order' => 4],
            $q5->id => ['points' => 1, 'order' => 5],
        ]);

        return $exam->fresh(['questions']);
    }

    // ================================================================
    // gradeAnswers — Respostas corretas (tudo certo = 7 pontos)
    // ================================================================

    public function test_all_correct_with_int_values(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => 2,     // MC: C (int)
            $questions[1]->id => 1,     // TF: true (int)
            $questions[2]->id => 0,     // TF: false (int)
            $questions[3]->id => 4,     // MC: E (int)
            $questions[4]->id => 1,     // MC: B (int)
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertEquals(7, $result['score']);
        $this->assertEquals(7, $result['total_points']);
    }

    public function test_all_correct_with_string_values_from_form(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        // Simula exatamente o que o <select> do blade envia
        $answers = [
            $questions[0]->id => '2',       // MC: "2" (string do form)
            $questions[1]->id => '1',       // TF: "1" (string - NOVO: antes era "true")
            $questions[2]->id => '0',       // TF: "0" (string - NOVO: antes era "false")
            $questions[3]->id => '4',       // MC: "4" (string do form)
            $questions[4]->id => '1',       // MC: "1" (string do form)
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertEquals(7, $result['score']);
    }

    // ================================================================
    // Cenário do BUG ORIGINAL: form envia "true"/"false" como strings
    // ================================================================

    public function test_tf_with_legacy_true_false_strings(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        // Cenário antigo: form enviava "true"/"false"
        // Com o bug (int)"true" = 0, isso dava errado
        $answers = [
            $questions[0]->id => '2',
            $questions[1]->id => 'true',    // V/F com string "true"
            $questions[2]->id => 'false',   // V/F com string "false"
            $questions[3]->id => '4',
            $questions[4]->id => '1',
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        // Q2: gabarito=1, resposta="true" → normaliza para 1 → CORRETO
        $this->assertTrue($result['details'][$questions[1]->id]['correct']);
        // Q3: gabarito=0, resposta="false" → normaliza para 0 → CORRETO
        $this->assertTrue($result['details'][$questions[2]->id]['correct']);
        $this->assertEquals(7, $result['score']);
    }

    public function test_tf_wrong_answer_with_strings(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => '2',
            $questions[1]->id => 'false',   // Gabarito=1(V), resposta="false" → ERRADO
            $questions[2]->id => 'true',    // Gabarito=0(F), resposta="true" → ERRADO
            $questions[3]->id => '4',
            $questions[4]->id => '1',
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertFalse($result['details'][$questions[1]->id]['correct']);
        $this->assertFalse($result['details'][$questions[2]->id]['correct']);
        $this->assertEquals(5, $result['score']); // 7 - 2 (errou Q2 e Q3)
    }

    // ================================================================
    // Respostas em branco
    // ================================================================

    public function test_blank_answers_score_zero(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => null,
            $questions[1]->id => '',
            $questions[2]->id => null,
            $questions[3]->id => '',
            $questions[4]->id => null,
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertEquals(0, $result['score']);
        $this->assertEquals(7, $result['total_points']);
    }

    public function test_blank_does_not_match_correct_option_zero(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        // Q3 tem correct_option = 0 (falso). Branco NÃO deve bater com 0.
        $answers = [
            $questions[0]->id => null,
            $questions[1]->id => null,
            $questions[2]->id => null,  // Branco — gabarito é 0
            $questions[3]->id => null,
            $questions[4]->id => null,
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertFalse($result['details'][$questions[2]->id]['correct']);
    }

    // ================================================================
    // Formatos mistos de V/F (V, F, T, verdadeiro, etc.)
    // ================================================================

    public function test_tf_v_f_letters(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => '2',
            $questions[1]->id => 'V',   // Verdadeiro → gabarito=1 → CORRETO
            $questions[2]->id => 'F',   // Falso → gabarito=0 → CORRETO
            $questions[3]->id => '4',
            $questions[4]->id => '1',
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertTrue($result['details'][$questions[1]->id]['correct']);
        $this->assertTrue($result['details'][$questions[2]->id]['correct']);
        $this->assertEquals(7, $result['score']);
    }

    // ================================================================
    // Exam não encontrado
    // ================================================================

    public function test_nonexistent_exam_returns_zero(): void
    {
        $result = $this->gradingService->gradeAnswers(99999, null, []);

        $this->assertEquals(0, $result['score']);
        $this->assertEquals(0, $result['total_points']);
        $this->assertEmpty($result['details']);
    }

    // ================================================================
    // MC com letras
    // ================================================================

    public function test_mc_answers_as_letters(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => 'C',   // Letra → index 2 → CORRETO
            $questions[1]->id => '1',
            $questions[2]->id => '0',
            $questions[3]->id => 'E',   // Letra → index 4 → CORRETO
            $questions[4]->id => 'B',   // Letra → index 1 → CORRETO
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $this->assertTrue($result['details'][$questions[0]->id]['correct']);
        $this->assertTrue($result['details'][$questions[3]->id]['correct']);
        $this->assertTrue($result['details'][$questions[4]->id]['correct']);
        $this->assertEquals(7, $result['score']);
    }

    // ================================================================
    // Detalhes do resultado
    // ================================================================

    public function test_details_include_normalized_values(): void
    {
        $exam = $this->createExamWithQuestions();
        $questions = $exam->questions;

        $answers = [
            $questions[0]->id => '2',
            $questions[1]->id => 'true',
            $questions[2]->id => '0',
            $questions[3]->id => '4',
            $questions[4]->id => '1',
        ];

        $result = $this->gradingService->gradeAnswers($exam->id, null, $answers);

        $detail = $result['details'][$questions[1]->id];
        $this->assertSame(1, $detail['normalized']);
        $this->assertSame(1, $detail['correct_normalized']);
        $this->assertSame('graded', $detail['status']);
    }
}
