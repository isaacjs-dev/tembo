<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\PedagogicalAccessService;
use App\Services\RevisionBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function index(Request $request, PedagogicalAccessService $access): View
    {
        $lessons = Lesson::query()->where('organization_id', $request->user()->organization_id)
            ->when($access->shouldScopeToAuthor($request->user(), (int) $request->user()->organization_id), fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->latest()->paginate(15);

        $manageableIds = $lessons->getCollection()
            ->filter(fn (Lesson $lesson): bool => $access->canManage($request->user(), $lesson->organization_id, $lesson->author_id))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $canCreate = $access->canCreate($request->user(), (int) $request->user()->organization_id);

        return view('lessons.index', compact('lessons', 'manageableIds', 'canCreate'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);

        return view('lessons.form', ['lesson' => new Lesson, 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get()]);
    }

    public function store(Request $request, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $lesson = DB::transaction(function () use ($data, $classIds, $request, $builder): Lesson {
            $lesson = Lesson::create([...collect($data)->except('class_ids')->all(), 'organization_id' => $request->user()->organization_id,
                'author_id' => $request->user()->id, 'generate_review' => $request->boolean('generate_review'),
                'published_at' => $data['status'] === 'published' ? now() : null]);
            $lesson->schoolClasses()->sync($classIds);
            if ($lesson->generate_review) {
                $builder->createDraft($lesson, $request->user(), $classIds, $request->user());
            }

            return $lesson;
        }, 3);

        return redirect()->route('lessons.edit', $lesson)->with('status', 'Aula criada com sucesso.');
    }

    public function edit(Request $request, Lesson $lesson, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);

        return view('lessons.form', ['lesson' => $lesson->load('schoolClasses'), 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get()]);
    }

    public function show(Request $request, Lesson $lesson, PedagogicalAccessService $access): View
    {
        abort_unless($access->canView($request->user(), $lesson->organization_id), 403);

        return view('lessons.show', [
            'lesson' => $lesson->load(['author', 'discipline', 'schoolClasses']),
            'canReport' => $access->canManage($request->user(), $lesson->organization_id, $lesson->author_id)
                || $access->canReview($request->user(), $lesson->organization_id),
        ]);
    }

    public function report(Request $request, Lesson $lesson, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id)
            || $access->canReview($request->user(), $lesson->organization_id), 403);
        $classIds = $lesson->schoolClasses()->pluck('school_classes.id');
        $students = User::withTrashed()->where(function ($query) use ($lesson, $classIds): void {
            $query->where(function ($active) use ($lesson, $classIds): void {
                $active->memberOfOrganization($lesson->organization_id, 'student')
                    ->whereHas('schoolClasses', fn ($classes) => $classes->whereIn('school_classes.id', $classIds));
            })->orWhereHas('lessonProgress', fn ($progress) => $progress
                ->where('organization_id', $lesson->organization_id)->where('lesson_id', $lesson->id));
        })
            ->orderBy('name')->paginate(50, ['users.id', 'users.name', 'users.email']);
        $progress = LessonProgress::query()->where('lesson_id', $lesson->id)
            ->whereIn('student_id', $students->getCollection()->pluck('id'))->get()->keyBy('student_id');

        return view('lessons.report', compact('lesson', 'students', 'progress'));
    }

    public function update(Request $request, Lesson $lesson, PedagogicalAccessService $access, RevisionBuilderService $builder): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        DB::transaction(function () use ($lesson, $request, $data, $classIds, $builder): void {
            $locked = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $makeReview = $request->boolean('generate_review') && ! $locked->generate_review && ! $locked->revisionSources()->exists();
            $locked->update([...collect($data)->except('class_ids')->all(), 'generate_review' => $request->boolean('generate_review'),
                'published_at' => $data['status'] === 'published' ? ($locked->published_at ?: now()) : null]);
            $locked->schoolClasses()->sync($classIds);
            if ($makeReview) {
                $author = $locked->author()->withTrashed()->firstOrFail();
                $builder->createDraft($locked, $author, $classIds, $request->user());
            }
        }, 3);

        return back()->with('status', 'Aula atualizada.');
    }

    public function destroy(Request $request, Lesson $lesson, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $lesson->organization_id, $lesson->author_id), 403);
        DB::transaction(function () use ($lesson): void {
            $locked = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            if ($locked->progress()->exists()) {
                throw ValidationException::withMessages([
                    'lesson' => 'Esta aula possui progresso de alunos e não pode ser excluída. Arquive-a para preservar o histórico.',
                ]);
            }
            $locked->delete();
        }, 3);

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
