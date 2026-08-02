<?php

namespace Tests\Unit;

use App\Services\OmrGradingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OmrGradingService::normalizeAnswer()
 *
 * Tests every input variant the system may encounter:
 * - Frontend forms (strings "1", "0", "true", "false")
 * - Mobile app (integers 0..4, booleans)
 * - Legacy data ("V", "F", "T", "verdadeiro", etc.)
 * - Edge cases (null, empty, invalid)
 */
class OmrNormalizeAnswerTest extends TestCase
{
    // ================================================================
    // TRUE/FALSE — Truthy values → 1
    // ================================================================

    #[DataProvider('trueFalseTruthyProvider')]
    public function test_true_false_truthy_values_normalize_to_1(mixed $input): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'true_false');
        $this->assertSame(1, $result, 'Expected 1 for truthy input: '.var_export($input, true));
    }

    public static function trueFalseTruthyProvider(): array
    {
        return [
            'bool true' => [true],
            'string "true"' => ['true'],
            'string "TRUE"' => ['TRUE'],
            'string "True"' => ['True'],
            'string "1"' => ['1'],
            'int 1' => [1],
            'string "V"' => ['V'],
            'string "v"' => ['v'],
            'string "T"' => ['T'],
            'string "t"' => ['t'],
            'string "verdadeiro"' => ['verdadeiro'],
            'string "yes"' => ['yes'],
            'string "sim"' => ['sim'],
        ];
    }

    // ================================================================
    // TRUE/FALSE — Falsy values → 0
    // ================================================================

    #[DataProvider('trueFalseFalsyProvider')]
    public function test_true_false_falsy_values_normalize_to_0(mixed $input): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'true_false');
        $this->assertSame(0, $result, 'Expected 0 for falsy input: '.var_export($input, true));
    }

    public static function trueFalseFalsyProvider(): array
    {
        return [
            'bool false' => [false],
            'string "false"' => ['false'],
            'string "FALSE"' => ['FALSE'],
            'string "False"' => ['False'],
            'string "0"' => ['0'],
            'int 0' => [0],
            'string "F"' => ['F'],
            'string "f"' => ['f'],
            'string "falso"' => ['falso'],
            'string "no"' => ['no'],
            'string "não"' => ['não'],
            'string "nao"' => ['nao'],
        ];
    }

    // ================================================================
    // TRUE/FALSE — Blank/null → null
    // ================================================================

    #[DataProvider('blankProvider')]
    public function test_true_false_blank_values_normalize_to_null(mixed $input): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'true_false');
        $this->assertNull($result, 'Expected null for blank input: '.var_export($input, true));
    }

    public static function blankProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'dash' => ['—'],
        ];
    }

    // ================================================================
    // TRUE/FALSE — Invalid values → ['invalid' => true, ...]
    // ================================================================

    #[DataProvider('trueFalseInvalidProvider')]
    public function test_true_false_invalid_values_return_invalid_array(mixed $input): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'true_false');
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);
        $this->assertSame($input, $result['raw']);
    }

    public static function trueFalseInvalidProvider(): array
    {
        return [
            'string "xyz"' => ['xyz'],
            'string "maybe"' => ['maybe'],
            'string "2"' => ['2'],
            'int 5' => [5],
            'string "A"' => ['A'], // A is not a valid V/F answer
        ];
    }

    // ================================================================
    // MULTIPLE CHOICE — Integer indices → same int
    // ================================================================

    #[DataProvider('mcIntegerProvider')]
    public function test_mc_integer_indices_normalize_correctly(mixed $input, int $expected): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'multiple_choice');
        $this->assertSame($expected, $result);
    }

    public static function mcIntegerProvider(): array
    {
        return [
            'int 0' => [0, 0],
            'int 1' => [1, 1],
            'int 2' => [2, 2],
            'int 3' => [3, 3],
            'int 4' => [4, 4],
            'string "0"' => ['0', 0],
            'string "1"' => ['1', 1],
            'string "2"' => ['2', 2],
            'string "3"' => ['3', 3],
            'string "4"' => ['4', 4],
        ];
    }

    // ================================================================
    // MULTIPLE CHOICE — Letter values → index
    // ================================================================

    #[DataProvider('mcLetterProvider')]
    public function test_mc_letter_values_normalize_to_index(string $input, int $expected): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'multiple_choice');
        $this->assertSame($expected, $result);
    }

    public static function mcLetterProvider(): array
    {
        return [
            'A' => ['A', 0],
            'B' => ['B', 1],
            'C' => ['C', 2],
            'D' => ['D', 3],
            'E' => ['E', 4],
            'a (lowercase)' => ['a', 0],
            'e (lowercase)' => ['e', 4],
        ];
    }

    // ================================================================
    // MULTIPLE CHOICE — With custom options context
    // ================================================================

    public function test_mc_respects_options_count(): void
    {
        // Only 2 options → index 2 should be invalid
        $result = OmrGradingService::normalizeAnswer(2, 'multiple_choice', ['Sim', 'Não']);
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);

        // But index 1 is valid
        $result = OmrGradingService::normalizeAnswer(1, 'multiple_choice', ['Sim', 'Não']);
        $this->assertSame(1, $result);
    }

    public function test_mc_letter_beyond_options_is_invalid(): void
    {
        // Only 3 options (A, B, C) → D should be invalid
        $result = OmrGradingService::normalizeAnswer('D', 'multiple_choice', ['X', 'Y', 'Z']);
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);

        // C is valid (index 2)
        $result = OmrGradingService::normalizeAnswer('C', 'multiple_choice', ['X', 'Y', 'Z']);
        $this->assertSame(2, $result);
    }

    // ================================================================
    // MULTIPLE CHOICE — Blank → null
    // ================================================================

    #[DataProvider('blankProvider')]
    public function test_mc_blank_values_normalize_to_null(mixed $input): void
    {
        $result = OmrGradingService::normalizeAnswer($input, 'multiple_choice');
        $this->assertNull($result);
    }

    // ================================================================
    // MULTIPLE CHOICE — Invalid → ['invalid' => true, ...]
    // ================================================================

    public function test_mc_out_of_range_index_is_invalid(): void
    {
        $result = OmrGradingService::normalizeAnswer(5, 'multiple_choice');
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);
    }

    public function test_mc_negative_index_is_invalid(): void
    {
        // String "-1" is not ctype_digit, and length > 1 so not a letter
        $result = OmrGradingService::normalizeAnswer('-1', 'multiple_choice');
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);
    }

    public function test_mc_letter_f_beyond_5_options_is_invalid(): void
    {
        $result = OmrGradingService::normalizeAnswer('F', 'multiple_choice');
        $this->assertIsArray($result);
        $this->assertTrue($result['invalid']);
    }

    // ================================================================
    // Unknown question type → null
    // ================================================================

    public function test_unknown_question_type_returns_null(): void
    {
        $result = OmrGradingService::normalizeAnswer('anything', 'essay');
        $this->assertNull($result);

        $result = OmrGradingService::normalizeAnswer('anything', 'short_answer');
        $this->assertNull($result);
    }

    // ================================================================
    // isInvalidAnswer helper
    // ================================================================

    public function test_is_invalid_answer_detects_invalid_array(): void
    {
        $this->assertTrue(OmrGradingService::isInvalidAnswer(['invalid' => true, 'raw' => 'xyz']));
        $this->assertFalse(OmrGradingService::isInvalidAnswer(0));
        $this->assertFalse(OmrGradingService::isInvalidAnswer(1));
        $this->assertFalse(OmrGradingService::isInvalidAnswer(null));
        $this->assertFalse(OmrGradingService::isInvalidAnswer(['invalid' => false]));
    }

    // ================================================================
    // Whitespace handling
    // ================================================================

    public function test_whitespace_around_values_is_trimmed(): void
    {
        $this->assertSame(1, OmrGradingService::normalizeAnswer(' true ', 'true_false'));
        $this->assertSame(0, OmrGradingService::normalizeAnswer(' false ', 'true_false'));
        $this->assertSame(2, OmrGradingService::normalizeAnswer(' C ', 'multiple_choice'));
    }
}
