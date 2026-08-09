<?php

namespace App\Services;

use App\Models\Revision;

class RevisionPromptService
{
    public function build(Revision $revision, array $counts): string
    {
        $revision->loadMissing(['items', 'discipline', 'sources.source']);
        $requested = collect([
            'multiple_choice' => (int) ($counts['multiple_choice'] ?? 0),
            'true_false' => (int) ($counts['true_false'] ?? 0),
            'matching' => (int) ($counts['matching'] ?? 0),
            'fill_blank' => (int) ($counts['fill_blank'] ?? 0),
            'ordering' => (int) ($counts['ordering'] ?? 0),
            'flashcard' => (int) ($counts['flashcard'] ?? 0),
            'short_answer' => (int) ($counts['short_answer'] ?? 0),
        ])->filter()->all();

        $context = [
            'title' => $revision->title,
            'description' => $revision->description,
            'discipline' => $revision->discipline?->name,
            'existing_items' => $revision->items->map(fn ($item) => [
                'type' => $item->type,
                'prompt' => $item->prompt,
                'content' => $item->content,
            ])->values()->all(),
        ];

        $schema = [
            'schema_version' => 1,
            'title' => 'string',
            'items' => [[
                'type' => 'multiple_choice|true_false|matching|fill_blank|ordering|flashcard|short_answer',
                'prompt' => 'string',
                'content' => ['options/pairs/items/front/back conforme o tipo'],
                'solution' => ['correct_option/answer/pairs/order/accepted_answers conforme o tipo'],
                'explanation' => 'string',
                'hints' => ['string'],
                'difficulty' => '1..5',
                'points' => 'number >= 0',
            ]],
        ];

        return implode("\n\n", [
            'Você é um assistente pedagógico. Crie exercícios de revisão claros, inclusivos e adequados ao contexto fornecido.',
            'Não inclua dados pessoais de alunos. Retorne SOMENTE JSON válido, sem markdown, comentários ou texto antes/depois.',
            'Quantidades solicitadas: '.json_encode($requested, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Contexto pedagógico: '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Formato obrigatório: '.json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
