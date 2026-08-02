<?php

namespace App\Services;

use App\Models\ExamSubmission;
use App\Models\LearningMaterial;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LearningRecommendationService
{
    /**
     * Materiais publicados para as turmas do estudante, ordenados por aderência
     * às dificuldades observadas. Nenhum gabarito ou resposta é retornado.
     *
     * @param  array{search?: string|null, discipline_id?: int|string|null}  $filters
     */
    public function forStudent(
        User $student,
        array $filters = [],
        ?ExamSubmission $submission = null,
        int $perPage = 12,
    ): LengthAwarePaginator {
        $errors = $this->errorProfile($student, $submission);

        $query = LearningMaterial::query()
            ->where('organization_id', $student->organization_id)
            ->where('status', 'published')
            ->whereHas('schoolClasses.students', function ($query) use ($student): void {
                $query->where('users.id', $student->id);
            })
            ->with([
                'author:id,name',
                'discipline:id,name',
                'customSkill:id,name',
                'bnccNode:id,code,title',
                'progressRecords' => fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->select([
                        'id',
                        'learning_material_id',
                        'student_id',
                        'status',
                        'view_count',
                        'completed_at',
                    ]),
            ])
            ->distinct();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($disciplineId = $filters['discipline_id'] ?? null) {
            $query->where('discipline_id', (int) $disciplineId);
        }

        [$disciplineSql, $disciplineBindings] = $this->scoreCase(
            'learning_materials.discipline_id',
            $errors['disciplines'],
            4,
        );
        [$customSql, $customBindings] = $this->scoreCase(
            'learning_materials.custom_skill_id',
            $errors['custom_skills'],
            7,
        );
        [$bnccSql, $bnccBindings] = $this->scoreCase(
            'learning_materials.bncc_node_id',
            $errors['bncc_nodes'],
            7,
        );

        $materials = $query
            ->select('learning_materials.*')
            ->selectRaw(
                "({$disciplineSql} + {$customSql} + {$bnccSql}) AS recommendation_score",
                [...$disciplineBindings, ...$customBindings, ...$bnccBindings],
            )
            ->orderByDesc('recommendation_score')
            ->orderByDesc('learning_materials.created_at')
            ->orderBy('learning_materials.id')
            ->paginate($perPage)
            ->withQueryString();

        $materials->setCollection(
            $materials->getCollection()->map(
                fn (LearningMaterial $material): LearningMaterial => $this->explain($material, $errors)
            )
        );

        return $materials;
    }

    /**
     * @return array{disciplines: array<int, int>, custom_skills: array<int, int>, bncc_nodes: array<int, int>}
     */
    private function errorProfile(User $student, ?ExamSubmission $submission): array
    {
        $answerBase = DB::table('exam_answers')
            ->join('exam_submissions', 'exam_submissions.id', '=', 'exam_answers.exam_submission_id')
            ->join('exams', 'exams.id', '=', 'exam_submissions.exam_id')
            ->join('questions', 'questions.id', '=', 'exam_answers.question_id')
            ->where('exam_submissions.user_id', $student->id)
            ->where('exams.organization_id', $student->organization_id)
            ->where('exam_answers.is_correct', false);

        if ($submission) {
            $answerBase->where('exam_submissions.id', $submission->id);
        }

        $disciplines = (clone $answerBase)
            ->whereNotNull('questions.discipline_id')
            ->groupBy('questions.discipline_id')
            ->selectRaw('questions.discipline_id AS topic_id, COUNT(*) AS error_count')
            ->pluck('error_count', 'topic_id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $customSkills = (clone $answerBase)
            ->join('question_custom_skill', 'question_custom_skill.question_id', '=', 'questions.id')
            ->groupBy('question_custom_skill.custom_skill_id')
            ->selectRaw('question_custom_skill.custom_skill_id AS topic_id, COUNT(*) AS error_count')
            ->pluck('error_count', 'topic_id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $bnccNodes = (clone $answerBase)
            ->join('question_bncc_links', 'question_bncc_links.question_id', '=', 'questions.id')
            ->groupBy('question_bncc_links.bncc_skill_node_id')
            ->selectRaw('question_bncc_links.bncc_skill_node_id AS topic_id, COUNT(*) AS error_count')
            ->pluck('error_count', 'topic_id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'disciplines' => $disciplines,
            'custom_skills' => $customSkills,
            'bncc_nodes' => $bnccNodes,
        ];
    }

    /**
     * @param  array<int, int>  $counts
     * @return array{0: string, 1: array<int, int>}
     */
    private function scoreCase(string $column, array $counts, int $weight): array
    {
        if ($counts === []) {
            return ['0', []];
        }

        $clauses = [];
        $bindings = [];
        foreach ($counts as $topicId => $count) {
            $clauses[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $topicId;
            $bindings[] = (int) $count * $weight;
        }

        return [
            "CASE {$column} ".implode(' ', $clauses).' ELSE 0 END',
            $bindings,
        ];
    }

    /**
     * @param  array{disciplines: array<int, int>, custom_skills: array<int, int>, bncc_nodes: array<int, int>}  $errors
     */
    private function explain(LearningMaterial $material, array $errors): LearningMaterial
    {
        $reasons = [];

        $disciplineErrors = (int) ($errors['disciplines'][$material->discipline_id] ?? 0);
        if ($disciplineErrors > 0) {
            $reasons[] = "{$disciplineErrors} resposta(s) incorreta(s) em {$material->discipline?->name}";
        }

        $customErrors = (int) ($errors['custom_skills'][$material->custom_skill_id] ?? 0);
        if ($customErrors > 0) {
            $reasons[] = "{$customErrors} dificuldade(s) na habilidade {$material->customSkill?->name}";
        }

        $bnccErrors = (int) ($errors['bncc_nodes'][$material->bncc_node_id] ?? 0);
        if ($bnccErrors > 0) {
            $label = $material->bnccNode?->code ?: $material->bnccNode?->title;
            $reasons[] = "{$bnccErrors} dificuldade(s) na habilidade {$label}";
        }

        $material->setAttribute(
            'recommendation_reason',
            $reasons === []
                ? 'Material publicado para uma de suas turmas.'
                : 'Recomendado porque identificamos '.implode(' e ', $reasons).'.'
        );

        return $material;
    }
}
