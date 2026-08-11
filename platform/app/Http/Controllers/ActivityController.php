<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAttempt;
use App\Models\ActivityResponse;
use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\User;
use App\Services\PedagogicalAccessService;
use App\Services\QuestionLibraryService;
use App\Services\RevisionBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request, PedagogicalAccessService $access): View
    {
        $activities = Activity::query()->where('organization_id', $request->user()->organization_id)
            ->when($access->shouldScopeToAuthor($request->user(), (int) $request->user()->organization_id), fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->withCount('questions')->latest()->paginate(15);

        $manageableIds = $activities->getCollection()
            ->filter(fn (Activity $activity): bool => $access->canManage($request->user(), $activity->organization_id, $activity->author_id))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $canCreate = $access->canCreate($request->user(), (int) $request->user()->organization_id);

        return view('activities.index', compact('activities', 'manageableIds', 'canCreate'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);

        return $this->form($request, new Activity, $access);
    }

    public function edit(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);

        return $this->form($request, $activity, $access);
    }

    public function show(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        abort_unless($access->canView($request->user(), $activity->organization_id), 403);

        return view('activities.show', [
            'activity' => $activity->load(['author', 'discipline', 'schoolClasses', 'questions']),
            'canReport' => $access->canManage($request->user(), $activity->organization_id, $activity->author_id)
                || $access->canReview($request->user(), $activity->organization_id),
        ]);
    }

    public function report(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        $this->authorizeReport($request, $activity, $access);
        $classIds = $activity->schoolClasses()->pluck('school_classes.id');
        $students = User::withTrashed()->where(function ($query) use ($activity, $classIds): void {
            $query->where(function ($active) use ($activity, $classIds): void {
                $active->memberOfOrganization($activity->organization_id, 'student')
                    ->whereHas('schoolClasses', fn ($classes) => $classes->whereIn('school_classes.id', $classIds));
            })->orWhereHas('activityAttempts', fn ($attempts) => $attempts
                ->where('organization_id', $activity->organization_id)->where('activity_id', $activity->id));
        })
            ->orderBy('name')->paginate(50, ['users.id', 'users.name', 'users.email']);
        $attempts = ActivityAttempt::query()->where('activity_id', $activity->id)
            ->whereIn('student_id', $students->getCollection()->pluck('id'))->with(['student:id,name,email', 'responses'])
            ->orderByDesc('attempt_number')->get()->groupBy('student_id');

        return view('activities.report', compact('activity', 'students', 'attempts'));
    }

    public function grade(Request $request, Activity $activity, ActivityAttempt $attempt, PedagogicalAccessService $access): RedirectResponse
    {
        $this->authorizeReport($request, $activity, $access);
        abort_unless((int) $attempt->activity_id === (int) $activity->id
            && (int) $attempt->organization_id === (int) $activity->organization_id, 404);
        $data = $request->validate([
            'scores' => ['nullable', 'array'], 'scores.*' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'array'], 'feedback.*' => ['nullable', 'string', 'max:5000'],
            'overall_score' => ['nullable', 'numeric', 'min:0'],
        ]);
        DB::transaction(function () use ($attempt, $data, $request): void {
            $locked = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless(in_array($locked->status, ['submitted', 'graded'], true), 409);
            $questions = collect($locked->content_snapshot['questions'] ?? [])->keyBy(fn ($question) => (int) $question['key']);
            $manualOverall = $questions->isEmpty() || data_get($locked->content_snapshot, 'activity.modality') === 'paper';
            if ($manualOverall) {
                $overall = $data['overall_score'] ?? null;
                if (! is_numeric($overall) || (float) $overall > (float) $locked->total_points) {
                    throw ValidationException::withMessages(['overall_score' => 'Informe uma pontuação válida para a atividade.']);
                }
                $locked->update([
                    'status' => 'graded', 'score' => (float) $overall,
                    'graded_at' => now(), 'graded_by' => $request->user()->id,
                ]);
                $this->auditGrade($locked);

                return;
            }
            $essayKeys = $questions->filter(fn ($question) => ($question['type'] ?? null) === 'essay')
                ->keys()->map(fn ($key) => (int) $key)->sort()->values();
            $scoreKeys = collect(array_keys($data['scores'] ?? []))->map(fn ($key) => (int) $key)->sort()->values();
            if ($essayKeys->all() !== $scoreKeys->all()) {
                throw ValidationException::withMessages(['scores' => 'Corrija todas as questões discursivas antes de finalizar.']);
            }
            foreach (($data['scores'] ?? []) as $key => $score) {
                $question = $questions->get((int) $key);
                if (! $question || ($question['type'] ?? null) !== 'essay' || (float) $score > (float) ($question['points'] ?? 0)) {
                    throw ValidationException::withMessages(["scores.$key" => 'Pontuação inválida para esta questão.']);
                }
                ActivityResponse::query()->updateOrCreate([
                    'activity_attempt_id' => $locked->id, 'snapshot_question_key' => (int) $key,
                ], [
                    'question_id' => $question['question_id'] ?? null, 'points_awarded' => (float) $score,
                    'feedback' => $data['feedback'][$key] ?? null, 'answered_at' => now(),
                ]);
            }
            $locked->update([
                'status' => 'graded', 'score' => (float) $locked->responses()->sum('points_awarded'),
                'graded_at' => now(), 'graded_by' => $request->user()->id,
            ]);
            $this->auditGrade($locked);
        }, 3);

        return back()->with('status', 'Tentativa corrigida.');
    }

    public function store(Request $request, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $activity = DB::transaction(function () use ($data, $classIds, $request, $builder): Activity {
            $activity = Activity::create([...collect($data)->except(['class_ids', 'question_ids'])->all(), 'organization_id' => $request->user()->organization_id,
                'author_id' => $request->user()->id, 'generate_review' => $request->boolean('generate_review'), 'published_at' => $data['status'] === 'published' ? now() : null]);
            $activity->schoolClasses()->sync($classIds);
            $this->syncQuestions($activity, $data['question_ids'] ?? [], $request);
            if ($activity->generate_review) {
                $builder->createDraft($activity, $request->user(), $classIds, $request->user());
            }

            return $activity;
        }, 3);

        return redirect()->route('activities.edit', $activity)->with('status', 'Atividade criada com sucesso.');
    }

    public function update(Request $request, Activity $activity, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        DB::transaction(function () use ($activity, $request, $data, $classIds, $builder): void {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $makeReview = $request->boolean('generate_review') && ! $locked->generate_review && ! $locked->revisionSources()->exists();
            $locked->update([...collect($data)->except(['class_ids', 'question_ids'])->all(), 'generate_review' => $request->boolean('generate_review'),
                'published_at' => $data['status'] === 'published' ? ($locked->published_at ?: now()) : null]);
            $locked->schoolClasses()->sync($classIds);
            $this->syncQuestions($locked, $data['question_ids'] ?? [], $request);
            if ($makeReview) {
                $author = $locked->author()->withTrashed()->firstOrFail();
                $builder->createDraft($locked, $author, $classIds, $request->user());
            }
        }, 3);

        return back()->with('status', 'Atividade atualizada.');
    }

    public function destroy(Request $request, Activity $activity, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);
        DB::transaction(function () use ($activity): void {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($locked->attempts()->exists()) {
                throw ValidationException::withMessages([
                    'activity' => 'Esta atividade possui tentativas e não pode ser excluída. Arquive-a para preservar o histórico.',
                ]);
            }
            $locked->delete();
        }, 3);

        return redirect()->route('activities.index')->with('status', 'Atividade removida.');
    }

    private function form(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        return view('activities.form', ['activity' => $activity->load(['schoolClasses', 'questions']), 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(),
            'questions' => app(QuestionLibraryService::class)->visibleTo($request->user())
                ->where('organization_id', $request->user()->organization_id)
                ->latest()->limit(200)->get()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'discipline_id' => ['nullable', 'integer', Rule::exists('disciplines', 'id')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id))],
            'instructions' => ['nullable', 'string', 'max:50000'], 'available_at' => ['nullable', 'date'], 'due_at' => ['nullable', 'date', 'after_or_equal:available_at'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:20'], 'points' => ['required', 'numeric', 'min:0', 'max:10000'], 'modality' => ['required', 'in:online,paper,hybrid'],
            'status' => ['required', 'in:draft,published,archived'], 'generate_review' => ['nullable', 'boolean'], 'class_ids' => ['required', 'array', 'min:1'], 'class_ids.*' => ['integer'],
            'question_ids' => ['nullable', 'array'], 'question_ids.*' => ['integer', 'exists:questions,id']]);
    }

    private function syncQuestions(Activity $activity, array $questionIds, Request $request): void
    {
        $allowed = app(QuestionLibraryService::class)->visibleTo($request->user())
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('id', $questionIds)
            ->pluck('id');
        abort_unless($allowed->count() === count(array_unique($questionIds)), 403);
        $activity->questions()->sync($allowed->mapWithKeys(fn ($id, $order) => [$id => ['order' => $order, 'points' => 1]])->all());
    }

    private function authorizeReport(Request $request, Activity $activity, PedagogicalAccessService $access): void
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id)
            || $access->canReview($request->user(), $activity->organization_id), 403);
    }

    private function auditGrade(ActivityAttempt $attempt): void
    {
        AuditLog::log('activity_attempt_graded', ActivityAttempt::class, $attempt->id, [
            'organization_id' => $attempt->organization_id, 'activity_id' => $attempt->activity_id,
            'student_id' => $attempt->student_id,
        ]);
    }
}
