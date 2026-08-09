<?php

namespace App\Services;

use App\Models\Revision;
use App\Models\RevisionImport;
use App\Models\RevisionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class RevisionImportService
{
    public function import(Revision $revision, User $user, string $json, string $mode = 'append'): RevisionImport
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages(['json' => 'JSON inválido: '.$exception->getMessage()]);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['json' => 'O JSON precisa conter um objeto com schema_version e items.']);
        }

        $validator = Validator::make($payload, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'title' => ['nullable', 'string', 'max:180'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.type' => ['required', 'string', 'in:'.implode(',', array_slice(RevisionItem::TYPES, 0, 7))],
            'items.*.prompt' => ['required', 'string', 'max:10000'],
            'items.*.content' => ['nullable', 'array'],
            'items.*.solution' => ['required', 'array'],
            'items.*.explanation' => ['nullable', 'string', 'max:10000'],
            'items.*.hints' => ['nullable', 'array', 'max:10'],
            'items.*.hints.*' => ['string', 'max:500'],
            'items.*.difficulty' => ['nullable', 'integer', 'between:1,5'],
            'items.*.points' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $validator->after(function ($validator) use ($payload): void {
            foreach (($payload['items'] ?? []) as $index => $item) {
                foreach ($this->semanticErrors($item) as $error) {
                    $validator->errors()->add("items.{$index}", $error);
                }
            }
        });

        if ($validator->fails()) {
            RevisionImport::create([
                'revision_id' => $revision->id,
                'imported_by' => $user->id,
                'schema_version' => (int) ($payload['schema_version'] ?? 0),
                'payload' => $payload,
                'status' => 'rejected',
                'validation_errors' => $validator->errors()->toArray(),
            ]);
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($revision, $user, $payload, $mode): RevisionImport {
            if ($mode === 'replace') {
                $revision->items()->delete();
            }
            $order = (int) ($revision->items()->max('order') ?? -1) + 1;
            foreach ($payload['items'] as $item) {
                $content = $item['content'] ?? [];
                unset($content['resources']);
                $revision->items()->create([
                    'type' => $item['type'], 'order' => $order++, 'difficulty' => $item['difficulty'] ?? 1,
                    'prompt' => trim($item['prompt']), 'content' => $content, 'solution' => $item['solution'],
                    'explanation' => $item['explanation'] ?? null, 'hints' => $item['hints'] ?? [],
                    'points' => $item['points'] ?? 1, 'updated_by' => $user->id,
                ]);
            }
            if (filled($payload['title'] ?? null)) {
                $revision->update(['title' => $payload['title']]);
            }

            return RevisionImport::create([
                'revision_id' => $revision->id, 'imported_by' => $user->id, 'schema_version' => 1,
                'payload' => $payload, 'items_imported' => count($payload['items']), 'status' => 'imported',
            ]);
        });
    }

    private function semanticErrors(array $item): array
    {
        $type = $item['type'] ?? null;
        $content = $item['content'] ?? [];
        $solution = $item['solution'] ?? [];
        $options = $content['options'] ?? [];
        $correctOption = $solution['correct_option'] ?? null;

        return match ($type) {
            'multiple_choice' => count($options) < 2 || ! is_numeric($correctOption)
                || (int) $correctOption < 0 || (int) $correctOption >= count($options)
                ? ['Múltipla escolha requer ao menos duas opções e correct_option válido.'] : [],
            'true_false' => ! in_array((string) $correctOption, ['0', '1'], true) ? ['Verdadeiro/falso requer correct_option (0 ou 1).'] : [],
            'matching' => count($content['left'] ?? []) < 2 || count($content['right'] ?? []) < 2 || empty($solution['pairs']) ? ['Associação requer listas left/right e pares no gabarito.'] : [],
            'fill_blank', 'short_answer' => empty($solution['accepted_answers']) ? ['Este tipo requer accepted_answers.'] : [],
            'ordering' => count($content['items'] ?? []) < 2 || empty($solution['order']) ? ['Ordenação requer items e order.'] : [],
            'flashcard' => blank($content['front'] ?? null) || blank($content['back'] ?? null) ? ['Flashcard requer front e back.'] : [],
            default => [],
        };
    }
}
