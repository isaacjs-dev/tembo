<?php

namespace Tests\Feature\Services;

use App\Services\OmrGradingService;
use Tests\TestCase;

class OmrGradingServiceTest extends TestCase
{
    /**
     * Test normalization of true_false answers.
     */
    public function test_normalize_true_false_answers()
    {
        // Verdadeiros
        $this->assertEquals(1, OmrGradingService::normalizeAnswer(true, 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('true', 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('1', 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer(1, 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('v', 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('V', 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('t', 'true_false'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('T', 'true_false'));

        // Falsos
        $this->assertEquals(0, OmrGradingService::normalizeAnswer(false, 'true_false'));
        $this->assertEquals(0, OmrGradingService::normalizeAnswer('false', 'true_false'));
        $this->assertEquals(0, OmrGradingService::normalizeAnswer('0', 'true_false'));
        $this->assertEquals(0, OmrGradingService::normalizeAnswer(0, 'true_false'));
        $this->assertEquals(0, OmrGradingService::normalizeAnswer('f', 'true_false'));
        $this->assertEquals(0, OmrGradingService::normalizeAnswer('F', 'true_false'));
    }

    /**
     * Test normalization of multiple_choice answers.
     */
    public function test_normalize_multiple_choice_answers()
    {
        // Convert letter to index (Contexto padrão = 5 opções (A-E))
        $this->assertEquals(0, OmrGradingService::normalizeAnswer('A', 'multiple_choice'));
        $this->assertEquals(1, OmrGradingService::normalizeAnswer('B', 'multiple_choice'));
        $this->assertEquals(2, OmrGradingService::normalizeAnswer('C', 'multiple_choice'));
        $this->assertEquals(3, OmrGradingService::normalizeAnswer('D', 'multiple_choice'));
        $this->assertEquals(4, OmrGradingService::normalizeAnswer('E', 'multiple_choice'));

        // Case insensitivity and trim
        $this->assertEquals(0, OmrGradingService::normalizeAnswer(' a ', 'multiple_choice'));

        // Integer inputs should stay as integer indices
        $this->assertEquals(0, OmrGradingService::normalizeAnswer(0, 'multiple_choice'));
        $this->assertEquals(4, OmrGradingService::normalizeAnswer(4, 'multiple_choice'));
        $this->assertEquals(2, OmrGradingService::normalizeAnswer('2', 'multiple_choice'));

        // Invalid options
        $this->assertTrue(is_array(OmrGradingService::normalizeAnswer('Z', 'multiple_choice')));
        $this->assertTrue(is_array(OmrGradingService::normalizeAnswer(-1, 'multiple_choice')));
        $this->assertTrue(is_array(OmrGradingService::normalizeAnswer(5, 'multiple_choice'))); // Fora de bounds no default de 5
    }

    /**
     * Test handling of empty/null values.
     */
    public function test_normalize_empty_answers()
    {
        $this->assertNull(OmrGradingService::normalizeAnswer(null, 'multiple_choice'));
        $this->assertNull(OmrGradingService::normalizeAnswer('', 'true_false'));
        $this->assertNull(OmrGradingService::normalizeAnswer('—', 'multiple_choice')); // Travessão da dashboard
    }
}
