<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Exam;
use App\Models\KnowledgeArea;
use App\Models\Question;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use App\Services\MonthlyUsageService;
use App\Services\QuestionResourceService;
use App\Services\RevisionBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $user_id = $user->id;
        $tab = $request->input('tab', 'mine');

        $organizationId = $this->currentOrganizationId();

        $query = Question::where('organization_id', $organizationId)
            ->with(['owner', 'discipline', 'knowledgeArea']);

        // Filtro por aba
        if ($tab === 'shared') {
            $query->where('owner_id', '!=', $user_id)
                ->whereHas('shares', fn ($sq) => $sq->where('shared_with_user_id', $user_id));
        } elseif ($tab === 'public') {
            $query->where('owner_id', '!=', $user_id)
                ->where('visibility_scope', 'org_public');
        } else {
            // "mine" (default)
            $query->where('owner_id', $user_id);
        }

        // Busca por texto no enunciado
        if ($search = $request->input('search')) {
            $query->where('content', 'like', '%'.$search.'%');
        }

        // Filtro por disciplina
        if ($discipline = $request->input('discipline_id')) {
            $query->where('discipline_id', $discipline);
        }

        // Filtro por tipo
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filtro por dificuldade
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        $questions = $query->latest()->paginate(12)->withQueryString();

        // Dados para os filtros
        $disciplines = Discipline::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        // Provas do usuário (para "Adicionar à Prova")
        $myExams = Exam::where('author_id', $user_id)
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['draft', 'published'])
            ->select('id', 'title', 'status')
            ->latest()
            ->get();

        // Contadores por aba (sem filtros de busca)
        $baseQuery = Question::where('organization_id', $organizationId);
        $counts = [
            'mine' => (clone $baseQuery)->where('owner_id', $user_id)->count(),
            'shared' => (clone $baseQuery)->where('owner_id', '!=', $user_id)
                ->whereHas('shares', fn ($sq) => $sq->where('shared_with_user_id', $user_id))->count(),
            'public' => (clone $baseQuery)->where('owner_id', '!=', $user_id)
                ->where('visibility_scope', 'org_public')->count(),
        ];

        return view('questions.index', compact('questions', 'disciplines', 'tab', 'counts', 'myExams'));
    }

    public function create(QuestionResourceService $resources)
    {
        $organizationId = $this->currentOrganizationId();
        $knowledgeAreas = KnowledgeArea::where('organization_id', $organizationId)->orderBy('name')->get();
        $disciplines = Discipline::where('organization_id', $organizationId)->orderBy('name')->get();

        $availableResources = $resources->visibleTo(auth()->user())
            ->with('currentVersion:id,question_resource_id,version_number')
            ->orderBy('title')
            ->get();

        return view('questions.create', compact('knowledgeAreas', 'disciplines', 'availableResources'));
    }

    public function store(
        Request $request,
        MonthlyUsageService $usage,
        RevisionBuilderService $revisions,
        QuestionResourceService $resources,
    ) {
        $orgId = $this->currentOrganizationId();
        $validated = $this->validateQuestion($request, $orgId);
        $content = $this->buildQuestionContent($validated);

        $question = DB::transaction(function () use ($validated, $content, $orgId, $usage, $resources) {
            $question = Question::create([
                'organization_id' => $orgId,
                'owner_id' => auth()->id(),
                'type' => $validated['type'],
                'visibility_scope' => $validated['visibility_scope'],
                'content' => $content,
                'knowledge_area_id' => $validated['knowledge_area_id'] ?? null,
                'discipline_id' => $validated['discipline_id'] ?? null,
                'level' => $validated['level'] ?? null,
                'stage' => $validated['stage'],
                'grade' => $validated['grade'],
            ]);

            if (isset($validated['bncc_skills'])) {
                $question->bnccSkills()->sync($validated['bncc_skills']);
            }
            if (isset($validated['custom_skills'])) {
                $question->customSkills()->sync($validated['custom_skills']);
            }
            $resources->syncQuestion($question, $validated['resource_ids'] ?? [], auth()->user());

            $usage->consume(auth()->user(), MonthlyUsageService::QUESTIONS_CREATED, 1, "question:create:{$question->id}", $question, auth()->user());

            return $question;
        });

        if ($request->boolean('generate_review')) {
            $revision = $revisions->createDraft($question, auth()->user());

            return redirect()->route('revisions.edit', $revision)
                ->with('status', 'Questão criada e rascunho de revisão preparado. Escolha as turmas antes de publicar.');
        }

        return redirect()->route('questions.index')->with('status', 'Questão criada com sucesso!');
    }

    public function edit(string $id, QuestionResourceService $resources)
    {
        $organizationId = $this->currentOrganizationId();
        $question = Question::where('organization_id', $organizationId)
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        $knowledgeAreas = KnowledgeArea::where('organization_id', $organizationId)->orderBy('name')->get();
        $disciplines = Discipline::where('organization_id', $organizationId)->orderBy('name')->get();
        $question->load([
            'resources:id,title,type',
            'resourceLinks.resource:id,title,type,status,deleted_at',
        ]);
        $availableResources = $resources->visibleTo(auth()->user())
            ->with('currentVersion:id,question_resource_id,version_number')
            ->orderBy('title')
            ->get();

        return view('questions.edit', compact('question', 'knowledgeAreas', 'disciplines', 'availableResources'));
    }

    public function update(Request $request, string $id, QuestionResourceService $resources)
    {
        $organizationId = $this->currentOrganizationId();
        $question = Question::where('organization_id', $organizationId)
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        $validated = $this->validateQuestion($request, $organizationId);
        $content = $this->buildQuestionContent($validated);

        DB::transaction(function () use ($question, $validated, $content, $resources): void {
            $question->update([
                'type' => $validated['type'],
                'visibility_scope' => $validated['visibility_scope'],
                'content' => $content,
                'knowledge_area_id' => $validated['knowledge_area_id'] ?? null,
                'discipline_id' => $validated['discipline_id'] ?? null,
                'level' => $validated['level'] ?? null,
                'stage' => $validated['stage'],
                'grade' => $validated['grade'],
            ]);

            $question->bnccSkills()->sync($validated['bncc_skills'] ?? []);
            $question->customSkills()->sync($validated['custom_skills'] ?? []);
            $resources->syncQuestion($question, $validated['resource_ids'] ?? [], auth()->user());
            $resources->assertQuestionLinksCompatible($question, $validated['visibility_scope']);
        });

        return redirect()->route('questions.index')->with('status', 'Questão atualizada com sucesso!');
    }

    public function share(string $id)
    {
        // Check plan feature for sharing
        if (! auth()->user()->hasFeature('sharing')) {
            return back()->withErrors(['O plano atual não permite compartilhamento de questões.']);
        }

        $organizationId = $this->currentOrganizationId();
        $question = Question::where('organization_id', $organizationId)
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        $teachers = User::query()
            ->where('status', 'active')
            ->memberOfOrganization($organizationId, 'teacher')
            ->where('id', '!=', auth()->id())
            ->get();

        $sharedWithIds = $question->shares()->pluck('shared_with_user_id')->toArray();

        return view('questions.share', compact('question', 'teachers', 'sharedWithIds'));
    }

    public function storeShare(Request $request, string $id, QuestionResourceService $resources)
    {
        $organizationId = $this->currentOrganizationId();
        $question = Question::where('organization_id', $organizationId)
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => ['integer', 'distinct', new ActiveOrganizationMember($organizationId, 'teacher')],
        ]);

        $teacherIds = collect($validated['teacher_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $visibility = $teacherIds->isNotEmpty()
            ? 'shared_specific'
            : ($question->visibility_scope === 'shared_specific' ? 'private' : $question->visibility_scope);
        $resources->assertQuestionLinksCompatible($question, $visibility, $teacherIds);

        DB::transaction(function () use ($question, $teacherIds, $visibility): void {
            $question->shares()->delete();
            $question->update(['visibility_scope' => $visibility]);
            $question->shares()->createMany(
                $teacherIds->map(fn ($id) => ['shared_with_user_id' => $id])->all()
            );
        });

        return redirect()->route('questions.index')->with('status', 'Opções de compartilhamento atualizadas!');
    }

    public function destroy(string $id)
    {
        $organizationId = $this->currentOrganizationId();
        $question = Question::where('organization_id', $organizationId)
            ->where('owner_id', auth()->id())
            ->findOrFail($id);

        $question->delete();

        return redirect()->route('questions.index')->with('status', 'Questão excluída.');
    }

    public function duplicate(string $id, MonthlyUsageService $usage, QuestionResourceService $resources)
    {
        // Pega a questão original (mesmo a organização ou se é pública)
        $organization_id = $this->currentOrganizationId();
        $user_id = auth()->id();

        $original = Question::where('organization_id', $organization_id)
            ->where(function ($q) use ($user_id) {
                $q->where('owner_id', $user_id)
                    ->orWhere('visibility_scope', 'org_public')
                    ->orWhereHas('shares', function ($sq) use ($user_id) {
                        $sq->where('shared_with_user_id', $user_id);
                    });
            })
            ->with(['bnccSkills:id', 'customSkills:id', 'resourceLinks.version'])
            ->findOrFail($id);

        $resourceIds = $original->resourceLinks->pluck('question_resource_id')->unique();
        if ($resourceIds->isNotEmpty()
            && $resources->visibleTo(auth()->user())->whereKey($resourceIds)->count() !== $resourceIds->count()) {
            throw ValidationException::withMessages([
                'resource_ids' => 'A questão possui material de apoio que não está acessível para duplicação.',
            ]);
        }
        if ($original->resourceLinks->contains(fn ($link): bool => ! $link->version
            || (int) $link->version->question_resource_id !== (int) $link->question_resource_id)) {
            throw ValidationException::withMessages([
                'resource_ids' => 'A questão possui um vínculo histórico de recurso inconsistente.',
            ]);
        }

        $copy = DB::transaction(function () use ($original, $organization_id, $user_id, $usage) {
            // Preserve every taxonomy column, including fields added in future migrations.
            $copy = $original->replicate([
                'organization_id',
                'owner_id',
                'visibility_scope',
                'source_question_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
            $copy->organization_id = $organization_id;
            $copy->owner_id = $user_id;
            $copy->visibility_scope = 'private';
            $copy->source_question_id = $original->id;
            $copy->save();

            $copy->bnccSkills()->sync($original->bnccSkills->modelKeys());
            $copy->customSkills()->sync($original->customSkills->modelKeys());
            foreach ($original->resourceLinks as $link) {
                $copy->resources()->attach($link->question_resource_id, [
                    'question_resource_version_id' => $link->question_resource_version_id,
                    'is_required' => $link->is_required,
                    'sort_order' => $link->sort_order,
                ]);
            }
            $usage->consume(auth()->user(), MonthlyUsageService::QUESTIONS_CREATED, 1, "question:create:{$copy->id}", $copy, auth()->user(), ['source_question_id' => $original->id]);

            return $copy;
        });

        return redirect()->route('questions.edit', $copy->id)->with('status', 'Questão duplicada! Você agora pode editá-la de forma independente.');
    }

    /**
     * Validate common metadata and the structure required by each question type.
     */
    private function validateQuestion(Request $request, int $organizationId): array
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:multiple_choice,true_false,essay'],
            'visibility_scope' => ['required', 'in:private,shared_specific,org_public'],
            'resource_ids' => ['nullable', 'array', 'max:20'],
            'resource_ids.*' => ['integer', 'distinct'],
            'statement' => ['required', 'string', 'max:50000'],
            'options' => ['nullable', 'array', 'max:5'],
            'options.*' => ['nullable', 'string', 'max:5000'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:4'],
            'tf_answer' => ['nullable', 'integer', 'in:0,1'],
            'knowledge_area_id' => [
                'nullable',
                Rule::exists('knowledge_areas', 'id')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'discipline_id' => [
                'nullable',
                Rule::exists('disciplines', 'id')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'level' => ['nullable', 'string', 'in:very_easy,easy,medium,hard,very_hard'],
            'stage' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'string', 'max:50'],
            'bncc_skills' => ['nullable', 'array'],
            'bncc_skills.*' => [
                'integer',
                'distinct',
                Rule::exists('bncc_nodes', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'discipline_id',
                        DB::table('disciplines')
                            ->select('id')
                            ->where('organization_id', $organizationId),
                    )
                ),
            ],
            'custom_skills' => ['nullable', 'array'],
            'custom_skills.*' => [
                'integer',
                'distinct',
                Rule::exists('custom_skills', 'id')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'rubric_title' => ['nullable', 'string', 'max:255'],
            'rubric_description' => ['nullable', 'string', 'max:2000'],
            'rubric_criteria' => ['nullable', 'array', 'max:10'],
            'rubric_criteria.*' => ['array'],
            'rubric_criteria.*.title' => ['nullable', 'string', 'max:255'],
            'rubric_criteria.*.description' => ['nullable', 'string', 'max:2000'],
            'rubric_criteria.*.points' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
        ], [
            'options.max' => 'Uma questão pode ter no máximo cinco alternativas.',
            'rubric_criteria.max' => 'Uma rubrica pode ter no máximo dez critérios.',
            'rubric_criteria.*.points.gt' => 'A pontuação do critério deve ser maior que zero.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = $request->input('type');

            if ($type === 'multiple_choice') {
                $options = $request->input('options', []);
                $filledOptions = collect(is_array($options) ? $options : [])
                    ->filter(fn ($option) => filled($option));

                if ($filledOptions->count() < 2) {
                    $validator->errors()->add(
                        'options',
                        'Informe pelo menos duas alternativas para a questão de múltipla escolha.'
                    );
                }

                $correctOption = $request->input('correct_option');
                if ($correctOption === null
                    || ! array_key_exists((int) $correctOption, is_array($options) ? $options : [])
                    || blank($options[(int) $correctOption] ?? null)) {
                    $validator->errors()->add(
                        'correct_option',
                        'Selecione uma alternativa preenchida como resposta correta.'
                    );
                }
            }

            if ($type === 'true_false' && ! in_array((string) $request->input('tf_answer'), ['0', '1'], true)) {
                $validator->errors()->add(
                    'tf_answer',
                    'Selecione Verdadeiro ou Falso como resposta correta.'
                );
            }

            if ($type !== 'essay') {
                return;
            }

            $criteria = $request->input('rubric_criteria', []);
            $completeCriteria = 0;

            foreach (is_array($criteria) ? $criteria : [] as $index => $criterion) {
                if (! is_array($criterion)) {
                    continue;
                }

                $hasAnyValue = filled($criterion['title'] ?? null)
                    || filled($criterion['description'] ?? null)
                    || filled($criterion['points'] ?? null);

                if (! $hasAnyValue) {
                    continue;
                }

                if (blank($criterion['title'] ?? null)) {
                    $validator->errors()->add(
                        "rubric_criteria.{$index}.title",
                        'Informe o título do critério da rubrica.'
                    );
                }

                if (blank($criterion['points'] ?? null)) {
                    $validator->errors()->add(
                        "rubric_criteria.{$index}.points",
                        'Informe a pontuação máxima do critério da rubrica.'
                    );
                }

                if (filled($criterion['title'] ?? null) && filled($criterion['points'] ?? null)) {
                    $completeCriteria++;
                }
            }

            if ((filled($request->input('rubric_title')) || filled($request->input('rubric_description')))
                && $completeCriteria === 0) {
                $validator->errors()->add(
                    'rubric_criteria',
                    'Adicione ao menos um critério completo à rubrica.'
                );
            }
        });

        return $validator->validate();
    }

    /** Resolve the legacy current context without ever guessing another tenant. */
    private function currentOrganizationId(): int
    {
        $user = auth()->user();
        $organizationId = (int) $user->organization_id;

        abort_unless(
            $organizationId > 0 && $user->canUseOrganizationContext($organizationId),
            403,
            'Selecione uma instituição ativa antes de continuar.',
        );

        return $organizationId;
    }

    /**
     * Normalize type-specific input into the question content JSON.
     */
    private function buildQuestionContent(array $validated): array
    {
        $content = [
            'statement' => trim($validated['statement']),
        ];

        if ($validated['type'] === 'multiple_choice') {
            $options = [];
            $correctOption = null;

            foreach ($validated['options'] ?? [] as $originalIndex => $option) {
                if (blank($option)) {
                    continue;
                }

                if ((int) $originalIndex === (int) $validated['correct_option']) {
                    $correctOption = count($options);
                }

                $options[] = trim($option);
            }

            $content['options'] = $options;
            $content['correct_option'] = $correctOption;
        } elseif ($validated['type'] === 'true_false') {
            $content['options'] = ['Verdadeiro', 'Falso'];
            $content['correct_option'] = (int) $validated['tf_answer'];
        } else {
            $criteria = [];

            foreach ($validated['rubric_criteria'] ?? [] as $criterion) {
                $hasAnyValue = filled($criterion['title'] ?? null)
                    || filled($criterion['description'] ?? null)
                    || filled($criterion['points'] ?? null);

                if (! $hasAnyValue) {
                    continue;
                }

                $criteria[] = [
                    'title' => trim($criterion['title']),
                    'description' => filled($criterion['description'] ?? null)
                        ? trim($criterion['description'])
                        : null,
                    'points' => (float) $criterion['points'],
                ];
            }

            if ($criteria !== []) {
                $content['rubric'] = [
                    'title' => filled($validated['rubric_title'] ?? null)
                        ? trim($validated['rubric_title'])
                        : 'Rubrica de correção',
                    'description' => filled($validated['rubric_description'] ?? null)
                        ? trim($validated['rubric_description'])
                        : null,
                    'criteria' => $criteria,
                ];
            }
        }

        return $content;
    }
}
