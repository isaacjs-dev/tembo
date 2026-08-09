<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\Revision;
use App\Services\ExamAccessService;
use App\Services\ExamPresentationService;
use App\Services\LearningRecommendationService;
use App\Services\OmrGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function __construct(
        private readonly ExamAccessService $access,
        private readonly OmrGradingService $grading,
        private readonly ExamPresentationService $presentation,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $classIds = $user->schoolClasses()->pluck('school_classes.id');
        $grantedExamIds = $this->access->grantedExamIds($request);
        $ownSubmissionExamIds = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('exam_id');

        $availableExams = Exam::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('status', ['published', 'closed'])
            ->where(function ($query) use ($user, $classIds, $grantedExamIds, $ownSubmissionExamIds): void {
                $query->whereHas('schoolClasses', function ($classQuery) use ($classIds): void {
                    $classQuery->whereIn('school_classes.id', $classIds);
                })->orWhereHas('students', fn ($studentQuery) => $studentQuery->where('users.id', $user->id));

                if ($grantedExamIds !== []) {
                    $query->orWhereIn('exams.id', $grantedExamIds);
                }
                if ($ownSubmissionExamIds->isNotEmpty()) {
                    $query->orWhereIn('exams.id', $ownSubmissionExamIds);
                }
            })
            ->with(['author:id,name', 'organization:id,name', 'discipline:id,name'])
            ->withCount('questions')
            ->latest()
            ->paginate(10);

        $examIds = $availableExams->pluck('id');
        $attempts = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->get();

        $submissions = $attempts->unique('exam_id')->keyBy('exam_id');
        $gradedSubmissions = $attempts
            ->where('status', 'graded')
            ->unique('exam_id')
            ->keyBy('exam_id');
        $attemptCounts = $attempts->countBy('exam_id');
        $portalMeta = $availableExams->getCollection()
            ->mapWithKeys(function (Exam $exam) use ($attemptCounts): array {
                $allowed = $this->allowedAttempts($exam);
                $used = (int) ($attemptCounts[$exam->id] ?? 0);

                return [
                    $exam->id => [
                        'attempts_allowed' => $allowed,
                        'attempts_used' => $used,
                        'attempts_remaining' => max(0, $allowed - $used),
                        'availability' => $this->access->availability($exam),
                        'release' => $this->access->releaseSettings($exam),
                        'results_can_be_viewed' => $this->access->resultsCanBeViewed($exam),
                        'results_available_at' => $this->access->resultsAvailableAt($exam),
                        'application_mode' => $this->access->applicationMode($exam),
                        'application_mode_label' => $this->access->applicationModeLabel($exam),
                        'supports_online' => $this->access->supportsOnline($exam),
                    ],
                ];
            });

        return view('student.dashboard', compact(
            'availableExams',
            'submissions',
            'gradedSubmissions',
            'portalMeta',
        ));
    }

    public function joinByCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_code' => ['required', 'string', 'max:10'],
        ]);
        $code = strtoupper(trim($validated['access_code']));
        $student = $request->user();

        $exam = Exam::query()
            ->where('organization_id', $student->organization_id)
            ->where('access_code', $code)
            ->first();

        if (! $exam) {
            return back()
                ->withInput()
                ->withErrors(['access_code' => 'Código de avaliação inválido ou prova indisponível.']);
        }

        $this->access->ensureJoinable($exam, $student);
        $this->access->grantFromAccessCode($exam, $request);

        return redirect()->route('student.exam.show', $exam);
    }

    public function show(Request $request, string $id): View
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request, 'overview');
        $exam->load(['author:id,name', 'organization:id,name', 'discipline:id,name'])->loadCount('questions');

        $inProgress = $this->inProgressSubmission($exam, $request);
        if ($inProgress && $this->deadlineReached($inProgress)) {
            $this->finalizeSubmission($exam, $inProgress);
        }

        $attempts = $this->attemptsFor($exam, $request);
        $submission = $attempts->first();
        $attemptsAllowed = $this->allowedAttempts($exam);
        $attemptsUsed = $attempts->count();
        $supportsOnline = $this->access->supportsOnline($exam);
        $availability = $this->access->availability($exam);
        $canStart = $supportsOnline
            && $exam->status === 'published'
            && $availability['state'] === 'open'
            && ! $attempts->contains('status', 'in_progress')
            && $attemptsUsed < $attemptsAllowed;
        $release = $this->access->releaseSettings($exam);
        $resultsCanBeViewed = $this->access->resultsCanBeViewed($exam);
        $resultsAvailableAt = $this->access->resultsAvailableAt($exam);
        $applicationModeLabel = $this->access->applicationModeLabel($exam);
        $blockingRevision = $this->blockingRevision($exam, $request);
        if ($blockingRevision) {
            $canStart = false;
        }

        return view('student.exam_intro', compact(
            'exam',
            'submission',
            'attempts',
            'attemptsAllowed',
            'attemptsUsed',
            'canStart',
            'release',
            'resultsCanBeViewed',
            'availability',
            'supportsOnline',
            'blockingRevision',
            'resultsAvailableAt',
            'applicationModeLabel',
        ));
    }

    public function start(Request $request, string $id): RedirectResponse
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request);

        if ($blockingRevision = $this->blockingRevision($exam, $request)) {
            throw ValidationException::withMessages([
                'revision' => "Conclua a revisão obrigatória \"{$blockingRevision->title}\" antes de iniciar esta avaliação.",
            ]);
        }

        $existing = $this->inProgressSubmission($exam, $request);
        if ($existing && $this->deadlineReached($existing)) {
            $this->finalizeSubmission($exam, $existing);
            $existing = null;
        }

        if ($existing) {
            return redirect()->route('student.exam.execution', $exam);
        }

        $submission = DB::transaction(function () use ($exam, $request): ExamSubmission {
            // There may be no submission row to lock on the first attempt. Locking the
            // student prevents two concurrent start requests from both claiming attempt 1.
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $attempts = ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->get();

            if ($current = $attempts->firstWhere('status', 'in_progress')) {
                return $current;
            }

            $allowed = $this->allowedAttempts($exam);
            if ($attempts->count() >= $allowed) {
                throw ValidationException::withMessages([
                    'attempt' => 'Você já utilizou todas as tentativas permitidas.',
                ]);
            }

            $startedAt = now();
            $timeLimit = $this->timeLimitMinutes($exam);

            return ExamSubmission::create([
                'exam_id' => $exam->id,
                'user_id' => $request->user()->id,
                'attempt_number' => ((int) $attempts->max('attempt_number')) + 1,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'deadline_at' => $timeLimit ? $startedAt->copy()->addMinutes($timeLimit) : null,
                'client_token' => (string) Str::uuid(),
            ]);
        }, 3);

        return redirect()->route('student.exam.execution', [
            'exam' => $exam->id,
            'attempt' => $submission->attempt_number,
        ]);
    }

    public function execution(Request $request, string $id): View|RedirectResponse
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request);
        $exam->load([
            'author:id,name',
            'organization:id,name',
            'discipline:id,name',
            'questions.discipline',
            'questions.customSkills',
            'questions.bnccSkills',
        ]);

        $submission = $this->inProgressSubmission($exam, $request);
        if (! $submission) {
            return redirect()
                ->route('student.exam.show', $exam)
                ->withErrors(['attempt' => 'Não há uma tentativa em andamento.']);
        }

        if ($this->deadlineReached($submission)) {
            $this->finalizeSubmission($exam, $submission);

            return $this->redirectAfterTimeout($exam);
        }

        $savedAnswers = $submission->answers()
            ->get()
            ->mapWithKeys(fn (ExamAnswer $answer): array => [
                $answer->question_id => $this->rawAnswer($answer),
            ])
            ->all();

        if (filter_var($exam->settings['shuffle_questions'] ?? false, FILTER_VALIDATE_BOOL)) {
            $seed = (string) $submission->client_token;
            $exam->setRelation(
                'questions',
                $exam->questions
                    ->sortBy(fn ($question) => hash('sha256', "{$seed}:question:{$question->id}"))
                    ->values(),
            );
        }

        $questionBlocks = $this->presentation->blocks($exam->questions, $exam->settings ?? []);

        return view('student.exam_execution', compact('exam', 'submission', 'savedAnswers', 'questionBlocks'));
    }

    public function autosave(Request $request, string $id): JsonResponse
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request);
        $exam->load('questions');

        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'max:250'],
        ]);

        $submission = $this->submissionByToken($exam, $request, $validated['client_token']);
        if ($submission->status !== 'in_progress') {
            return response()->json([
                'status' => $submission->status,
                'message' => 'Esta tentativa já foi finalizada.',
            ], 409);
        }

        if ($this->deadlineReached($submission)) {
            $this->finalizeSubmission($exam, $submission);

            return response()->json([
                'status' => 'timed_out',
                'message' => 'O tempo da avaliação terminou. As respostas salvas foram enviadas.',
            ], 409);
        }

        $answers = $this->validateAnswers($exam, $validated['answers']);

        DB::transaction(function () use ($submission, $answers): void {
            $locked = ExamSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            if ($locked->status !== 'in_progress' || $this->deadlineReached($locked)) {
                throw ValidationException::withMessages([
                    'attempt' => 'Esta tentativa não aceita mais alterações.',
                ]);
            }

            $this->upsertAnswers($locked, $answers);
            $locked->touch();
        }, 3);

        $answeredCount = $this->answeredCount($submission->fresh());

        return response()->json([
            'status' => 'saved',
            'saved_at' => now()->toIso8601String(),
            'answered_count' => $answeredCount,
            'total_questions' => $exam->questions->count(),
        ]);
    }

    public function submit(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request);
        $exam->load('questions');

        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
            'answers' => ['sometimes', 'array', 'max:250'],
        ]);
        $submission = $this->submissionByToken($exam, $request, $validated['client_token']);

        if (in_array($submission->status, ['submitted', 'graded'], true)) {
            return $this->submissionResponse($request, $exam, $submission, true);
        }
        if ($submission->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'attempt' => 'Esta tentativa não pode ser enviada.',
            ]);
        }

        $answers = [];
        if (! $this->deadlineReached($submission)) {
            $answers = $this->validateAnswers($exam, $validated['answers'] ?? []);
        }

        $submission = $this->finalizeSubmission($exam, $submission, $answers);

        return $this->submissionResponse($request, $exam, $submission);
    }

    public function results(Request $request, string $id): View|RedirectResponse
    {
        $exam = $this->access->findForStudent($request->user(), $id);
        $this->access->ensureCanAccess($exam, $request->user(), $request, 'results');

        $release = $this->access->releaseSettings($exam);
        if (! in_array(true, $release, true)) {
            return redirect()
                ->route('student.dashboard')
                ->withErrors(['results' => 'Os resultados ainda não foram liberados pelo professor.']);
        }

        $exam->load([
            'author:id,name',
            'organization:id,name',
            'discipline:id,name',
            'questions.discipline',
            'questions.customSkills',
            'questions.bnccSkills',
        ]);
        $submission = ExamSubmission::query()
            ->with('answers')
            ->where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'graded')
            ->orderByDesc('attempt_number')
            ->firstOrFail();

        $totalPoints = (float) $exam->questions->sum(
            fn ($question) => (float) ($question->pivot->points ?? 0)
        );
        $recommendations = $release['show_feedback']
            ? $this->buildRecommendations($exam, $submission)
            : collect();
        $recommendedMaterials = $release['show_feedback']
            ? app(LearningRecommendationService::class)
                ->forStudent($request->user(), [], $submission, 3)
                ->getCollection()
            : collect();
        $applicationModeLabel = $this->access->applicationModeLabel($exam);
        $availability = $this->access->availability($exam);

        return view('student.exam_results', compact(
            'exam',
            'submission',
            'release',
            'totalPoints',
            'recommendations',
            'recommendedMaterials',
            'applicationModeLabel',
            'availability',
        ));
    }

    /**
     * @param  array<int|string, mixed>  $rawAnswers
     * @return array<int, mixed>
     */
    private function validateAnswers(Exam $exam, array $rawAnswers): array
    {
        $questions = $exam->questions->keyBy('id');
        $validated = [];
        $errors = [];

        foreach ($rawAnswers as $questionId => $rawAnswer) {
            if (! ctype_digit((string) $questionId) || ! $questions->has((int) $questionId)) {
                $errors["answers.$questionId"] = 'A questão informada não pertence a esta avaliação.';

                continue;
            }

            $question = $questions->get((int) $questionId);
            if ($rawAnswer === null || $rawAnswer === '') {
                $validated[(int) $questionId] = null;

                continue;
            }

            if (in_array($question->type, ['multiple_choice', 'true_false'], true)) {
                $normalized = OmrGradingService::normalizeAnswer(
                    $rawAnswer,
                    $question->type,
                    $question->content['options'] ?? [],
                );

                if (OmrGradingService::isInvalidAnswer($normalized)) {
                    $errors["answers.$questionId"] = 'A resposta selecionada é inválida.';

                    continue;
                }

                $validated[(int) $questionId] = $normalized;

                continue;
            }

            if ($question->type === 'essay') {
                if (! is_string($rawAnswer) || mb_strlen($rawAnswer) > 20000) {
                    $errors["answers.$questionId"] = 'A resposta discursiva deve ter no máximo 20.000 caracteres.';

                    continue;
                }

                $validated[(int) $questionId] = $rawAnswer;

                continue;
            }

            $errors["answers.$questionId"] = 'Este tipo de questão não é aceito na prova on-line.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    /**
     * @param  array<int, mixed>  $answers
     */
    private function upsertAnswers(ExamSubmission $submission, array $answers): void
    {
        foreach ($answers as $questionId => $answer) {
            ExamAnswer::query()->updateOrCreate(
                [
                    'exam_submission_id' => $submission->id,
                    'question_id' => $questionId,
                ],
                [
                    'answer_data' => ['raw' => $answer],
                    'is_correct' => null,
                    'points_awarded' => 0,
                ],
            );
        }
    }

    /**
     * @param  array<int, mixed>  $incomingAnswers
     */
    private function finalizeSubmission(
        Exam $exam,
        ExamSubmission $submission,
        array $incomingAnswers = [],
    ): ExamSubmission {
        return DB::transaction(function () use ($exam, $submission, $incomingAnswers): ExamSubmission {
            $locked = ExamSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            if (in_array($locked->status, ['submitted', 'graded'], true)) {
                return $locked;
            }
            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'attempt' => 'Esta tentativa não pode ser finalizada.',
                ]);
            }

            if ($incomingAnswers !== [] && ! $this->deadlineReached($locked)) {
                $this->upsertAnswers($locked, $incomingAnswers);
            }

            $storedAnswers = $locked->answers()
                ->get()
                ->mapWithKeys(fn (ExamAnswer $answer): array => [
                    $answer->question_id => $this->rawAnswer($answer),
                ])
                ->all();
            $grading = $this->grading->gradeAnswers($exam->id, null, $storedAnswers);
            $requiresManualGrading = false;

            foreach ($exam->questions as $question) {
                $answer = ExamAnswer::query()->firstOrNew([
                    'exam_submission_id' => $locked->id,
                    'question_id' => $question->id,
                ]);
                $rawAnswer = $storedAnswers[$question->id] ?? null;
                $answer->answer_data = ['raw' => $rawAnswer];

                if ($question->type === 'essay') {
                    $requiresManualGrading = true;
                    $answer->is_correct = null;
                    $answer->points_awarded = 0;
                } else {
                    $detail = $grading['details'][$question->id] ?? [];
                    $answer->is_correct = (bool) ($detail['correct'] ?? false);
                    $answer->points_awarded = (float) ($detail['points'] ?? 0);
                }

                $answer->save();
            }

            $locked->update([
                'status' => $requiresManualGrading ? 'submitted' : 'graded',
                'score' => (float) $grading['score'],
                'finished_at' => now(),
            ]);

            return $locked->fresh('answers');
        }, 3);
    }

    private function submissionResponse(
        Request $request,
        Exam $exam,
        ExamSubmission $submission,
        bool $idempotent = false,
    ): JsonResponse|RedirectResponse {
        $release = $this->access->releaseSettings($exam);
        $payload = [
            'status' => $submission->status,
            'idempotent' => $idempotent,
            'message' => $submission->status === 'submitted'
                ? 'Avaliação enviada. Aguarde a correção do professor.'
                : 'Avaliação enviada com sucesso.',
            'redirect_url' => $submission->status === 'graded' && $this->access->hasReleasedResults($exam)
                ? route('student.exam.results', $exam)
                : route('student.dashboard'),
        ];

        if ($release['show_score'] && $submission->status === 'graded') {
            $payload['score'] = (float) $submission->score;
        }

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        $message = $payload['message'];
        if (array_key_exists('score', $payload)) {
            $message .= ' Sua nota foi '.number_format($payload['score'], 1, ',', '.');
        }

        return redirect()->route('student.dashboard')->with('status', $message);
    }

    private function redirectAfterTimeout(Exam $exam): RedirectResponse
    {
        if ($this->access->hasReleasedResults($exam)) {
            return redirect()
                ->route('student.exam.results', $exam)
                ->with('status', 'O tempo terminou e suas respostas salvas foram enviadas.');
        }

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'O tempo terminou e suas respostas salvas foram enviadas.');
    }

    private function submissionByToken(Exam $exam, Request $request, string $clientToken): ExamSubmission
    {
        return ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->where('client_token', $clientToken)
            ->firstOrFail();
    }

    private function inProgressSubmission(Exam $exam, Request $request): ?ExamSubmission
    {
        return ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->orderByDesc('attempt_number')
            ->first();
    }

    private function attemptsFor(Exam $exam, Request $request): Collection
    {
        return ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->get();
    }

    private function deadlineReached(ExamSubmission $submission): bool
    {
        return $submission->deadline_at !== null && ! now()->isBefore($submission->deadline_at);
    }

    private function allowedAttempts(Exam $exam): int
    {
        return max(1, (int) ($exam->settings['attempts'] ?? 1));
    }

    private function timeLimitMinutes(Exam $exam): ?int
    {
        $minutes = (int) ($exam->settings['time_limit'] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    private function rawAnswer(ExamAnswer $answer): mixed
    {
        $data = is_array($answer->answer_data)
            ? $answer->answer_data
            : json_decode((string) $answer->answer_data, true);

        return $data['raw'] ?? $data['selected'] ?? null;
    }

    private function answeredCount(ExamSubmission $submission): int
    {
        return $submission->answers()
            ->get()
            ->filter(function (ExamAnswer $answer): bool {
                $raw = $this->rawAnswer($answer);

                return $raw !== null && $raw !== '';
            })
            ->count();
    }

    private function buildRecommendations(Exam $exam, ExamSubmission $submission): Collection
    {
        $answers = $submission->answers->keyBy('question_id');
        $groups = [];

        foreach ($exam->questions as $question) {
            $answer = $answers->get($question->id);
            if (! $answer || $answer->is_correct !== false) {
                continue;
            }

            $discipline = $question->discipline?->name ?: 'Conteúdo geral';
            $skills = $question->customSkills
                ->pluck('name')
                ->merge($question->bnccSkills->map(
                    fn ($skill) => $skill->code ?? $skill->name ?? $skill->title ?? null
                ))
                ->filter()
                ->unique()
                ->values();
            $key = $discipline.'|'.$skills->join(',');

            $groups[$key] ??= [
                'discipline' => $discipline,
                'skills' => $skills,
                'errors' => 0,
            ];
            $groups[$key]['errors']++;
        }

        return collect($groups)
            ->sortByDesc('errors')
            ->values()
            ->map(function (array $group): array {
                $skillText = $group['skills']->isNotEmpty()
                    ? ' nas habilidades '.$group['skills']->join(', ')
                    : '';

                return [
                    'title' => 'Revisar '.$group['discipline'],
                    'reason' => "Recomendação baseada em {$group['errors']} resposta(s) incorreta(s){$skillText}.",
                    'action' => 'Retome o conteúdo, registre suas dúvidas e pratique questões do mesmo tema.',
                ];
            });
    }

    private function blockingRevision(Exam $exam, Request $request): ?Revision
    {
        $classIds = $request->user()->schoolClasses()->pluck('school_classes.id');

        return Revision::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('status', 'published')
            ->where('is_required', true)
            ->where('timing', 'before')
            ->where('block_exam', true)
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->whereHas('sources', fn ($query) => $query
                ->where('source_type', $exam->getMorphClass())
                ->where('source_id', $exam->id))
            ->whereHas('schoolClasses', fn ($query) => $query->whereIn('school_classes.id', $classIds))
            ->whereDoesntHave('attempts', fn ($query) => $query
                ->where('student_id', $request->user()->id)
                ->where('status', 'completed'))
            ->first();
    }
}
