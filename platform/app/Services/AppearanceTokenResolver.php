<?php

namespace App\Services;

use DomainException;

class AppearanceTokenResolver
{
    public const TOKENS = [
        'student.name',
        'student.registration',
        'teacher.name',
        'institution.name',
        'class.name',
        'subject.name',
        'assessment.title',
        'assessment.subtitle',
        'assessment.date',
        'assessment.period',
        'assessment.copy_number',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'student.name' => 'Aluno',
        'student.registration' => 'Matrícula',
        'teacher.name' => 'Professor',
        'institution.name' => 'Instituição',
        'class.name' => 'Turma',
        'subject.name' => 'Disciplina',
        'assessment.title' => 'Avaliação',
        'assessment.subtitle' => 'Subtítulo',
        'assessment.date' => 'Data',
        'assessment.period' => 'Período',
        'assessment.copy_number' => 'Versão',
    ];

    /** @return array<int, array{type:string,value?:string,label?:string,token?:string}> */
    public function elements(array $headerDefinition, array $context): array
    {
        $elements = $headerDefinition['elements'] ?? [];
        if (! is_array($elements) || count($elements) > 30) {
            throw new DomainException('Definição de cabeçalho inválida.');
        }

        return collect($elements)->map(function (mixed $element) use ($context): array {
            if (! is_array($element)) {
                throw new DomainException('Elemento de cabeçalho inválido.');
            }
            $type = (string) ($element['type'] ?? '');
            if ($type === 'line') {
                return ['type' => 'line'];
            }
            if (! in_array($type, ['text', 'field'], true)) {
                throw new DomainException('Tipo de elemento de cabeçalho não autorizado.');
            }

            $token = isset($element['token']) ? (string) $element['token'] : null;
            $rawText = isset($element['text']) ? (string) $element['text'] : null;
            if ($token !== null) {
                $value = $this->token($token, $context);
            } elseif ($rawText !== null) {
                $value = $this->inline($rawText, $context);
            } else {
                throw new DomainException('Elemento sem token ou texto.');
            }

            return array_filter([
                'type' => $type,
                'token' => $token,
                'label' => $type === 'field' ? (self::LABELS[$token] ?? 'Campo') : null,
                'value' => $value,
            ], fn (mixed $item): bool => $item !== null);
        })->values()->all();
    }

    public function inline(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-z][a-z0-9_.]*)\s*\}\}/i', function (array $match) use ($context): string {
            return $this->token($match[1], $context);
        }, $text) ?? '';
    }

    public function token(string $token, array $context): string
    {
        if (! in_array($token, self::TOKENS, true)) {
            throw new DomainException("Token de aparência não autorizado: {$token}.");
        }

        $value = data_get($context, $token);
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return str_repeat('_', in_array($token, ['student.name', 'institution.name', 'assessment.title'], true) ? 34 : 16);
    }
}
