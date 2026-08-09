<?php

namespace App\Http\Controllers;

use App\Models\BNCcNode;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\LearningMaterial;
use App\Models\SchoolClass;
use App\Services\RevisionBuilderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearningMaterialController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LearningMaterial::class);
        $user = $request->user();
        $organizationId = $this->organizationId($request);

        $materials = LearningMaterial::query()
            ->where('organization_id', $organizationId)
            ->when(
                $user->type === 'teacher',
                fn (Builder $query) => $query->where('author_id', $user->id),
            )
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with(['author:id,name', 'discipline:id,name', 'schoolClasses:id,name'])
            ->withCount([
                'progressRecords as opened_count',
                'progressRecords as completed_count' => fn (Builder $query) => $query
                    ->where('status', 'completed'),
            ])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('learning-materials.index', compact('materials'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', LearningMaterial::class);

        return view('learning-materials.create', $this->formOptions($request));
    }

    public function store(Request $request, RevisionBuilderService $revisions): RedirectResponse
    {
        Gate::authorize('create', LearningMaterial::class);
        $validated = $this->validated($request);
        $organizationId = $this->organizationId($request);

        $material = DB::transaction(function () use ($request, $validated, $organizationId): LearningMaterial {
            $classIds = $validated['class_ids'] ?? [];
            unset($validated['class_ids']);

            $material = LearningMaterial::query()->create([
                ...$validated,
                'organization_id' => $organizationId,
                'author_id' => $request->user()->id,
            ]);
            $material->schoolClasses()->sync($classIds);

            return $material;
        });

        if ($request->boolean('generate_review')) {
            $revision = $revisions->createDraft(
                $material,
                $request->user(),
                $material->schoolClasses()->pluck('school_classes.id')->all(),
            );

            return redirect()->route('revisions.edit', $revision)
                ->with('status', 'Material criado e rascunho de revisão preparado.');
        }

        return redirect()
            ->route('learning-materials.edit', $material)
            ->with('status', 'Material de estudo criado com sucesso.');
    }

    public function edit(Request $request, LearningMaterial $learningMaterial): View
    {
        Gate::authorize('update', $learningMaterial);
        $learningMaterial->load('schoolClasses:id');

        return view('learning-materials.edit', [
            'material' => $learningMaterial,
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, LearningMaterial $learningMaterial): RedirectResponse
    {
        Gate::authorize('update', $learningMaterial);
        $validated = $this->validated($request);

        DB::transaction(function () use ($learningMaterial, $validated): void {
            $classIds = $validated['class_ids'] ?? [];
            unset($validated['class_ids']);

            $learningMaterial->update($validated);
            $learningMaterial->schoolClasses()->sync($classIds);
        });

        return redirect()
            ->route('learning-materials.edit', $learningMaterial)
            ->with('status', 'Material de estudo atualizado.');
    }

    public function destroy(LearningMaterial $learningMaterial): RedirectResponse
    {
        Gate::authorize('delete', $learningMaterial);
        $learningMaterial->delete();

        return redirect()
            ->route('learning-materials.index')
            ->with('status', 'Material enviado para a lixeira.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $organizationId = $this->organizationId($request);

        return [
            'classes' => $this->manageableClasses($request)->orderBy('name')->get(['id', 'name', 'year']),
            'disciplines' => Discipline::query()
                ->where('organization_id', $organizationId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'customSkills' => CustomSkill::query()
                ->where('organization_id', $organizationId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'bnccNodes' => BNCcNode::query()
                ->whereHas('discipline', fn (Builder $query) => $query->where('organization_id', $organizationId))
                ->where('is_active', true)
                ->orderBy('code')
                ->orderBy('title')
                ->get(['id', 'code', 'title', 'discipline_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $organizationId = $this->organizationId($request);
        $manageableClassIds = $this->manageableClasses($request)->pluck('school_classes.id')->all();
        $disciplineIds = Discipline::query()
            ->where('organization_id', $organizationId)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'discipline_id' => [
                'nullable',
                'integer',
                Rule::exists('disciplines', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'custom_skill_id' => [
                'nullable',
                'integer',
                Rule::exists('custom_skills', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'bncc_node_id' => [
                'nullable',
                'integer',
                Rule::exists('bncc_nodes', 'id')->where(
                    fn ($query) => $query->whereIn('discipline_id', $disciplineIds)
                ),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', Rule::in($manageableClassIds)],
        ]);

        if (blank($validated['body'] ?? null) && blank($validated['external_url'] ?? null)) {
            throw ValidationException::withMessages([
                'body' => 'Informe um conteúdo de estudo ou um link externo seguro.',
            ]);
        }

        if (
            ($validated['status'] ?? 'draft') === 'published'
            && empty($validated['class_ids'])
        ) {
            throw ValidationException::withMessages([
                'class_ids' => 'Selecione ao menos uma turma antes de publicar.',
            ]);
        }

        return $validated;
    }

    private function manageableClasses(Request $request): Builder
    {
        $user = $request->user();
        $query = SchoolClass::query()
            ->where('organization_id', $this->organizationId($request));

        if ($user->type === 'teacher') {
            $query->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('owner_type', 'user')->where('owner_id', $user->id);
                })->orWhereHas('teachers', fn (Builder $query) => $query->where('users.id', $user->id));
            });
        }

        return $query;
    }

    private function organizationId(Request $request): int
    {
        $organizationId = $request->user()->organization_id;
        abort_if(! $organizationId, 403, 'Selecione uma organização para gerenciar materiais.');

        return (int) $organizationId;
    }
}
