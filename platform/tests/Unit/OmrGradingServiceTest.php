<?php

namespace Tests\Unit;

use App\Services\OmrGradingService;
use PHPUnit\Framework\TestCase;

class OmrGradingServiceTest extends TestCase
{
    // ================================================================
    // normalizeAnswer — true_false
    // ================================================================

    public function test_tf_bool_true_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer(true, 'true_false'));
    }

    public function test_tf_bool_false_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer(false, 'true_false'));
    }

    public function test_tf_string_true_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('true', 'true_false'));
    }

    public function test_tf_string_false_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('false', 'true_false'));
    }

    public function test_tf_string_true_uppercase_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('True', 'true_false'));
    }

    public function test_tf_string_fals_e_uppercase_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('FALSE', 'true_false'));
    }

    public function test_tf_string_1_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('1', 'true_false'));
    }

    public function test_tf_string_0_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('0', 'true_false'));
    }

    public function test_tf_int_1_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer(1, 'true_false'));
    }

    public function test_tf_int_0_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer(0, 'true_false'));
    }

    public function test_tf_v_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('V', 'true_false'));
    }

    public function test_tf_f_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('F', 'true_false'));
    }

    public function test_tf_t_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('T', 'true_false'));
    }

    public function test_tf_v_lowercase_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('v', 'true_false'));
    }

    public function test_tf_f_lowercase_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('f', 'true_false'));
    }

    // ================================================================
    // normalizeAnswer — true_false — valores nulos/vazios
    // ================================================================

    public function test_tf_null_normalizes_to_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer(null, 'true_false'));
    }

    public function test_tf_empty_string_normalizes_to_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer('', 'true_false'));
    }

    public function test_tf_dash_normalizes_to_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer('—', 'true_false'));
    }

    // ================================================================
    // normalizeAnswer — true_false — valores inválidos
    // ================================================================

    public function test_tf_invalid_string_returns_invalid(): void
    {
        $result = OmrGradingService::normalizeAnswer('maybe', 'true_false');
        $this->assertTrue(OmrGradingService::isInvalidAnswer($result));
    }

    public function test_tf_int_5_returns_invalid(): void
    {
        $result = OmrGradingService::normalizeAnswer(5, 'true_false');
        $this->assertTrue(OmrGradingService::isInvalidAnswer($result));
    }

    // ================================================================
    // normalizeAnswer — multiple_choice
    // ================================================================

    public function test_mc_int_0_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer(0, 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_int_3_normalizes_to_3(): void
    {
        $this->assertSame(3, OmrGradingService::normalizeAnswer(3, 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_string_0_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('0', 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_string_2_normalizes_to_2(): void
    {
        $this->assertSame(2, OmrGradingService::normalizeAnswer('2', 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_letter_a_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('A', 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_letter_d_normalizes_to_3(): void
    {
        $this->assertSame(3, OmrGradingService::normalizeAnswer('D', 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_letter_e_normalizes_to_4_with_5_options(): void
    {
        $this->assertSame(4, OmrGradingService::normalizeAnswer('E', 'multiple_choice', ['A', 'B', 'C', 'D', 'E']));
    }

    public function test_mc_letter_lowercase_b_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('b', 'multiple_choice', ['A', 'B', 'C', 'D']));
    }

    public function test_mc_null_normalizes_to_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer(null, 'multiple_choice'));
    }

    public function test_mc_empty_string_normalizes_to_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer('', 'multiple_choice'));
    }

    public function test_mc_index_out_of_range_returns_invalid(): void
    {
        // 4 options but index 5
        $result = OmrGradingService::normalizeAnswer(5, 'multiple_choice', ['A', 'B', 'C', 'D']);
        $this->assertTrue(OmrGradingService::isInvalidAnswer($result));
    }

    public function test_mc_letter_f_returns_invalid_for_5_options(): void
    {
        $result = OmrGradingService::normalizeAnswer('F', 'multiple_choice', ['A', 'B', 'C', 'D', 'E']);
        $this->assertTrue(OmrGradingService::isInvalidAnswer($result));
    }

    // ================================================================
    // normalizeAnswer — essay
    // ================================================================

    public function test_essay_always_returns_null(): void
    {
        $this->assertNull(OmrGradingService::normalizeAnswer('qualquer coisa', 'essay'));
    }

    // ================================================================
    // Cenários de bug original — (int)"true" e (int)"false"
    // ================================================================

    public function test_bug_regression_string_true_must_not_become_zero(): void
    {
        // No bug original: (int)"true" = 0, causando falha total em T/F
        $normalized = OmrGradingService::normalizeAnswer('true', 'true_false');
        $this->assertSame(1, $normalized);
        $this->assertNotSame(0, $normalized);
    }

    public function test_bug_regression_string_false_must_be_zero_not_wrong(): void
    {
        $normalized = OmrGradingService::normalizeAnswer('false', 'true_false');
        $this->assertSame(0, $normalized);
    }

    public function test_bug_regression_true_correct_vs_true_answer(): void
    {
        // Gabarito: correct_option = 1 (verdadeiro)
        // Resposta: "true" (string do form)
        $answer = OmrGradingService::normalizeAnswer('true', 'true_false');
        $correct = OmrGradingService::normalizeAnswer(1, 'true_false');
        $this->assertSame($answer, $correct); // Ambos devem ser 1
    }

    public function test_bug_regression_false_correct_vs_false_answer(): void
    {
        // Gabarito: correct_option = 0 (falso)
        // Resposta: "false" (string do form)
        $answer = OmrGradingService::normalizeAnswer('false', 'true_false');
        $correct = OmrGradingService::normalizeAnswer(0, 'true_false');
        $this->assertSame($answer, $correct); // Ambos devem ser 0
    }

    public function test_bug_regression_true_answer_should_not_match_false_correct(): void
    {
        // Gabarito: correct_option = 0 (falso)
        // Resposta: "true" (string do form)
        $answer = OmrGradingService::normalizeAnswer('true', 'true_false');
        $correct = OmrGradingService::normalizeAnswer(0, 'true_false');
        $this->assertNotSame($answer, $correct); // 1 !== 0
    }

    public function test_bug_regression_blank_should_not_be_correct(): void
    {
        // Resposta em branco não deve coincidir com gabarito 0 (falso)
        $answer = OmrGradingService::normalizeAnswer('', 'true_false');
        $correct = OmrGradingService::normalizeAnswer(0, 'true_false');
        $this->assertNull($answer);
        $this->assertNotSame($answer, $correct);
    }

    public function test_bug_regression_mc_blank_not_correct_for_option_0(): void
    {
        // Resposta em branco não deve coincidir com gabarito 0 (opção A)
        $answer = OmrGradingService::normalizeAnswer('', 'multiple_choice');
        $correct = OmrGradingService::normalizeAnswer(0, 'multiple_choice');
        $this->assertNull($answer);
        $this->assertNotSame($answer, $correct);
    }

    // ================================================================
    // isInvalidAnswer
    // ================================================================

    public function test_is_invalid_answer_true_for_invalid(): void
    {
        $this->assertTrue(OmrGradingService::isInvalidAnswer(['invalid' => true, 'raw' => 'xyz']));
    }

    public function test_is_invalid_answer_false_for_int(): void
    {
        $this->assertFalse(OmrGradingService::isInvalidAnswer(0));
    }

    public function test_is_invalid_answer_false_for_null(): void
    {
        $this->assertFalse(OmrGradingService::isInvalidAnswer(null));
    }

    // ================================================================
    // Cenários com 2 opções apenas (A, B) — questão mista
    // ================================================================

    public function test_mc_2_options_a_normalizes_to_0(): void
    {
        $this->assertSame(0, OmrGradingService::normalizeAnswer('A', 'multiple_choice', ['Sim', 'Não']));
    }

    public function test_mc_2_options_b_normalizes_to_1(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer('B', 'multiple_choice', ['Sim', 'Não']));
    }

    public function test_mc_2_options_c_returns_invalid(): void
    {
        $result = OmrGradingService::normalizeAnswer('C', 'multiple_choice', ['Sim', 'Não']);
        $this->assertTrue(OmrGradingService::isInvalidAnswer($result));
    }
}
