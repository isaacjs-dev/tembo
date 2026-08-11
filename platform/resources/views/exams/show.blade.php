<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('exams.index') }}">Avaliações</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Resultados</span>
                </nav>
                <h1 class="page-title">Resultados: {{ $exam->title }}</h1>
                @if($exam->discipline)
                    <p class="mt-1 text-sm font-semibold text-gray-600">{{ $exam->discipline->name }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if($exam->access_code)
                    <span class="badge badge-neutral flex items-center gap-1" title="Código de Acesso">
                        <span class="material-symbols-outlined text-[18px]">key</span>
                        {{ $exam->access_code }}
                    </span>
                @endif
                <a href="{{ route('exams.index') }}" class="btn-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl font-bold">
            {{ session('status') }}
        </div>
    @endif

    <!-- Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <h3 class="text-gray-500 font-bold text-sm mb-1 uppercase tracking-wider">Total de Alunos</h3>
            @php
                $totalStudents = $audienceStudents->count();
            @endphp
            <p class="stat-card-value">{{ $totalStudents }}</p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-duo-border p-6 shadow-sm">
            <h3 class="text-gray-500 font-bold text-sm mb-1 uppercase tracking-wider">Entregues</h3>
            <p class="text-3xl font-extrabold text-primary">
                {{ $submissions->whereIn('status', ['submitted', 'graded'])->count() }}
            </p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-duo-border p-6 shadow-sm">
            <h3 class="text-gray-500 font-bold text-sm mb-1 uppercase tracking-wider">Aguardando Correção</h3>
            <p class="text-3xl font-extrabold text-yellow-500">{{ $submissions->where('status', 'submitted')->count() }}
            </p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-duo-border p-6 shadow-sm">
            <h3 class="text-gray-500 font-bold text-sm mb-1 uppercase tracking-wider">Média da Turma</h3>
            @php
                $graded = $submissions->where('status', 'graded');
                $avg = $graded->count() > 0 ? $graded->avg('score') : 0;
            @endphp
            <p class="text-3xl font-extrabold text-blue-500">{{ number_format($avg, 1) }}</p>
        </div>
    </div>

    <!-- Cartão-Resposta (OMR) -->
    <div class="bg-white rounded-2xl border-2 border-duo-border shadow-sm p-6 mb-8">
        <h2 class="text-lg font-extrabold text-duo-heading flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-primary">qr_code_2</span> Cartão-Resposta (OMR)
        </h2>
        <p class="text-sm text-gray-500 font-medium mb-4">
            Gere a folha de respostas para leitura automática. O cartão se adapta ao número de questões da prova.
        </p>
        @if($exam->questions->count() === 0)
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
                Adicione questões à prova antes de gerar o cartão-resposta.
            </div>
        @else
            <form action="{{ route('exams.exportAnswerSheet', $exam->id) }}" method="POST" target="_blank"
                class="flex flex-wrap items-end gap-4">
                @csrf
                <div class="flex-1 min-w-[220px]">
                    <label class="input-label">Template do cartão</label>
                    @php
                        $preferredTpl = $exam->card_template_id ?? optional($cardTemplates->firstWhere('is_default', true))->id;
                        $selectedTpl = $preferredTpl && !($cardTemplateCompatibility[$preferredTpl] ?? null)
                            ? $preferredTpl
                            : optional($cardTemplates->first(fn ($tpl) => !($cardTemplateCompatibility[$tpl->id] ?? null)))->id;
                        $hasCompatibleTemplate = (bool) $selectedTpl;
                    @endphp
                    <select name="card_template_id" class="input-field" {{ $hasCompatibleTemplate ? '' : 'disabled' }}>
                        @php
                            $templateGroups = [
                                'Sistema' => $cardTemplates->where('is_system', true),
                                'Meus modelos' => $cardTemplates->where('is_system', false)->where('created_by', auth()->id()),
                                'Instituição' => $cardTemplates->where('is_system', false)->where('created_by', '!=', auth()->id()),
                            ];
                        @endphp
                        @foreach($templateGroups as $groupLabel => $groupTemplates)
                            @if($groupTemplates->isNotEmpty())
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach($groupTemplates as $tpl)
                                        @php $incompatibility = $cardTemplateCompatibility[$tpl->id] ?? null; @endphp
                                        <option value="{{ $tpl->id }}" {{ $selectedTpl == $tpl->id ? 'selected' : '' }} {{ $incompatibility ? 'disabled' : '' }}>
                                            {{ $tpl->name }}@if($tpl->is_default) (padrão)@endif — {{ $tpl->columns }}×{{ $tpl->rows_per_column }}, {{ $tpl->max_options }} alternativas, até {{ $tpl->max_questions ?? '∞' }} questões{{ $incompatibility ? ' — incompatível' : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                        @if($cardTemplates->isEmpty())
                            <option value="">Nenhum template disponível</option>
                        @endif
                    </select>
                    @php $incompatibleTemplates = $cardTemplates->filter(fn ($tpl) => (bool) ($cardTemplateCompatibility[$tpl->id] ?? null)); @endphp
                    @if($incompatibleTemplates->isNotEmpty())
                        <details class="mt-2 text-xs text-gray-500">
                            <summary class="cursor-pointer">Por que alguns modelos estão indisponíveis?</summary>
                            <ul class="mt-1 list-disc pl-5 space-y-1">
                                @foreach($incompatibleTemplates as $tpl)
                                    <li><strong>{{ $tpl->name }}:</strong> {{ $cardTemplateCompatibility[$tpl->id] }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    @if(!$hasCompatibleTemplate)
                        <p class="mt-2 text-sm font-bold text-red-600" role="alert">
                            Nenhum modelo ativo comporta as questões e alternativas desta Avaliação.
                        </p>
                    @endif
                </div>
                <div class="w-32">
                    <label class="input-label">Quantidade</label>
                    <input type="number" name="quantity" value="1" min="1" max="100" class="input-field">
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-bold text-gray-600 pb-3">
                    <input type="checkbox" name="shuffle_options_mc" value="1" class="rounded border-gray-300">
                    Embaralhar alternativas
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-bold text-gray-600 pb-3">
                    <input type="checkbox" name="individualize" value="1" class="rounded border-gray-300">
                    Uma por aluno do público
                </label>
                <button type="submit" class="btn-primary btn-sm flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $hasCompatibleTemplate ? '' : 'disabled' }}>
                    <span class="material-symbols-outlined text-[18px]">print</span> Gerar Cartão-Resposta (PDF)
                </button>
            </form>
        @endif
    </div>

    <!-- Submissions List -->
    <div class="bg-white rounded-2xl border-2 border-duo-border shadow-sm overflow-hidden mb-12">
        <div class="p-6 border-b-2 border-duo-border flex justify-between items-center bg-gray-50">
            <h2 class="text-lg font-extrabold text-duo-heading flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">checklist</span> Respostas dos Alunos
            </h2>
            <div x-data>
                <button @click="$dispatch('open-modal', 'print-advanced-modal')"
                    class="btn-secondary btn-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Imprimir Lote
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b-2 border-duo-border">
                        <th class="p-4 text-xs font-extrabold text-gray-400 uppercase tracking-widest w-1/4">Aluno</th>
                        <th class="p-4 text-xs font-extrabold text-gray-400 uppercase tracking-widest w-1/4">Turma</th>
                        <th class="p-4 text-xs font-extrabold text-gray-400 uppercase tracking-widest w-1/4">Status</th>
                        <th
                            class="p-4 text-xs font-extrabold text-gray-400 uppercase tracking-widest w-1/6 text-center">
                            Nota</th>
                        <th class="p-4 text-xs font-extrabold text-gray-400 uppercase tracking-widest text-right">Ação
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-gray-100">
                    @foreach($audienceStudents as $student)
                            @php
                                $sub = $submissions->get($student->id);
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors group">
                                <td class="p-4">
                                    <div class="font-bold text-gray-800">{{ $student->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $student->email }}</div>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-md text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                        {{ $student->schoolClasses->pluck('name')->join(', ') ?: 'Aluno específico' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    @if(!$sub)
                                        <span class="inline-flex items-center gap-1 text-gray-400 font-bold text-xs">
                                            <span class="size-2 rounded-full bg-gray-300"></span> Pendente
                                        </span>
                                    @elseif($sub->status === 'in_progress')
                                        <span class="inline-flex items-center gap-1 text-blue-500 font-bold text-xs">
                                            <span class="size-2 rounded-full bg-blue-500 animate-pulse"></span> Em andamento
                                        </span>
                                    @elseif($sub->status === 'submitted')
                                        <span class="inline-flex items-center gap-1 text-yellow-600 font-bold text-xs">
                                            <span class="size-2 rounded-full bg-yellow-500"></span> Aguardando (Fila)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-green-600 font-bold text-xs">
                                            <span class="size-2 rounded-full bg-green-500"></span> Corrigido
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    @if($sub && $sub->status === 'graded')
                                        <span class="font-extrabold text-lg">{{ number_format($sub->score, 1) }}</span>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    @if($sub && in_array($sub->status, ['submitted', 'graded']))
                                        <a href="{{ route('exams.gradeSubmission', [$exam->id, $sub->id]) }}"
                                            class="inline-flex items-center justify-center p-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg font-bold text-xs uppercase tracking-wider transition-colors"
                                            title="Corrigir / Visualizar">
                                            <span class="material-symbols-outlined text-[18px]">rule</span>
                                        </a>
                                    @else
                                        <span class="text-gray-300 material-symbols-outlined block text-center"
                                            title="Nenhuma submissão">remove</span>
                                    @endif
                                </td>
                            </tr>
                    @endforeach

                    @if($totalStudents === 0)
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 font-medium">
                                Nenhum público vinculado. A avaliação pode permanecer assim enquanto estiver em preparação.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Print Advanced Configuration Modal --}}
    @include('exams.partials.print-advanced-modal')
</x-app-layout>
