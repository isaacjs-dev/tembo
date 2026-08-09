<?php

namespace App\Services;

use App\Models\Exam;

class ExamApplicationModeService
{
    public const MODES = [
        'online' => '100% online',
        'printed_digital' => 'Avaliação impressa + resposta digital',
        'printed_omr' => 'Avaliação impressa + cartão-resposta OMR',
        'offline_omr' => 'OMR offline + sincronização posterior',
        // Contratos históricos continuam legíveis e editáveis durante a migração.
        'hybrid' => 'Híbrida (legado)',
        'paper' => 'Impressa (legado)',
    ];

    /** @return array<string, string> */
    public function options(): array
    {
        return self::MODES;
    }

    public function mode(Exam $exam): string
    {
        $mode = $exam->settings['application_mode'] ?? 'hybrid';

        return array_key_exists($mode, self::MODES) ? $mode : 'hybrid';
    }

    /** @return array{digital:bool,print:bool,omr:bool,offline:bool} */
    public function capabilities(Exam $exam): array
    {
        return match ($this->mode($exam)) {
            'online' => ['digital' => true, 'print' => false, 'omr' => false, 'offline' => false],
            'printed_digital' => ['digital' => true, 'print' => true, 'omr' => false, 'offline' => false],
            'printed_omr', 'paper' => ['digital' => false, 'print' => true, 'omr' => true, 'offline' => false],
            'offline_omr' => ['digital' => false, 'print' => true, 'omr' => true, 'offline' => true],
            default => ['digital' => true, 'print' => true, 'omr' => true, 'offline' => false],
        };
    }
}
