<?php

namespace App\Services;

use DomainException;

class AppearanceDefinitionSchema
{
    /** @return array<string, array<string, mixed>> */
    public function normalizeAssets(array $assets): array
    {
        if (count($assets) > 10) {
            throw new DomainException('A definição excede o limite de assets.');
        }

        $normalized = [];
        foreach ($assets as $key => $asset) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $key)) {
                throw new DomainException('Identificador de asset inválido.');
            }
            $asset = $this->array($asset, 'asset');
            $this->onlyKeys($asset, ['storage_disk', 'storage_path', 'mime_type', 'size_bytes', 'sha256']);
            $path = (string) ($asset['storage_path'] ?? '');
            $mime = (string) ($asset['mime_type'] ?? '');
            $hash = (string) ($asset['sha256'] ?? '');
            $size = (int) ($asset['size_bytes'] ?? 0);
            if ($path === '' || ! preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]{0,499}$#', $path)
                || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")
                || ! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)
                || $size < 1 || $size > 5 * 1024 * 1024
                || ! preg_match('/^[a-f0-9]{64}$/', $hash)
                || ! in_array($asset['storage_disk'] ?? null, ['local', 'public'], true)) {
                throw new DomainException('Asset de aparência fora do envelope seguro.');
            }
            $normalized[$key] = [
                'storage_disk' => $asset['storage_disk'],
                'storage_path' => $path,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'sha256' => $hash,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /** @return array<string, mixed> */
    public function normalize(string $kind, array $definition): array
    {
        return match ($kind) {
            'assessment_layout' => $this->layout($definition),
            'assessment_header' => $this->header($definition, false),
            'answer_sheet_header' => $this->header($definition, true),
            default => throw new DomainException('Contexto de aparência desconhecido.'),
        };
    }

    /** @return array<string, mixed> */
    private function layout(array $definition): array
    {
        $this->onlyKeys($definition, ['page', 'questions']);
        $page = $this->array($definition['page'] ?? null, 'page');
        $questions = $this->array($definition['questions'] ?? null, 'questions');
        $this->onlyKeys($page, ['size', 'orientation', 'margins_mm']);
        $this->onlyKeys($questions, ['columns', 'separator', 'avoid_break_inside']);
        $margins = array_values($this->array($page['margins_mm'] ?? null, 'margins_mm'));
        if (count($margins) !== 4 || collect($margins)->contains(fn (mixed $value): bool => ! is_numeric($value) || (float) $value < 5 || (float) $value > 30)) {
            throw new DomainException('Margens devem conter quatro valores entre 5 e 30 mm.');
        }
        if (($page['size'] ?? null) !== 'A4'
            || ! in_array($page['orientation'] ?? null, ['portrait', 'landscape'], true)
            || ! in_array((int) ($questions['columns'] ?? 0), [1, 2], true)
            || ! in_array($questions['separator'] ?? null, ['line', 'box', 'none'], true)
            || ! is_bool($questions['avoid_break_inside'] ?? null)) {
            throw new DomainException('Definição de layout fora do envelope suportado.');
        }

        return [
            'page' => [
                'size' => 'A4',
                'orientation' => $page['orientation'],
                'margins_mm' => array_map('floatval', $margins),
            ],
            'questions' => [
                'columns' => (int) $questions['columns'],
                'separator' => $questions['separator'],
                'avoid_break_inside' => $questions['avoid_break_inside'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function header(array $definition, bool $answerSheet): array
    {
        $allowed = $answerSheet ? ['height_mm', 'elements', 'qr_slot', 'fields'] : ['height_mm', 'elements'];
        $this->onlyKeys($definition, $allowed);
        $height = (float) ($definition['height_mm'] ?? 0);
        if ($height < 10 || $height > 80) {
            throw new DomainException('Altura do cabeçalho fora do envelope suportado.');
        }
        $elements = $definition['elements'] ?? [];
        if (! is_array($elements) || count($elements) > 30) {
            throw new DomainException('Lista de elementos do cabeçalho inválida.');
        }
        $normalized = collect($elements)->map(function (mixed $element): array {
            $element = $this->array($element, 'element');
            $this->onlyKeys($element, ['type', 'token', 'text']);
            $type = (string) ($element['type'] ?? '');
            if ($type === 'line') {
                return ['type' => 'line'];
            }
            if (! in_array($type, ['text', 'field'], true)) {
                throw new DomainException('Tipo de elemento de cabeçalho não autorizado.');
            }
            $token = isset($element['token']) ? (string) $element['token'] : null;
            $text = isset($element['text']) ? (string) $element['text'] : null;
            if ($token !== null && ! in_array($token, AppearanceTokenResolver::TOKENS, true)) {
                throw new DomainException("Token de aparência não autorizado: {$token}.");
            }
            if ($text !== null) {
                if (mb_strlen($text) > 500) {
                    throw new DomainException('Texto de cabeçalho excede 500 caracteres.');
                }
                preg_match_all('/\{\{\s*([a-z][a-z0-9_.]*)\s*\}\}/i', $text, $matches);
                foreach ($matches[1] ?? [] as $inlineToken) {
                    if (! in_array($inlineToken, AppearanceTokenResolver::TOKENS, true)) {
                        throw new DomainException("Token de aparência não autorizado: {$inlineToken}.");
                    }
                }
            }
            if (($token === null) === ($text === null)) {
                throw new DomainException('Elemento deve possuir exatamente um token ou texto.');
            }

            return array_filter(['type' => $type, 'token' => $token, 'text' => $text], fn (mixed $value): bool => $value !== null);
        })->values()->all();

        $result = ['height_mm' => $height, 'elements' => $normalized];
        if ($answerSheet) {
            $qr = $this->array($definition['qr_slot'] ?? null, 'qr_slot');
            $this->onlyKeys($qr, ['position', 'width_mm', 'quiet_zone_mm']);
            if (! in_array($qr['position'] ?? null, ['top_left', 'top_right'], true)
                || ! is_numeric($qr['width_mm'] ?? null) || (float) $qr['width_mm'] < 20 || (float) $qr['width_mm'] > 45
                || ! is_numeric($qr['quiet_zone_mm'] ?? null) || (float) $qr['quiet_zone_mm'] < 3 || (float) $qr['quiet_zone_mm'] > 8) {
                throw new DomainException('Área segura do QR inválida.');
            }
            $fields = $this->array($definition['fields'] ?? null, 'fields');
            if (count($fields) > 12 || collect($fields)->contains(fn (mixed $token): bool => ! is_string($token) || ! in_array($token, AppearanceTokenResolver::TOKENS, true))) {
                throw new DomainException('Campos do cabeçalho do cartão inválidos.');
            }
            $result['qr_slot'] = [
                'position' => $qr['position'],
                'width_mm' => (float) $qr['width_mm'],
                'quiet_zone_mm' => (float) $qr['quiet_zone_mm'],
            ];
            $result['fields'] = array_values($fields);
        }

        return $result;
    }

    private function onlyKeys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new DomainException('A definição contém propriedades não autorizadas.');
        }
    }

    private function array(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new DomainException("{$field} deve ser um objeto ou lista.");
        }

        return $value;
    }
}
