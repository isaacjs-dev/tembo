<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCopy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamPrintService
{
    /**
     * Gera cópias completas da prova e persiste os mapas de questões e alternativas.
     *
     * @param array{
     *   shuffle_questions?: bool,
     *   shuffle_options_mc?: bool,
     *   shuffle_options_tf?: bool,
     *   group_disciplines?: bool,
     *   shuffle_disciplines?: bool
     * } $options
     */
    public function generateCopies(
        Exam $exam,
        int $quantity,
        array $options = [],
        ?int $schoolClassId = null
    ): Collection {
        if ($quantity < 1 || $quantity > 1000) {
            throw new \InvalidArgumentException('A quantidade de cópias deve estar entre 1 e 1000.');
        }

        $shuffleQuestions = (bool) ($options['shuffle_questions'] ?? false);
        $shuffleMc = (bool) ($options['shuffle_options_mc'] ?? false);
        $shuffleTf = (bool) ($options['shuffle_options_tf'] ?? false);
        $groupDisciplines = (bool) ($options['group_disciplines'] ?? true);
        $shuffleDisciplines = (bool) ($options['shuffle_disciplines'] ?? false);

        $exam->loadMissing('questions.discipline');
        $questions = $exam->questions;
        if ($questions->isEmpty()) {
            throw new \RuntimeException('Não é possível gerar uma versão impressa sem questões.');
        }

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

        return DB::transaction(function () use (
            $blocks,
            $exam,
            $groupDisciplines,
            $quantity,
            $questions,
            $schoolClassId,
            $shuffleDisciplines,
            $shuffleMc,
            $shuffleQuestions,
            $shuffleTf
        ): Collection {
            $copies = [];

            for ($copyNumber = 1; $copyNumber <= $quantity; $copyNumber++) {
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
                    'exam_id' => $exam->id,
                    'school_class_id' => $schoolClassId,
                    'copy_number' => $copyNumber,
                    'questions_map' => $questionIds,
                    'options_map' => $optionsMap,
                    // Identificador opaco; a autenticidade do cartão é garantida pelo HMAC.
                    'validation_hash' => Str::random(40),
                ]);
            }

            return new Collection($copies);
        }, 3);
    }
}
