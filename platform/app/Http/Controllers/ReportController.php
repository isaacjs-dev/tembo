<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $examQuery = Exam::query()
            ->where('organization_id', $orgId)
            ->withCount('submissions')
            ->orderByDesc('created_at');

        if ($user->hasWorkspaceRole('teacher')) {
            $examQuery->where('author_id', $user->id);
        }

        $exams = $examQuery->get();
        $allowedExamIds = $exams->pluck('id');

        $classQuery = SchoolClass::query()
            ->where('organization_id', $orgId)
            ->orderBy('name');

        if ($user->hasWorkspaceRole('teacher')) {
            $classQuery->where(function ($query) use ($user) {
                $query->where(function ($ownerQuery) use ($user) {
                    $ownerQuery->where('owner_type', $user::class)
                        ->where('owner_id', $user->id);
                })->orWhereHas('teachers', fn ($teacherQuery) => $teacherQuery->where('users.id', $user->id));
            });
        }

        $classes = $classQuery->get();
        $allowedClassIds = $classes->pluck('id');

        $validated = $request->validate([
            'exam_id' => ['nullable', 'integer', Rule::in($allowedExamIds->all())],
            'class_id' => ['nullable', 'integer', Rule::in($allowedClassIds->all())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectedExamId = $validated['exam_id'] ?? null;
        $selectedClassId = $validated['class_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $pointsRows = DB::table('exam_questions')
            ->whereIn('exam_id', $allowedExamIds)
            ->select('exam_id', 'question_id', 'points')
            ->get();

        $totalPointsByExam = $pointsRows
            ->groupBy('exam_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('points'));

        $pointsByExamQuestion = $pointsRows->mapWithKeys(
            fn ($row) => ["{$row->exam_id}:{$row->question_id}" => (float) $row->points]
        );
        $questionCountByExam = $pointsRows
            ->groupBy('exam_id')
            ->map(fn (Collection $rows) => $rows->pluck('question_id')->unique()->count());

        $submissionQuery = ExamSubmission::query()
            ->whereIn('exam_id', $allowedExamIds)
            ->with([
                'user:id,name,email',
                'exam:id,title',
                'answers.question.discipline',
                'answers.question.customSkills',
                'answers.question.bnccSkills',
            ]);

        if ($selectedExamId) {
            $submissionQuery->where('exam_id', $selectedExamId);
        }

        if ($selectedClassId) {
            $studentIds = DB::table('class_student')
                ->where('school_class_id', $selectedClassId)
                ->pluck('user_id');
            $submissionQuery->whereIn('user_id', $studentIds);
        }

        if ($dateFrom) {
            $submissionQuery->whereDate(DB::raw('COALESCE(finished_at, updated_at)'), '>=', $dateFrom);
        }

        if ($dateTo) {
            $submissionQuery->whereDate(DB::raw('COALESCE(finished_at, updated_at)'), '<=', $dateTo);
        }

        $submissions = $submissionQuery
            ->orderByDesc('finished_at')
            ->limit(5000)
            ->get()
            ->each(function (ExamSubmission $submission) use ($totalPointsByExam) {
                $total = (float) ($totalPointsByExam[$submission->exam_id] ?? 0);
                $submission->setAttribute('total_points', $total);
                $submission->setAttribute(
                    'score_percent',
                    $submission->status === 'graded' && $total > 0
                        ? round(((float) $submission->score / $total) * 100, 1)
                        : null
                );
            });

        $gradedSubmissions = $submissions->where('status', 'graded');
        $percentages = $gradedSubmissions
            ->pluck('score_percent')
            ->filter(fn ($value) => $value !== null)
            ->values();

        $stats = [
            'total_submissions' => $submissions->count(),
            'graded' => $gradedSubmissions->count(),
            'pending' => $submissions->where('status', 'submitted')->count(),
            'in_progress' => $submissions->where('status', 'in_progress')->count(),
            'average' => $percentages->isNotEmpty() ? round($percentages->avg(), 1) : 0,
            'median' => $this->calculateMedian($percentages->all()),
            'min' => $percentages->isNotEmpty() ? round($percentages->min(), 1) : 0,
            'max' => $percentages->isNotEmpty() ? round($percentages->max(), 1) : 0,
        ];

        $scoreDistribution = collect(range(0, 10))
            ->mapWithKeys(fn ($bucket) => [$bucket * 10 => 0]);
        foreach ($percentages as $percentage) {
            $bucket = min(100, ((int) floor($percentage / 10)) * 10);
            $scoreDistribution[$bucket] = $scoreDistribution[$bucket] + 1;
        }

        $examPerformance = $gradedSubmissions
            ->groupBy('exam_id')
            ->map(function (Collection $group) {
                return (object) [
                    'exam' => (object) [
                        'id' => $group->first()->exam_id,
                        'title' => $group->first()->exam?->title,
                    ],
                    'avg_score' => round((float) $group->avg('score_percent'), 1),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        [$questionPerformance, $skillPerformance, $disciplinePerformance] = $this->buildLearningAnalytics(
            $gradedSubmissions,
            $pointsByExamQuestion
        );

        $performanceTrend = $gradedSubmissions
            ->filter(fn (ExamSubmission $submission) => $submission->finished_at !== null)
            ->groupBy(fn (ExamSubmission $submission) => $submission->finished_at->format('Y-m-d'))
            ->map(fn (Collection $group, string $date) => (object) [
                'date' => $date,
                'label' => $group->first()->finished_at->format('d/m'),
                'average' => round((float) $group->avg('score_percent'), 1),
                'assessments' => $group->count(),
            ])
            ->sortBy('date')
            ->values();

        $activeSubmissions = $submissions
            ->where('status', 'in_progress')
            ->sortByDesc('updated_at')
            ->take(12)
            ->map(function (ExamSubmission $submission) use ($questionCountByExam) {
                $total = (int) ($questionCountByExam[$submission->exam_id] ?? 0);
                $answered = $submission->answers
                    ->filter(function ($answer) {
                        $data = is_array($answer->answer_data)
                            ? $answer->answer_data
                            : json_decode((string) $answer->answer_data, true);
                        $value = $data['raw'] ?? $data['selected'] ?? null;

                        return $value !== null && $value !== '';
                    })
                    ->count();

                return (object) [
                    'user' => $submission->user,
                    'exam' => $submission->exam,
                    'answered' => $answered,
                    'total' => $total,
                    'progress' => $total > 0 ? round(($answered / $total) * 100) : 0,
                    'updated_at' => $submission->updated_at,
                ];
            })
            ->values();

        $atRiskStudents = $gradedSubmissions
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                return (object) [
                    'user' => $group->first()->user,
                    'average' => round((float) $group->avg('score_percent'), 1),
                    'assessments' => $group->count(),
                    'last_activity' => $group->max('finished_at'),
                ];
            })
            ->filter(fn ($student) => $student->average < 60)
            ->sortBy('average')
            ->take(10)
            ->values();

        return view('reports.index', compact(
            'exams',
            'classes',
            'submissions',
            'stats',
            'scoreDistribution',
            'examPerformance',
            'questionPerformance',
            'skillPerformance',
            'disciplinePerformance',
            'performanceTrend',
            'activeSubmissions',
            'atRiskStudents',
            'selectedExamId',
            'selectedClassId',
            'dateFrom',
            'dateTo'
        ));
    }

    private function buildLearningAnalytics(
        Collection $submissions,
        Collection $pointsByExamQuestion
    ): array {
        $questions = [];
        $skills = [];
        $disciplines = [];

        foreach ($submissions as $submission) {
            foreach ($submission->answers as $answer) {
                $question = $answer->question;
                if (! $question) {
                    continue;
                }

                $maxPoints = (float) ($pointsByExamQuestion["{$submission->exam_id}:{$question->id}"] ?? 0);
                $questionRow = $questions[$question->id] ?? [
                    'question_id' => $question->id,
                    'statement' => $question->content['statement'] ?? 'Sem enunciado',
                    'type' => $question->type,
                    'discipline' => $question->discipline?->name ?? 'Sem disciplina',
                    'responses' => 0,
                    'evaluated' => 0,
                    'correct' => 0,
                    'earned' => 0.0,
                    'possible' => 0.0,
                ];

                $questionRow['responses']++;
                if ($answer->is_correct !== null) {
                    $questionRow['evaluated']++;
                    $questionRow['correct'] += $answer->is_correct ? 1 : 0;
                }
                $questionRow['earned'] += (float) ($answer->points_awarded ?? 0);
                $questionRow['possible'] += $maxPoints;
                $questions[$question->id] = $questionRow;

                $disciplineKey = (string) ($question->discipline_id ?: 'none');
                $disciplineRow = $disciplines[$disciplineKey] ?? [
                    'label' => $question->discipline?->name ?? 'Sem disciplina',
                    'responses' => 0,
                    'earned' => 0.0,
                    'possible' => 0.0,
                ];
                $disciplineRow['responses']++;
                $disciplineRow['earned'] += (float) ($answer->points_awarded ?? 0);
                $disciplineRow['possible'] += $maxPoints;
                $disciplines[$disciplineKey] = $disciplineRow;

                $labels = collect();
                foreach ($question->customSkills as $skill) {
                    $labels->push([
                        'key' => "custom:{$skill->id}",
                        'label' => $skill->name,
                        'source' => 'Habilidade institucional',
                    ]);
                }
                foreach ($question->bnccSkills as $skill) {
                    $labels->push([
                        'key' => "bncc:{$skill->id}",
                        'label' => trim(($skill->code ? "{$skill->code} — " : '').$skill->title),
                        'source' => 'BNCC',
                    ]);
                }
                if ($labels->isEmpty()) {
                    $labels->push([
                        'key' => 'discipline:'.($question->discipline_id ?: 'none'),
                        'label' => $question->discipline?->name ?? 'Sem habilidade vinculada',
                        'source' => 'Disciplina',
                    ]);
                }

                foreach ($labels->unique('key') as $label) {
                    $skillRow = $skills[$label['key']] ?? [
                        'label' => $label['label'],
                        'source' => $label['source'],
                        'responses' => 0,
                        'earned' => 0.0,
                        'possible' => 0.0,
                    ];
                    $skillRow['responses']++;
                    $skillRow['earned'] += (float) ($answer->points_awarded ?? 0);
                    $skillRow['possible'] += $maxPoints;
                    $skills[$label['key']] = $skillRow;
                }
            }
        }

        $questionPerformance = collect($questions)
            ->map(function (array $row) {
                $row['accuracy'] = $row['evaluated'] > 0
                    ? round(($row['correct'] / $row['evaluated']) * 100, 1)
                    : null;
                $row['mastery'] = $row['possible'] > 0
                    ? round(($row['earned'] / $row['possible']) * 100, 1)
                    : null;
                $row['needs_attention'] = $row['evaluated'] >= 3 && $row['accuracy'] < 50;

                return (object) $row;
            })
            ->sortBy(fn ($row) => $row->accuracy ?? 101)
            ->values();

        $skillPerformance = collect($skills)
            ->map(function (array $row) {
                $row['mastery'] = $row['possible'] > 0
                    ? round(($row['earned'] / $row['possible']) * 100, 1)
                    : null;

                return (object) $row;
            })
            ->sortBy(fn ($row) => $row->mastery ?? 101)
            ->values();

        $disciplinePerformance = collect($disciplines)
            ->map(function (array $row) {
                $row['mastery'] = $row['possible'] > 0
                    ? round(($row['earned'] / $row['possible']) * 100, 1)
                    : null;

                return (object) $row;
            })
            ->sortBy(fn ($row) => $row->mastery ?? 101)
            ->values();

        return [$questionPerformance, $skillPerformance, $disciplinePerformance];
    }

    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = (int) floor(($count - 1) / 2);

        if ($count % 2 === 0) {
            return round(($values[$middle] + $values[$middle + 1]) / 2, 1);
        }

        return round($values[$middle], 1);
    }
}
