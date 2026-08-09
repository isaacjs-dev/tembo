<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Banco de Questões</span>
                </nav>
                <h1 class="page-title">Banco de Questões</h1>
            </div>
            <div class="flex flex-wrap gap-2">
            <a href="{{ route('question-resources.index') }}" class="btn-secondary">
                <span aria-hidden="true" class="material-symbols-outlined">collections_bookmark</span>
                Materiais de apoio
            </a>
            <a href="{{ route('questions.create') }}" class="btn-primary">
                <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                Nova Questão
            </a>
            </div>
        </div>
    </x-slot>

    <!-- Tabs -->
    <div class="grid grid-cols-2 gap-1 sm:grid-cols-4 sm:gap-2 mb-6 border-b-2 border-duo-border pb-0"
        aria-label="Visualizações do banco de questões">
        @php
            $tabs = [
                'mine' => ['label' => 'Minhas questões', 'short' => 'Minhas', 'icon' => 'person', 'count' => $counts['mine']],
                'shared' => ['label' => 'Compartilhadas comigo', 'short' => 'Comigo', 'icon' => 'group', 'count' => $counts['shared']],
                'institution' => ['label' => 'Institucionais', 'short' => 'Instituição', 'icon' => 'domain', 'count' => $counts['institution']],
                'platform' => ['label' => 'Públicas da plataforma', 'short' => 'Públicas', 'icon' => 'public', 'count' => $counts['platform']],
            ];
        @endphp
        @foreach($tabs as $key => $t)
            <a href="{{ route('questions.index', ['tab' => $key]) }}"
                @if($tab === $key) aria-current="page" @endif
                class="flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 px-2 sm:px-4 py-3 text-xs sm:text-sm text-center font-bold transition-colors border-b-2 -mb-[2px]
                    {{ $tab === $key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-400 hover:text-duo-heading hover:border-gray-300' }}">
                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">{{ $t['icon'] }}</span>
                <span class="sm:hidden">{{ $t['short'] }}</span>
                <span class="hidden sm:inline">{{ $t['label'] }}</span>
                <span class="sm:ml-1 px-2 py-0.5 rounded-full text-xs font-extrabold
                    {{ $tab === $key ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-400' }}">
                    {{ $t['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <!-- Search & Filters -->
    <form method="GET" action="{{ route('questions.index') }}" class="mb-6" x-data="{ showFilters: {{ request()->hasAny(['discipline_id', 'type', 'level', 'stage', 'grade', 'sort']) ? 'true' : 'false' }} }">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <!-- Search Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <div class="input-icon-wrapper flex-1">
                <div class="input-icon">
                    <span aria-hidden="true" class="material-symbols-outlined text-xl">search</span>
                </div>
                <label for="question-search" class="sr-only">Buscar pelo enunciado da questão</label>
                <input id="question-search" type="search" name="search" value="{{ request('search') }}"
                    class="input-field !py-2.5" placeholder="Buscar por texto do enunciado...">
            </div>
            <button type="submit" class="btn-primary btn-sm">
                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">search</span>
                Buscar
            </button>
            <button type="button" @click="showFilters = !showFilters"
                class="btn-ghost btn-sm border-2 border-duo-border"
                aria-controls="question-advanced-filters"
                :aria-expanded="showFilters.toString()"
                :class="showFilters ? 'text-primary border-primary/30 bg-primary/5' : ''">
                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">tune</span>
                Filtros
            </button>
            @if(request()->hasAny(['search', 'discipline_id', 'type', 'level', 'stage', 'grade', 'sort']))
                <a href="{{ route('questions.index', ['tab' => $tab]) }}" class="text-sm font-bold text-red-400 hover:text-red-500 whitespace-nowrap">
                    Limpar
                </a>
            @endif
        </div>

        <!-- Advanced Filters (collapsible) -->
        <div id="question-advanced-filters" x-show="showFilters" x-collapse
            class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-3 p-4 bg-white border-2 border-duo-border rounded-xl">
            @if($tab !== 'platform')
            <div>
                <label for="question-discipline-filter" class="input-label text-xs">Disciplina</label>
                <select id="question-discipline-filter" name="discipline_id" class="input-field !py-2" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach($disciplines as $disc)
                        <option value="{{ $disc->id }}" {{ request('discipline_id') == $disc->id ? 'selected' : '' }}>
                            {{ $disc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label for="question-type-filter" class="input-label text-xs">Tipo</label>
                <select id="question-type-filter" name="type" class="input-field !py-2" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="multiple_choice" {{ request('type') === 'multiple_choice' ? 'selected' : '' }}>Múltipla Escolha</option>
                    <option value="true_false" {{ request('type') === 'true_false' ? 'selected' : '' }}>Verdadeiro ou Falso</option>
                    <option value="essay" {{ request('type') === 'essay' ? 'selected' : '' }}>Dissertativa</option>
                </select>
            </div>
            <div>
                <label for="question-level-filter" class="input-label text-xs">Dificuldade</label>
                <select id="question-level-filter" name="level" class="input-field !py-2" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="very_easy" {{ request('level') === 'very_easy' ? 'selected' : '' }}>Muito Fácil</option>
                    <option value="easy" {{ request('level') === 'easy' ? 'selected' : '' }}>Fácil</option>
                    <option value="medium" {{ request('level') === 'medium' ? 'selected' : '' }}>Médio</option>
                    <option value="hard" {{ request('level') === 'hard' ? 'selected' : '' }}>Difícil</option>
                    <option value="very_hard" {{ request('level') === 'very_hard' ? 'selected' : '' }}>Muito Difícil</option>
                </select>
            </div>
            <div>
                <label for="question-stage-filter" class="input-label text-xs">Etapa</label>
                <select id="question-stage-filter" name="stage" class="input-field !py-2" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="ef_iniciais" @selected(request('stage') === 'ef_iniciais')>EF Iniciais</option>
                    <option value="ef_finais" @selected(request('stage') === 'ef_finais')>EF Finais</option>
                    <option value="em" @selected(request('stage') === 'em')>Ensino Médio</option>
                </select>
            </div>
            <div>
                <label for="question-grade-filter" class="input-label text-xs">Ano/série</label>
                <input id="question-grade-filter" name="grade" value="{{ request('grade') }}" class="input-field !py-2" maxlength="50" placeholder="Ex.: 6">
            </div>
            <div>
                <label for="question-sort-filter" class="input-label text-xs">Ordenação</label>
                <select id="question-sort-filter" name="sort" class="input-field !py-2" onchange="this.form.submit()">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Mais recentes</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Mais antigas</option>
                </select>
            </div>
        </div>
    </form>

    <!-- Questions List -->
    <div class="space-y-4 mb-12">
        @forelse($questions as $question)
            @php
                $isOwner = $question->owner_id === auth()->id();
                $isEditableOwner = $isOwner && $question->visibility_scope !== 'platform_public';
                $canAttachDirectly = (int) $question->organization_id === (int) auth()->user()->organization_id;
                $statement = $question->content['statement'] ?? 'Sem enunciado';
                $options = $question->content['options'] ?? [];
                $correctIdx = $question->content['correct_option'] ?? null;
                $levelLabels = [
                    'very_easy' => 'Muito Fácil', 'easy' => 'Fácil', 'medium' => 'Médio',
                    'hard' => 'Difícil', 'very_hard' => 'Muito Difícil',
                ];
                $levelColors = [
                    'very_easy' => 'badge-success', 'easy' => 'badge-success',
                    'medium' => 'badge-warning', 'hard' => 'badge-danger', 'very_hard' => 'badge-danger',
                ];
            @endphp
            <div class="card" x-data="{ expanded: false }">
                <!-- Card Header (sempre visível) -->
                <button type="button"
                    class="block w-full p-5 text-left hover:bg-gray-50/50 transition-colors"
                    @click="expanded = !expanded"
                    :aria-expanded="expanded.toString()"
                    aria-controls="question-details-{{ $question->id }}">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <!-- Badges -->
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="badge badge-neutral">
                                    @if($question->type === 'multiple_choice') Múltipla Escolha
                                    @elseif($question->type === 'true_false') V ou F
                                    @else Dissertativa @endif
                                </span>
                                @if($question->level)
                                    <span class="badge {{ $levelColors[$question->level] ?? 'badge-neutral' }}">
                                        {{ $levelLabels[$question->level] ?? $question->level }}
                                    </span>
                                @endif
                                @if($question->discipline)
                                    <span class="badge badge-info">{{ $question->discipline->name }}</span>
                                @endif
                                @if(!$isOwner)
                                    <span class="badge badge-primary">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[12px]">person</span>
                                        {{ $question->owner->name }}
                                    </span>
                                @endif
                            </div>

                            <!-- Statement preview -->
                            <p class="text-sm font-semibold text-duo-heading leading-snug" :class="expanded ? '' : 'line-clamp-2'">
                                {{ $statement }}
                            </p>

                            <!-- Meta info -->
                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-400 font-medium">
                                <span>{{ $question->created_at->format('d/m/Y') }}</span>
                                @if($question->type === 'multiple_choice')
                                    <span>{{ count($options) }} alternativas</span>
                                @endif
                                @if($question->knowledgeArea)
                                    <span>{{ $question->knowledgeArea->name }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Expand indicator -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span aria-hidden="true" class="material-symbols-outlined text-gray-400 transition-transform duration-200"
                                :class="expanded ? 'rotate-180' : ''">
                                expand_more
                            </span>
                        </div>
                    </div>
                </button>

                <!-- Expanded Content (preview + actions) -->
                <div id="question-details-{{ $question->id }}" x-show="expanded" x-collapse>
                    <div class="border-t-2 border-duo-border">
                        <!-- Preview das alternativas -->
                        @if($question->type === 'multiple_choice' && count($options) > 0)
                            <div class="px-5 py-4 bg-background-light">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Alternativas</p>
                                <div class="space-y-2">
                                    @foreach($options as $idx => $optionText)
                                        @if(!empty($optionText))
                                            <div class="flex items-center gap-3 p-3 rounded-xl border-2 text-sm
                                                {{ $correctIdx !== null && (int)$correctIdx === $idx
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-duo-border bg-white' }}">
                                                <span class="size-7 rounded-full border-2 flex items-center justify-center text-xs font-bold flex-shrink-0
                                                    {{ $correctIdx !== null && (int)$correctIdx === $idx
                                                        ? 'border-primary text-primary bg-primary/10'
                                                        : 'border-gray-300 text-gray-400' }}">
                                                    {{ chr(65 + $idx) }}
                                                </span>
                                                <span class="font-medium text-duo-text flex-1">{{ $optionText }}</span>
                                                @if($correctIdx !== null && (int)$correctIdx === $idx)
                                                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-lg">check_circle</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($question->type === 'true_false')
                            <div class="px-5 py-4 bg-background-light">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Alternativas</p>
                                <div class="flex gap-3">
                                    <div class="flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-duo-border bg-white text-sm font-medium">
                                        <span class="size-6 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-xs font-bold">V</span>
                                        Verdadeiro
                                    </div>
                                    <div class="flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-duo-border bg-white text-sm font-medium">
                                        <span class="size-6 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-xs font-bold">F</span>
                                        Falso
                                    </div>
                                </div>
                            </div>
                        @elseif($question->type === 'essay')
                            <div class="px-5 py-4 bg-background-light">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Tipo de Resposta</p>
                                <div class="flex items-center gap-2 text-sm text-gray-500 font-medium">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">edit_note</span>
                                    Resposta dissertativa (texto livre)
                                </div>
                            </div>
                        @endif

                        <!-- Actions Bar -->
                        <div class="px-5 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t-2 border-duo-border bg-white rounded-b-2xl" @click.stop>
                            <div class="flex items-center gap-3 text-xs text-gray-400 font-medium">
                                @if($question->stage)
                                    @php
                                        $stageLabels = ['ef_iniciais' => 'EF Iniciais', 'ef_finais' => 'EF Finais', 'em' => 'Ensino Médio'];
                                    @endphp
                                    <span class="flex items-center gap-1">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">school</span>
                                        {{ $stageLabels[$question->stage] ?? $question->stage }}
                                        @if($question->grade) — {{ $question->grade }}º @endif
                                    </span>
                                @endif
                                @if($isOwner)
                                    <span class="flex items-center gap-1">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">visibility</span>
                                        {{ $question->visibility_scope === 'org_public' ? 'Institucional' : ($question->visibility_scope === 'platform_public' ? 'Pública da plataforma' : ($question->visibility_scope === 'shared_specific' ? 'Compartilhada' : 'Privada')) }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-1">
                                {{-- Adicionar à Prova --}}
                                @if($canAttachDirectly)
                                <div class="relative" x-data="{ showExamPicker: false, points: 1 }" @click.away="showExamPicker = false">
                                    <button type="button" @click="showExamPicker = !showExamPicker"
                                        class="btn-primary btn-sm !px-3 !py-1.5 text-xs !border-b-2">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">assignment_add</span>
                                        Adicionar à Prova
                                    </button>

                                    {{-- Popover de seleção --}}
                                    <div x-show="showExamPicker" x-transition
                                        class="absolute right-0 bottom-full mb-2 w-72 bg-white rounded-xl shadow-float border-2 border-duo-border p-4 z-50">
                                        <p class="text-sm font-bold text-duo-heading mb-3">Selecionar Avaliação</p>

                                        @if($myExams->isEmpty())
                                            <p class="text-xs text-gray-400 mb-3">Você não tem avaliações. Crie uma primeiro.</p>
                                            <a href="{{ route('exams.create') }}" class="btn-primary btn-sm w-full justify-center text-xs">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">add</span>
                                                Nova Avaliação
                                            </a>
                                        @else
                                            <div class="space-y-2 max-h-48 overflow-y-auto mb-3">
                                                @foreach($myExams as $exam)
                                                    <form action="{{ route('exams.addQuestion', $exam->id) }}" method="POST"
                                                        class="flex items-center gap-2 group/exam">
                                                        @csrf
                                                        <input type="hidden" name="question_id" value="{{ $question->id }}">
                                                        <input type="hidden" name="points" :value="points">
                                                        <button type="submit"
                                                            class="flex-1 text-left px-3 py-2 rounded-lg text-sm font-medium hover:bg-primary/5 hover:text-primary transition-colors flex items-center justify-between">
                                                            <span class="truncate">{{ $exam->title }}</span>
                                                            <span class="badge {{ $exam->status === 'draft' ? 'badge-neutral' : 'badge-success' }} text-[10px] !py-0 !px-1.5 flex-shrink-0">
                                                                {{ $exam->status === 'draft' ? 'Rascunho' : 'Publicada' }}
                                                            </span>
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
                                            <div class="border-t-2 border-duo-border pt-3">
                                                <label class="text-xs font-bold text-gray-500 mb-1 block">Pontuação</label>
                                                <input type="number" x-model="points" min="0" step="0.5"
                                                    aria-label="Pontuação da questão na avaliação selecionada"
                                                    class="input-field !py-1.5 text-sm w-full" placeholder="1.0">
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @else
                                    <span class="text-xs font-semibold text-gray-500" title="Duplique para criar uma cópia privada no seu espaço antes de usar.">Duplique para usar</span>
                                @endif

                                @if($isEditableOwner)
                                    <a href="{{ route('questions.share', $question->id) }}"
                                        class="p-2 rounded-xl transition-all duration-150 text-gray-400 hover:text-blue-500 hover:bg-blue-50"
                                        title="Compartilhar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">share</span>
                                    </a>
                                    <a href="{{ route('questions.edit', $question->id) }}"
                                        class="btn-icon !p-2" title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <form action="{{ route('questions.destroy', $question->id) }}" method="POST"
                                        onsubmit="return confirm('Excluir esta questão?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 rounded-xl transition-all duration-150 text-gray-400 hover:text-red-500 hover:bg-red-50"
                                            title="Excluir">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('questions.duplicate', $question->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-secondary btn-sm !px-3 !py-1.5 text-xs" title="Duplicar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">content_copy</span>
                                        Duplicar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-16 text-center">
                <span aria-hidden="true" class="material-symbols-outlined text-6xl text-gray-300 mb-4 block">quiz</span>
                @if($tab === 'mine')
                    <h2 class="text-xl font-bold text-duo-heading">Nenhuma questão criada</h2>
                    <p class="text-gray-400 mt-2 max-w-md mx-auto">
                        Você ainda não criou nenhuma questão. Clique no botão acima para começar.
                    </p>
                    <a href="{{ route('questions.create') }}" class="btn-primary mt-6">
                        <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                        Criar Primeira Questão
                    </a>
                @elseif($tab === 'shared')
                    <h2 class="text-xl font-bold text-duo-heading">Nenhuma questão compartilhada</h2>
                    <p class="text-gray-400 mt-2 max-w-md mx-auto">
                        Outros professores ainda não compartilharam questões com você.
                    </p>
                @elseif($tab === 'institution')
                    <h2 class="text-xl font-bold text-duo-heading">Nenhuma questão pública</h2>
                    <p class="text-gray-400 mt-2 max-w-md mx-auto">
                        Não há questões públicas na instituição ainda.
                    </p>
                @else
                    <h2 class="text-xl font-bold text-duo-heading">Nenhuma questão pública da plataforma</h2>
                    <p class="text-gray-400 mt-2 max-w-md mx-auto">
                        Nenhuma questão moderada está disponível com os filtros selecionados.
                    </p>
                @endif
            </div>
        @endforelse

        @if($questions->hasPages())
            <div class="mt-6">
                {{ $questions->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
