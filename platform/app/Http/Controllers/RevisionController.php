<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Revision;
use App\Models\RevisionItem;
use App\Services\PedagogicalAccessService;
use App\Services\RevisionImportService;
use App\Services\RevisionPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RevisionController extends Controller
{
    public function index(Request $request, PedagogicalAccessService $access): View
    {
        $revisions = Revision::query()->where('organization_id', $request->user()->organization_id)
            ->when($request->user()->hasWorkspaceRole('teacher') && ! $access->canReview($request->user(), $request->user()->organization_id), fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->withCount(['items', 'attempts'])->latest()->paginate(15);

        return view('revisions.index', compact('revisions'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        return $this->form($request, new Revision, $access);
    }

    public function edit(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id) || $access->canReview($request->user(), $revision->organization_id), 403);

        return $this->form($request, $revision, $access);
    }

    public function store(Request $request, PedagogicalAccessService $access): RedirectResponse
    {
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $revision = Revision::create([...collect($data)->except('class_ids')->all(), 'organization_id' => $request->user()->organization_id, 'author_id' => $request->user()->id,
            'status' => 'draft', 'published_at' => null]);
        $revision->schoolClasses()->sync($classIds);

        return redirect()->route('revisions.edit', $revision)->with('status', 'Revisão criada. Adicione itens ou importe o JSON da IA.');
    }

    public function update(Request $request, Revision $revision, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $revision->update(collect($data)->except(['class_ids', 'status'])->all());
        $revision->schoolClasses()->sync($classIds);

        return back()->with('status', 'Configurações atualizadas para todos os alunos.');
    }

    public function destroy(Request $request, Revision $revision, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $revision->delete();

        return redirect()->route('revisions.index')->with('status', 'Revisão removida.');
    }

    public function storeItem(Request $request, Revision $revision, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $this->itemData($request);
        $revision->items()->create([...$data, 'order' => (int) ($revision->items()->max('order') ?? -1) + 1, 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Item adicionado.');
    }

    public function updateItem(Request $request, Revision $revision, RevisionItem $item, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless((int) $item->revision_id === (int) $revision->id && $access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $item->update([...$this->itemData($request), 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Item atualizado. Alunos em andamento verão a nova versão nos itens ainda não respondidos.');
    }

    public function destroyItem(Request $request, Revision $revision, RevisionItem $item, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless((int) $item->revision_id === (int) $revision->id && $access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $item->delete();

        return back()->with('status', 'Item removido.');
    }

    public function reorder(Request $request, Revision $revision, PedagogicalAccessService $access): JsonResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);
        abort_unless($revision->items()->whereIn('id', $data['items'])->count() === count(array_unique($data['items'])), 403);
        DB::transaction(fn () => collect($data['items'])->each(fn ($id, $order) => RevisionItem::whereKey($id)->update(['order' => $order])));

        return response()->json(['ok' => true]);
    }

    public function prompt(Request $request, Revision $revision, RevisionPromptService $prompts, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $counts = $request->validate(['multiple_choice' => ['nullable', 'integer', 'min:0', 'max:50'], 'true_false' => ['nullable', 'integer', 'min:0', 'max:50'],
            'matching' => ['nullable', 'integer', 'min:0', 'max:50'], 'fill_blank' => ['nullable', 'integer', 'min:0', 'max:50'], 'ordering' => ['nullable', 'integer', 'min:0', 'max:50'],
            'flashcard' => ['nullable', 'integer', 'min:0', 'max:50'], 'short_answer' => ['nullable', 'integer', 'min:0', 'max:50']]);
        $counts = $counts ?: ['multiple_choice' => 5, 'true_false' => 3, 'flashcard' => 3, 'short_answer' => 2];
        $prompt = $prompts->build($revision, $counts);

        return view('revisions.prompt', compact('revision', 'prompt', 'counts'));
    }

    public function import(Request $request, Revision $revision, RevisionImportService $imports, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $request->validate(['json' => ['required', 'string', 'max:1000000'], 'mode' => ['required', 'in:append,replace']]);
        $result = $imports->import($revision, $request->user(), $data['json'], $data['mode']);

        return redirect()->route('revisions.edit', $revision)->with('status', "{$result->items_imported} itens importados e prontos para revisão do professor.");
    }

    public function status(Request $request, Revision $revision, PedagogicalAccessService $access): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['in_review', 'changes_requested', 'published', 'suspended'])], 'review_notes' => ['nullable', 'string', 'max:5000']]);
        $isAuthor = $access->canManage($request->user(), $revision->organization_id, $revision->author_id);
        $isReviewer = $access->canReview($request->user(), $revision->organization_id);
        abort_unless(($data['status'] === 'in_review' && $isAuthor) || ($data['status'] === 'published' && ($isAuthor || $isReviewer)) || (in_array($data['status'], ['changes_requested', 'suspended'], true) && $isReviewer), 403);
        if ($data['status'] === 'published' && ! $revision->activeItems()->exists()) {
            throw ValidationException::withMessages(['status' => 'Adicione ao menos um item ativo antes de publicar.']);
        }
        $revision->update(['status' => $data['status'], 'review_notes' => $data['review_notes'] ?? null, 'reviewed_by' => $isReviewer ? $request->user()->id : $revision->reviewed_by,
            'published_at' => $data['status'] === 'published' ? ($revision->published_at ?: now()) : $revision->published_at]);

        return back()->with('status', 'Situação da revisão atualizada.');
    }

    public function report(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id) || $access->canReview($request->user(), $revision->organization_id), 403);
        $attempts = $revision->attempts()->with('student')->where('status', 'completed')->latest('completed_at')->paginate(30);
        $summary = ['completed' => $revision->attempts()->where('status', 'completed')->count(), 'in_progress' => $revision->attempts()->where('status', 'in_progress')->count(),
            'average' => (float) $revision->attempts()->where('status', 'completed')->avg('score'), 'xp' => (int) $revision->attempts()->where('status', 'completed')->sum('xp_earned')];

        return view('revisions.report', compact('revision', 'attempts', 'summary'));
    }

    private function form(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        return view('revisions.form', ['revision' => $revision->load(['schoolClasses', 'items', 'sources.source']), 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(),
            'canManage' => $access->canManage($request->user(), $revision->organization_id ?: $request->user()->organization_id, $revision->author_id ?: $request->user()->id),
            'canReview' => $access->canReview($request->user(), $revision->organization_id ?: $request->user()->organization_id)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:10000'], 'discipline_id' => ['nullable', 'integer', Rule::exists('disciplines', 'id')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id))],
            'is_required' => ['nullable', 'boolean'], 'timing' => ['required', 'in:before,after,independent'], 'block_exam' => ['nullable', 'boolean'], 'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:available_at'], 'max_attempts' => ['required', 'integer', 'min:1', 'max:20'], 'feedback_mode' => ['required', 'in:immediate,end,hidden'],
            'gamification_enabled' => ['nullable', 'boolean'], 'class_ids' => ['required', 'array', 'min:1'], 'class_ids.*' => ['integer']]);
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate(['type' => ['required', Rule::in(RevisionItem::TYPES)], 'prompt' => ['required', 'string', 'max:10000'], 'content_json' => ['nullable', 'json'],
            'solution_json' => ['required', 'json'], 'explanation' => ['nullable', 'string', 'max:10000'], 'hints_text' => ['nullable', 'string', 'max:5000'], 'difficulty' => ['required', 'integer', 'between:1,5'],
            'points' => ['required', 'numeric', 'min:0', 'max:1000'], 'is_active' => ['nullable', 'boolean']]);

        return ['type' => $data['type'], 'prompt' => $data['prompt'], 'content' => json_decode($data['content_json'] ?: '{}', true), 'solution' => json_decode($data['solution_json'], true),
            'explanation' => $data['explanation'] ?? null, 'hints' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['hints_text'] ?? '')))), 'difficulty' => $data['difficulty'],
            'points' => $data['points'], 'is_active' => $request->boolean('is_active')];
    }
}
