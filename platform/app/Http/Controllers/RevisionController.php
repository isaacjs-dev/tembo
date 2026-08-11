<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Revision;
use App\Models\RevisionItem;
use App\Services\PedagogicalAccessService;
use App\Services\RevisionImportService;
use App\Services\RevisionPromptService;
use App\Services\RevisionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RevisionController extends Controller
{
    public function index(Request $request, PedagogicalAccessService $access): View
    {
        $revisions = Revision::query()->where('organization_id', $request->user()->organization_id)
            ->when($access->shouldScopeToAuthor($request->user(), (int) $request->user()->organization_id)
                && ! $access->canReview($request->user(), $request->user()->organization_id), fn ($q) => $q->where('author_id', $request->user()->id))
            ->with(['author', 'discipline', 'schoolClasses'])->withCount(['items', 'attempts'])->latest()->paginate(15);

        return view('revisions.index', compact('revisions'));
    }

    public function create(Request $request, PedagogicalAccessService $access): View
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);

        return $this->form($request, new Revision, $access);
    }

    public function edit(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id) || $access->canReview($request->user(), $revision->organization_id), 403);

        return $this->form($request, $revision, $access);
    }

    public function store(Request $request, PedagogicalAccessService $access): RedirectResponse
    {
        abort_unless($access->canCreate($request->user(), (int) $request->user()->organization_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $revision = Revision::create([...collect($data)->except('class_ids')->all(), 'organization_id' => $request->user()->organization_id, 'author_id' => $request->user()->id,
            'status' => 'draft', 'published_at' => null]);
        $revision->schoolClasses()->sync($classIds);

        return redirect()->route('revisions.edit', $revision)->with('status', 'Revisão criada. Adicione itens ou importe o JSON da IA.');
    }

    public function update(Request $request, Revision $revision, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $this->validated($request);
        $classIds = $access->validateClassIds($request->user(), $data['class_ids']);
        $workflow->mutate($revision, function (Revision $locked) use ($data, $classIds): void {
            $locked->update(collect($data)->except(['class_ids', 'status'])->all());
            $locked->schoolClasses()->sync($classIds);
        });

        return back()->with('status', 'Configurações atualizadas para todos os alunos.');
    }

    public function destroy(Request $request, Revision $revision, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $workflow->mutate($revision, fn (Revision $locked) => $locked->delete());

        return redirect()->route('revisions.index')->with('status', 'Revisão removida.');
    }

    public function storeItem(Request $request, Revision $revision, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $this->itemData($request);
        $workflow->mutate($revision, fn (Revision $locked) => $locked->items()->create([
            ...$data,
            'order' => (int) ($locked->items()->max('order') ?? -1) + 1,
            'updated_by' => $request->user()->id,
        ]));

        return back()->with('status', 'Item adicionado.');
    }

    public function updateItem(Request $request, Revision $revision, RevisionItem $item, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        abort_unless((int) $item->revision_id === (int) $revision->id && $access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $this->itemData($request);
        $workflow->mutate($revision, function (Revision $locked) use ($item, $data, $request): void {
            $lockedItem = $locked->items()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! empty($lockedItem->content['resources'])) {
                $data['content']['resources'] = $lockedItem->content['resources'];
            }
            $lockedItem->update([...$data, 'updated_by' => $request->user()->id]);
        });

        return back()->with('status', 'Item atualizado. A nova versão será usada somente em tentativas futuras.');
    }

    public function destroyItem(Request $request, Revision $revision, RevisionItem $item, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        abort_unless((int) $item->revision_id === (int) $revision->id && $access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $workflow->mutate($revision, fn (Revision $locked) => $locked->items()->whereKey($item->id)->firstOrFail()->delete());

        return back()->with('status', 'Item removido.');
    }

    public function reorder(Request $request, Revision $revision, PedagogicalAccessService $access, RevisionWorkflowService $workflow): JsonResponse
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id), 403);
        $data = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);
        $workflow->mutate($revision, function (Revision $locked) use ($data): void {
            abort_unless($locked->items()->whereIn('id', $data['items'])->count() === count(array_unique($data['items'])), 403);
            collect($data['items'])->each(fn ($id, $order) => $locked->items()->whereKey($id)->update(['order' => $order]));
        });

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

    public function status(Request $request, Revision $revision, PedagogicalAccessService $access, RevisionWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['in_review', 'changes_requested', 'published', 'suspended'])], 'review_notes' => ['nullable', 'string', 'max:5000']]);
        $isAuthor = $access->canManage($request->user(), $revision->organization_id, $revision->author_id);
        $isReviewer = $access->canReview($request->user(), $revision->organization_id);
        $workflow->transition($revision, $request->user(), $data['status'], $data['review_notes'] ?? null, $isAuthor, $isReviewer);

        return back()->with('status', 'Situação da revisão atualizada.');
    }

    public function report(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        abort_unless($access->canManage($request->user(), $revision->organization_id, $revision->author_id) || $access->canReview($request->user(), $revision->organization_id), 403);
        $attempts = $revision->attempts()->with(['student' => fn ($query) => $query->withTrashed()])
            ->where('status', 'completed')->latest('completed_at')->paginate(30);
        $summary = ['completed' => $revision->attempts()->where('status', 'completed')->count(), 'in_progress' => $revision->attempts()->where('status', 'in_progress')->count(),
            'average' => (float) $revision->attempts()->where('status', 'completed')->avg('score'), 'xp' => (int) $revision->attempts()->where('status', 'completed')->sum('xp_earned')];

        return view('revisions.report', compact('revision', 'attempts', 'summary'));
    }

    private function form(Request $request, Revision $revision, PedagogicalAccessService $access): View
    {
        $revision->load(['schoolClasses', 'items', 'sources.source']);
        $revision->setAttribute('attempts_count', $revision->exists ? $revision->attempts()->count() : 0);
        $organizationId = (int) ($revision->organization_id ?: $request->user()->organization_id);
        $canManage = $access->canManage($request->user(), $organizationId, (int) ($revision->author_id ?: $request->user()->id));
        $canReview = $access->canReview($request->user(), $organizationId);
        $independentReviewer = $canReview && (int) $request->user()->id !== (int) $revision->author_id;
        $statusTransitions = [];
        if ($canManage && in_array($revision->status, ['draft', 'changes_requested'], true)) {
            $statusTransitions['in_review'] = 'Enviar para revisão';
        }
        if (($revision->status === 'draft' && ($canManage || $canReview))
            || (in_array($revision->status, ['in_review', 'suspended'], true) && $independentReviewer)) {
            $statusTransitions['published'] = 'Publicar';
        }
        if ($independentReviewer && $revision->status === 'in_review') {
            $statusTransitions['changes_requested'] = 'Devolver para ajustes';
        }
        if ($canReview && $revision->status === 'published') {
            $statusTransitions['suspended'] = 'Suspender';
        }

        return view('revisions.form', ['revision' => $revision, 'classes' => $access->classesFor($request->user()),
            'disciplines' => Discipline::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(),
            'canManage' => $canManage, 'canReview' => $canReview, 'statusTransitions' => $statusTransitions,
            'hasAttempts' => (int) $revision->attempts_count > 0]);
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

        $content = json_decode($data['content_json'] ?: '{}', true);
        $solution = json_decode($data['solution_json'], true);
        if (! is_array($content) || ! is_array($solution)) {
            throw ValidationException::withMessages(['solution_json' => 'Conteudo e solucao precisam ser objetos JSON.']);
        }
        unset($content['resources']);
        $errors = $this->semanticItemErrors($data['type'], $content, $solution);
        if ($errors !== []) {
            throw ValidationException::withMessages(['solution_json' => $errors]);
        }

        return ['type' => $data['type'], 'prompt' => $data['prompt'], 'content' => $content, 'solution' => $solution,
            'explanation' => $data['explanation'] ?? null, 'hints' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['hints_text'] ?? '')))), 'difficulty' => $data['difficulty'],
            'points' => $data['points'], 'is_active' => $request->boolean('is_active')];
    }

    /** @return array<int, string> */
    private function semanticItemErrors(string $type, array $content, array $solution): array
    {
        $options = $content['options'] ?? [];
        $correct = $solution['correct_option'] ?? null;

        return match ($type) {
            'multiple_choice' => ! is_array($options) || count($options) < 2 || ! is_numeric($correct)
                || (int) $correct < 0 || (int) $correct >= count($options)
                ? ['Multipla escolha requer ao menos duas opcoes e alternativa correta valida.'] : [],
            'true_false' => ! in_array((string) $correct, ['0', '1'], true)
                ? ['Verdadeiro/falso requer alternativa correta 0 ou 1.'] : [],
            'matching' => count($content['left'] ?? []) < 2 || count($content['right'] ?? []) < 2 || empty($solution['pairs'])
                ? ['Associacao requer duas listas e pares no gabarito.'] : [],
            'fill_blank', 'short_answer' => empty($solution['accepted_answers'])
                ? ['Este tipo requer respostas aceitas.'] : [],
            'ordering' => count($content['items'] ?? []) < 2 || empty($solution['order'])
                ? ['Ordenacao requer itens e ordem correta.'] : [],
            'flashcard' => blank($content['front'] ?? null) || blank($content['back'] ?? null)
                ? ['Flashcard requer frente e verso.'] : [],
            default => [],
        };
    }
}
