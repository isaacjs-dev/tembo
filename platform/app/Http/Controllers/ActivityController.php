<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Discipline;
use App\Models\Question;
use App\Services\PedagogicalAccessService;
use App\Services\RevisionBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::query()->where('organization_id', $request->user()->organization_id)
            ->when($request->user()->type === 'teacher', fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->withCount('questions')->latest()->paginate(15);

        return view('activities.index', compact('activities'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        return $this->form($request, new Activity, $access);
    }

    public function edit(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);

        return $this->form($request, $activity, $access);
    }

    public function store(Request $request, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $activity = Activity::create([...collect($data)->except(['class_ids', 'question_ids'])->all(), 'organization_id' => $request->user()->organization_id,
            'author_id' => $request->user()->id, 'generate_review' => $request->boolean('generate_review'), 'published_at' => $data['status'] === 'published' ? now() : null]);
        $activity->schoolClasses()->sync($classIds);
        $this->syncQuestions($activity, $data['question_ids'] ?? [], $request);
        if ($activity->generate_review) {
            $builder->createDraft($activity, $request->user(), $classIds);
        }

        return redirect()->route('activities.edit', $activity)->with('status', 'Atividade criada com sucesso.');
    }

    public function update(Request $request, Activity $activity, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $makeReview = $request->boolean('generate_review') && ! $activity->generate_review && ! $activity->revisionSources()->exists();
        $activity->update([...collect($data)->except(['class_ids', 'question_ids'])->all(), 'generate_review' => $request->boolean('generate_review'),
            'published_at' => $data['status'] === 'published' ? ($activity->published_at ?: now()) : null]);
        $activity->schoolClasses()->sync($classIds);
        $this->syncQuestions($activity, $data['question_ids'] ?? [], $request);
        if ($makeReview) {
            $builder->createDraft($activity, $request->user(), $classIds);
        }

        return back()->with('status', 'Atividade atualizada.');
    }

    public function destroy(Request $request, Activity $activity, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $activity->organization_id, $activity->author_id), 403);
        $activity->delete();

        return redirect()->route('activities.index')->with('status', 'Atividade removida.');
    }

    private function form(Request $request, Activity $activity, PedagogicalAccessService $access): View
    {
        return view('activities.form', ['activity' => $activity->load(['schoolClasses', 'questions']), 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(),
            'questions' => Question::where('organization_id', $request->user()->organization_id)->where(function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id)
                    ->orWhere('visibility_scope', 'org_public')
                    ->orWhereHas('shares', fn ($share) => $share->where('shared_with_user_id', $request->user()->id));
            })->latest()->limit(200)->get()]);
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
        $allowed = Question::where('organization_id', $request->user()->organization_id)
            ->whereIn('id', $questionIds)
            ->where(function ($query) use ($request): void {
                $query->where('owner_id', $request->user()->id)
                    ->orWhere('visibility_scope', 'org_public')
                    ->orWhereHas('shares', fn ($share) => $share->where('shared_with_user_id', $request->user()->id));
            })->pluck('id');
        abort_unless($allowed->count() === count(array_unique($questionIds)), 403);
        $activity->questions()->sync($allowed->mapWithKeys(fn ($id, $order) => [$id => ['order' => $order, 'points' => 1]])->all());
    }
}
