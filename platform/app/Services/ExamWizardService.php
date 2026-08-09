<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExamWizardService
{
    public const VERSION = 1;

    public const STEPS = [
        'information' => 'Informações',
        'questions' => 'Questões',
        'audience' => 'Público',
        'application' => 'Aplicação',
        'appearance' => 'Aparência',
        'answer_sheet' => 'Cartão-resposta',
        'preview' => 'Pré-visualização',
        'publication' => 'Publicação',
    ];

    /** @return array{version: int, current_step: string, completed_steps: array<int, string>, revision: int, updated_at: ?string} */
    public function state(Exam $exam): array
    {
        $stored = data_get($exam->settings, '_wizard', []);
        $currentStep = is_string($stored['current_step'] ?? null)
            && array_key_exists($stored['current_step'], self::STEPS)
                ? $stored['current_step']
                : 'information';
        $completed = collect($stored['completed_steps'] ?? [])
            ->filter(fn ($step) => is_string($step) && array_key_exists($step, self::STEPS))
            ->unique()
            ->values()
            ->all();

        return [
            'version' => self::VERSION,
            'current_step' => $currentStep,
            'completed_steps' => $completed,
            'revision' => max(0, (int) ($stored['revision'] ?? 0)),
            'updated_at' => is_string($stored['updated_at'] ?? null) ? $stored['updated_at'] : null,
        ];
    }

    public function initialize(Exam $exam, string $currentStep = 'questions'): void
    {
        $settings = is_array($exam->settings) ? $exam->settings : [];
        $settings['_wizard'] = [
            'version' => self::VERSION,
            'current_step' => $currentStep,
            'completed_steps' => ['information'],
            'revision' => 1,
            'updated_at' => now()->toIso8601String(),
        ];
        $exam->update(['settings' => $settings]);
    }

    /**
     * Incorpora um envio de formulário tradicional ao mesmo controle de revisão do autosave.
     * O chamador deve manter o registro bloqueado durante a atualização.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function prepareSettingsForUpdate(
        Exam $exam,
        array $settings,
        ?int $expectedRevision,
        ?string $currentStep,
        bool $completePublication,
    ): array {
        $state = $this->state($exam);

        if ($expectedRevision !== null && $expectedRevision !== $state['revision']) {
            throw ValidationException::withMessages([
                'wizard_revision' => 'Este rascunho foi atualizado em outra aba. Recarregue a página antes de continuar.',
            ])->status(409);
        }

        $step = is_string($currentStep) && array_key_exists($currentStep, self::STEPS)
            ? $currentStep
            : $state['current_step'];
        $completed = collect($state['completed_steps']);
        if ($completePublication) {
            $completed->push('publication');
            $step = 'publication';
        }

        $settings['_wizard'] = [
            'version' => self::VERSION,
            'current_step' => $step,
            'completed_steps' => $completed->unique()->values()->all(),
            'revision' => $state['revision'] + 1,
            'updated_at' => now()->toIso8601String(),
        ];

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{version: int, current_step: string, completed_steps: array<int, string>, revision: int, updated_at: ?string}
     */
    public function autosave(
        Exam $exam,
        User $actor,
        string $step,
        array $payload,
        int $expectedRevision,
        bool $complete = false,
        ?string $targetStep = null,
    ): array {
        abort_unless((int) $exam->organization_id === (int) $actor->organization_id, 403);
        abort_unless((int) $exam->author_id === (int) $actor->id, 404);

        $validated = Validator::make($payload, $this->rulesFor($step))->validate();

        return DB::transaction(function () use (
            $exam,
            $actor,
            $step,
            $validated,
            $expectedRevision,
            $complete,
            $targetStep,
        ): array {
            $locked = Exam::withoutGlobalScopes()
                ->where('organization_id', $exam->organization_id)
                ->where('author_id', $actor->id)
                ->lockForUpdate()
                ->findOrFail($exam->id);
            abort_if($locked->status !== 'draft', 409, 'Somente avaliações em rascunho podem ser salvas automaticamente.');
            $state = $this->state($locked);

            if ($expectedRevision !== $state['revision']) {
                throw ValidationException::withMessages([
                    'revision' => 'Este rascunho foi atualizado em outra aba. Recarregue a página antes de continuar.',
                ])->status(409);
            }

            $settings = is_array($locked->settings) ? $locked->settings : [];
            $this->applyStep($locked, $settings, $step, $validated);

            if ($complete) {
                $this->validateCompletion($locked, $step, $settings);
            }

            $completed = collect($state['completed_steps']);
            if ($complete) {
                $completed->push($step);
            }

            $settings['_wizard'] = [
                'version' => self::VERSION,
                'current_step' => $targetStep ?? $step,
                'completed_steps' => $completed->unique()->values()->all(),
                'revision' => $state['revision'] + 1,
                'updated_at' => now()->toIso8601String(),
            ];
            $locked->settings = $settings;
            $locked->save();

            if ($complete) {
                AuditLog::log('exam_wizard_step_saved', Exam::class, $locked->id, [
                    'step' => $step,
                    'wizard_version' => self::VERSION,
                    'revision' => $state['revision'] + 1,
                    'first_completion' => ! in_array($step, $state['completed_steps'], true),
                ]);
            }

            return $this->state($locked);
        });
    }

    /** @return array<string, mixed> */
    private function rulesFor(string $step): array
    {
        abort_unless(array_key_exists($step, self::STEPS), 422, 'Etapa de Avaliação inválida.');

        return match ($step) {
            'information' => [
                'title' => ['required', 'string', 'max:255'],
                'instructions' => ['nullable', 'string', 'max:10000'],
            ],
            'application' => [
                'application_mode' => ['required', Rule::in(array_keys(ExamApplicationModeService::MODES))],
                'time_limit' => ['nullable', 'integer', 'min:1', 'max:1440'],
                'attempts' => ['required', 'integer', 'min:1', 'max:20'],
                'digital_presentation' => ['sometimes', Rule::in(ExamPresentationService::MODES)],
                'questions_per_page' => ['sometimes', 'integer', 'min:1', 'max:20'],
                'available_from' => ['nullable', 'date'],
                'available_until' => ['nullable', 'date', 'after:available_from'],
            ],
            'appearance' => [
                'shuffle_questions' => ['required', 'boolean'],
                'shuffle_options' => ['required', 'boolean'],
            ],
            'publication' => [
                'show_score' => ['required', 'boolean'],
                'show_answers' => ['required', 'boolean'],
                'show_feedback' => ['required', 'boolean'],
                'results_available_from' => ['nullable', 'date'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $validated
     */
    private function applyStep(Exam $exam, array &$settings, string $step, array $validated): void
    {
        if ($step === 'information') {
            $exam->title = trim($validated['title']);
            $settings['instructions'] = filled($validated['instructions'] ?? null)
                ? trim((string) $validated['instructions'])
                : null;

            return;
        }

        if ($step === 'application') {
            $settings['application_mode'] = $validated['application_mode'];
            $settings['time_limit'] = isset($validated['time_limit']) ? (int) $validated['time_limit'] : null;
            $settings['attempts'] = (int) $validated['attempts'];
            $settings['digital_presentation'] = $validated['digital_presentation']
                ?? $settings['digital_presentation']
                ?? 'auto';
            $settings['questions_per_page'] = (int) (
                $validated['questions_per_page']
                ?? $settings['questions_per_page']
                ?? 5
            );
            $settings['available_from'] = $this->normalizedDate($validated['available_from'] ?? null);
            $settings['available_until'] = $this->normalizedDate($validated['available_until'] ?? null);

            return;
        }

        if ($step === 'appearance') {
            $settings['shuffle_questions'] = (bool) $validated['shuffle_questions'];
            $settings['shuffle_options'] = (bool) $validated['shuffle_options'];

            return;
        }

        if ($step === 'publication') {
            $settings['show_score'] = (bool) $validated['show_score'];
            $settings['show_answers'] = (bool) $validated['show_answers'];
            $settings['show_feedback'] = (bool) $validated['show_feedback'];
            $settings['results_available_from'] = $this->normalizedDate($validated['results_available_from'] ?? null);
        }
    }

    /** @param array<string, mixed> $settings */
    private function validateCompletion(Exam $exam, string $step, array $settings): void
    {
        if (in_array($step, ['questions', 'answer_sheet', 'preview'], true)) {
            if (! $exam->questions()->exists()) {
                throw ValidationException::withMessages([
                    'questions' => 'Adicione ao menos uma questão antes de concluir esta etapa.',
                ]);
            }

            if ((float) $exam->questions()->sum('exam_questions.points') <= 0) {
                throw ValidationException::withMessages([
                    'questions' => 'A pontuação total deve ser maior que zero antes de concluir esta etapa.',
                ]);
            }
        }

        if ($step === 'publication') {
            Validator::make($settings, [
                'show_score' => ['required', 'boolean'],
                'show_answers' => ['required', 'boolean'],
                'show_feedback' => ['required', 'boolean'],
                'results_available_from' => ['nullable', 'date'],
            ])->validate();
        }

        // Público vazio é uma escolha válida para rascunhos sem destinatário imediato.
    }

    private function normalizedDate(mixed $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toIso8601String() : null;
    }
}
