<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCopy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamPrintService
{
    public const OUTPUT_TYPES = ['exam', 'answer_sheet', 'both', 'answer_key'];

    /**
     * Gera cópias completas da prova e persiste os mapas de questões e alternativas.
     *
     * @param array{
     *   shuffle_questions?: bool,
     *   shuffle_options_mc?: bool,
     *   shuffle_options_tf?: bool,
     *   group_disciplines?: bool,
     *   shuffle_disciplines?: bool,
     *   output_type?: string,
     *   card_template_id?: int|null,
     *   card_template_version?: int|null,
     *   template_snapshot?: array<string, mixed>|null
     * } $options
     * @param  array<int, int>  $studentIds
     */
    public function generateCopies(
        Exam $exam,
        int $quantity,
        array $options = [],
        ?int $schoolClassId = null,
        array $studentIds = [],
    ): Collection {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        sort($studentIds, SORT_NUMERIC);
        if ($studentIds !== []) {
            if (in_array(0, $studentIds, true)) {
                throw new \InvalidArgumentException('Os alunos das cópias devem possuir identificadores válidos.');
            }
            $quantity = count($studentIds);
        }
        if ($quantity < 1 || $quantity > 1000) {
            throw new \InvalidArgumentException('A quantidade de cópias deve estar entre 1 e 1000.');
        }

        $outputType = (string) ($options['output_type'] ?? 'both');
        if (! in_array($outputType, self::OUTPUT_TYPES, true)) {
            throw new \InvalidArgumentException('O tipo de saída informado não é suportado.');
        }

        return DB::transaction(function () use (
            $exam,
            $quantity,
            $options,
            $schoolClassId,
            $studentIds,
            $outputType,
        ): Collection {
            $lockedExam = Exam::withoutGlobalScopes()->lockForUpdate()->findOrFail($exam->id);
            $lockedExam->load(['questions.discipline']);
            $questions = $lockedExam->questions;
            if ($questions->isEmpty()) {
                throw new \RuntimeException('Não é possível gerar uma versão impressa sem questões.');
            }

            $shuffleQuestions = (bool) ($options['shuffle_questions'] ?? false);
            $shuffleMc = (bool) ($options['shuffle_options_mc'] ?? false);
            $shuffleTf = (bool) ($options['shuffle_options_tf'] ?? false);
            $groupDisciplines = (bool) ($options['group_disciplines'] ?? true);
            $shuffleDisciplines = (bool) ($options['shuffle_disciplines'] ?? false);
            $blocks = [];
            if ($groupDisciplines) {
                foreach ($questions as $question) {
                    $disciplineKey = $question->discipline_id ?: 'no_discipline';
                    $blocks[$disciplineKey] ??= [];
                    $blocks[$disciplineKey][] = $question;
                }
            } else {
                $blocks['all'] = $questions->all();
            }

            $snapshot = $this->questionSnapshot($questions);
            $latestSnapshot = $lockedExam->copies()
                ->whereNotNull('question_snapshot')
                ->latest('id')
                ->first();
            $examVersion = max(1, (int) $lockedExam->version);
            if ($latestSnapshot && $this->snapshotHash($latestSnapshot->question_snapshot) !== $this->snapshotHash($snapshot)) {
                $examVersion = max($examVersion, (int) $latestSnapshot->exam_version) + 1;
                $lockedExam->forceFill(['version' => $examVersion])->save();
            }

            $firstCopyNumber = ((int) $lockedExam->copies()->max('copy_number')) + 1;
            $generationUuid = (string) Str::uuid();
            $copies = [];

            for ($offset = 0; $offset < $quantity; $offset++) {
                $copyNumber = $firstCopyNumber + $offset;
                $blockKeys = array_keys($blocks);

                if ($groupDisciplines && $shuffleDisciplines) {
                    $noDisciplineIndex = array_search('no_discipline', $blockKeys, true);
                    $hasNoDiscipline = $noDisciplineIndex !== false;

                    if ($hasNoDiscipline) {
                        unset($blockKeys[$noDisciplineIndex]);
                        $blockKeys = array_values($blockKeys);
                    }

                    shuffle($blockKeys);

                    if ($hasNoDiscipline) {
                        $blockKeys[] = 'no_discipline';
                    }
                }

                $questionIds = [];
                foreach ($blockKeys as $blockKey) {
                    $blockQuestions = $blocks[$blockKey];
                    if ($shuffleQuestions) {
                        shuffle($blockQuestions);
                    }

                    foreach ($blockQuestions as $question) {
                        $questionIds[] = $question->id;
                    }
                }

                $optionsMap = [];
                foreach ($questions as $question) {
                    if ($question->type === 'multiple_choice') {
                        $optionCount = count($question->content['options'] ?? []);
                        $indices = $optionCount > 0 ? range(0, $optionCount - 1) : [];
                        if ($shuffleMc) {
                            shuffle($indices);
                        }
                        $optionsMap[$question->id] = $indices;
                    } elseif ($question->type === 'true_false') {
                        // Convenção do banco de questões: 0=Verdadeiro e 1=Falso.
                        $indices = [0, 1];
                        if ($shuffleTf) {
                            shuffle($indices);
                        }
                        $optionsMap[$question->id] = $indices;
                    } else {
                        $optionsMap[$question->id] = null;
                    }
                }

                $copies[] = ExamCopy::create([
                    'exam_id' => $lockedExam->id,
                    'school_class_id' => $schoolClassId,
                    'student_id' => $studentIds[$offset] ?? null,
                    'generation_uuid' => $generationUuid,
                    'copy_number' => $copyNumber,
                    'exam_version' => $examVersion,
                    'card_template_id' => $options['card_template_id'] ?? null,
                    'card_template_version' => $options['card_template_version'] ?? null,
                    'output_type' => $outputType,
                    'template_snapshot' => $options['template_snapshot'] ?? null,
                    'questions_map' => $questionIds,
                    'options_map' => $optionsMap,
                    'question_snapshot' => $snapshot,
                    // Identificador opaco; a autenticidade do cartão é garantida pelo HMAC.
                    'validation_hash' => Str::random(40),
                ]);
            }

            return new Collection($copies);
        }, 3);
    }

    /** @return array<int, array<string, mixed>> */
    private function questionSnapshot(Collection $questions): array
    {
        return $questions->values()->map(fn ($question, int $index): array => [
            'id' => (int) $question->id,
            'type' => (string) $question->type,
            'content' => $question->content,
            'points' => (float) ($question->pivot->points ?? 0),
            'order' => (int) ($question->pivot->order ?? $index + 1),
            'discipline_id' => $question->discipline_id ? (int) $question->discipline_id : null,
            'discipline_name' => $question->discipline?->name,
        ])->all();
    }

    /** @param array<int, array<string, mixed>>|null $snapshot */
    private function snapshotHash(?array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
