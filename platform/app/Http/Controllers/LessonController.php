<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Lesson;
use App\Services\PedagogicalAccessService;
use App\Services\RevisionBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function index(Request $request): View
    {
        $lessons = Lesson::query()->where('organization_id', $request->user()->organization_id)
            ->when($request->user()->hasWorkspaceRole('teacher'), fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->latest()->paginate(15);

        return view('lessons.index', compact('lessons'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        return view('lessons.form', ['lesson' => new Lesson, 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get()]);
    }

    public function store(Request $request, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $lesson = Lesson::create([...collect($data)->except('class_ids')->all(), 'organization_id' => $request->user()->organization_id,
            'author_id' => $request->user()->id, 'generate_review' => $request->boolean('generate_review'),
            'published_at' => $data['status'] === 'published' ? now() : null]);
        $lesson->schoolClasses()->sync($classIds);
        if ($lesson->generate_review) {
            $builder->createDraft($lesson, $request->user(), $classIds);
        }

        return redirect()->route('lessons.edit', $lesson)->with('status', 'Aula criada com sucesso.');
    }

    public function edit(Request $request, Lesson $lesson, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);

        return view('lessons.form', ['lesson' => $lesson->load('schoolClasses'), 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get()]);
    }

    public function update(Request $request, Lesson $lesson, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $makeReview = $request->boolean('generate_review') && ! $lesson->generate_review && ! $lesson->revisionSources()->exists();
        $lesson->update([...collect($data)->except('class_ids')->all(), 'generate_review' => $request->boolean('generate_review'),
            'published_at' => $data['status'] === 'published' ? ($lesson->published_at ?: now()) : null]);
        $lesson->schoolClasses()->sync($classIds);
        if ($makeReview) {
            $builder->createDraft($lesson, $request->user(), $classIds);
        }

        return back()->with('status', 'Aula atualizada.');
    }

    public function destroy(Request $request, Lesson $lesson, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);
        $lesson->delete();

        return redirect()->route('lessons.index')->with('status', 'Aula removida.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'discipline_id' => ['nullable', 'integer', Rule::exists('disciplines', 'id')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id))],
            'objectives' => ['nullable', 'string', 'max:10000'], 'content' => ['nullable', 'string', 'max:100000'], 'starts_at' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published,archived'], 'generate_review' => ['nullable', 'boolean'],
            'class_ids' => ['required', 'array', 'min:1'], 'class_ids.*' => ['integer']]);
    }
}
