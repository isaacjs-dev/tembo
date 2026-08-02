<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('exams.index') }}">Avaliações</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Configurar</span>
                </nav>
                <h1 class="page-title">Configurar: {{ $exam->title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-neutral flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">info</span>
                    {{ ucfirst($exam->status) }}
                </span>
                <a href="{{ route('exams.index') }}" class="btn-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="alert alert-success mb-6">
            <span class="material-symbols-outlined">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="error-list mb-6">
            @foreach ($errors->all() as $error)
                <div class="error-list-item">
                    <span class="material-symbols-outlined text-xl">error</span>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Main Container with Alpine --}}
    <div x-data="examBuilder()" class="mb-12">

        {{-- 5-col grid: 3-col editor + 2-col preview --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

            {{-- Left Column: Settings + Questions + Sidebar --}}
            <div class="lg:col-span-3 space-y-6">

                {{-- Settings Card --}}
                <div class="card p-8">
                    <h2
                        class="text-xl font-extrabold text-duo-heading mb-6 flex items-center gap-2 border-b-2 border-duo-border pb-4">
                        <span class="material-symbols-outlined text-primary">settings</span> Configurações Gerais
                    </h2>
                    <form action="{{ route('exams.update', $exam->id) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_form" value="1">

                        @php
                            $settingDate = function (string $key) use ($exam): string {
                                $value = $exam->settings[$key] ?? null;
                                if (!$value) {
                                    return '';
                                }

                                try {
                                    return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i');
                                } catch (\Throwable) {
                                    return '';
                                }
                            };
                        @endphp

                        <div class="space-y-2">
                            <label for="title" class="input-label">Título da Avaliação</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $exam->title) }}" required
                                class="input-field">
                        </div>

                        <div class="space-y-2">
                            <label for="instructions" class="input-label">Instruções ao estudante</label>
                            <textarea id="instructions" name="instructions" rows="4" maxlength="10000"
                                class="input-field"
                                placeholder="Materiais permitidos, critérios e orientações.">{{ old('instructions', $exam->settings['instructions'] ?? '') }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="status" class="input-label">Status da Avaliação</label>
                                <select id="status" name="status" class="input-field">
                                    <option value="draft" {{ old('status', $exam->status) == 'draft' ? 'selected' : '' }}>
                                        Rascunho (invisível)</option>
                                    <option value="published" {{ old('status', $exam->status) == 'published' ? 'selected' : '' }}>Publicada</option>
                                    <option value="closed" {{ old('status', $exam->status) == 'closed' ? 'selected' : '' }}>Encerrada</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="application_mode" class="input-label">Modalidade</label>
                                <select id="application_mode" name="application_mode" class="input-field">
                                    <option value="hybrid" @selected(old('application_mode', $exam->settings['application_mode'] ?? 'hybrid') === 'hybrid')>
                                        Híbrida — on-line e impressa
                                    </option>
                                    <option value="online" @selected(old('application_mode', $exam->settings['application_mode'] ?? '') === 'online')>
                                        Somente on-line
                                    </option>
                                    <option value="paper" @selected(old('application_mode', $exam->settings['application_mode'] ?? '') === 'paper')>
                                        Somente impressa / cartão-resposta
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="attempts" class="input-label">Tentativas Permitidas</label>
                                <input type="number" id="attempts" name="attempts"
                                    value="{{ old('attempts', $exam->settings['attempts'] ?? 1) }}" min="1"
                                    max="20" class="input-field">
                            </div>
                            <div class="space-y-2">
                                <label for="time_limit" class="input-label">Tempo Limite (Minutos)</label>
                                <input type="number" id="time_limit" name="time_limit"
                                    value="{{ old('time_limit', $exam->settings['time_limit'] ?? '') }}" min="1" max="1440"
                                    class="input-field" placeholder="Sem limite">
                            </div>
                            <div class="space-y-2">
                                <label for="available_from" class="input-label">Disponível a partir de</label>
                                <input type="datetime-local" id="available_from" name="available_from"
                                    value="{{ old('available_from', $settingDate('available_from')) }}"
                                    class="input-field">
                            </div>
                            <div class="space-y-2">
                                <label for="available_until" class="input-label">Prazo final</label>
                                <input type="datetime-local" id="available_until" name="available_until"
                                    value="{{ old('available_until', $settingDate('available_until')) }}"
                                    class="input-field">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 rounded-xl border-2 border-duo-border bg-gray-50 p-5 md:grid-cols-2">
                            <fieldset class="space-y-3">
                                <legend class="mb-2 font-extrabold text-duo-heading">Apresentação</legend>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="shuffle_questions" value="1"
                                        @checked(old('shuffle_questions', $exam->settings['shuffle_questions'] ?? false))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Embaralhar questões</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="shuffle_options" value="1"
                                        @checked(old('shuffle_options', $exam->settings['shuffle_options'] ?? false))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Embaralhar alternativas</span>
                                </label>
                            </fieldset>

                            <fieldset class="space-y-3">
                                <legend class="mb-2 font-extrabold text-duo-heading">Resultados liberados</legend>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_score" value="1"
                                        @checked(old('show_score', $exam->settings['show_score'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Nota</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_feedback" value="1"
                                        @checked(old('show_feedback', $exam->settings['show_feedback'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Comentários e recomendações</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_answers" value="1"
                                        @checked(old('show_answers', $exam->settings['show_answers'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Respostas e gabarito</span>
                                </label>
                                <div>
                                    <label for="results_available_from" class="input-label">Liberar a partir de</label>
                                    <input type="datetime-local" id="results_available_from"
                                        name="results_available_from"
                                        value="{{ old('results_available_from', $settingDate('results_available_from')) }}"
                                        class="input-field">
                                </div>
                            </fieldset>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary text-xs uppercase tracking-wider">
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Questions Card --}}
                <div class="card p-8">
                    <div
                        class="flex flex-col md:flex-row justify-between items-center mb-6 border-b-2 border-duo-border pb-4 gap-4">
                        <h2 class="text-xl font-extrabold text-duo-heading flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">format_list_numbered</span>
                            Questões
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs"
                                x-text="questions.length"></span>
                        </h2>

                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-gray-500">
                                Total: <span class="text-primary font-extrabold" x-text="totalPoints.toFixed(1)"></span>
                                pts
                            </span>
                            <button type="button" @click="openPicker()"
                                class="btn-primary btn-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">library_add</span>
                                Adicionar Questões
                            </button>
                        </div>
                    </div>

                    {{-- Question List with Drag & Drop --}}
                    <div id="questions-sortable" class="space-y-3">
                        <template x-for="(q, idx) in questions" :key="q.id">
                            <div class="flex items-center gap-3 p-4 bg-gray-50 border-2 border-duo-border rounded-xl group transition-all hover:border-primary/30"
                                :data-id="q.id">

                                {{-- Drag Handle --}}
                                <div
                                    class="cursor-grab active:cursor-grabbing shrink-0 text-gray-300 hover:text-gray-500 drag-handle">
                                    <span class="material-symbols-outlined text-[20px]">drag_indicator</span>
                                </div>

                                {{-- Number --}}
                                <div class="size-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-extrabold text-sm shrink-0"
                                    x-text="idx + 1"></div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="badge badge-primary text-[10px]" x-text="
                                            q.type === 'multiple_choice' ? 'Múlt. Escolha' :
                                            q.type === 'true_false' ? 'V/F' : 'Dissertativa'
                                        "></span>
                                        <span x-show="q.discipline" class="badge badge-info text-[10px]"
                                            x-text="q.discipline"></span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-800 truncate"
                                        x-text="q.content?.statement || ''"></p>
                                </div>

                                {{-- Points --}}
                                <div class="shrink-0 flex items-center gap-1">
                                    <input type="number" :value="q.points" step="0.5" min="0"
                                        @change="updateQuestionPoints(q.id, $event.target.value)"
                                        class="w-16 px-2 py-1.5 bg-white border-2 border-duo-border rounded-lg text-xs font-bold text-center focus:border-primary focus:outline-none">
                                    <span class="text-xs text-gray-400 font-bold">pts</span>
                                </div>

                                {{-- Remove --}}
                                <button type="button" @click="removeQuestion(q.id)"
                                    class="shrink-0 p-1.5 text-gray-300 hover:text-red-500 rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Empty State --}}
                    <div x-show="questions.length === 0"
                        class="text-center p-8 bg-gray-50 border-2 border-dashed border-duo-border rounded-xl">
                        <span class="material-symbols-outlined text-4xl text-gray-300 mb-2 block">inventory_2</span>
                        <p class="text-gray-500 font-bold text-sm mb-4">Nenhuma questão adicionada ainda.</p>
                        <button type="button" @click="openPicker()"
                            class="btn-primary btn-sm inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">library_add</span>
                            Adicionar Questões
                        </button>
                    </div>
                </div>

                {{-- Classes + Publish (row) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Turmas --}}
                    <div class="card p-6">
                        <h2
                            class="text-lg font-extrabold text-duo-heading mb-4 flex items-center gap-2 border-b-2 border-duo-border pb-3">
                            <span class="material-symbols-outlined text-secondary">groups</span> Turmas Alvo
                        </h2>
                        <form action="{{ route('exams.syncClasses', $exam->id) }}" method="POST" class="space-y-4">
                            @csrf
                            <div class="max-h-48 overflow-y-auto space-y-2 pr-2">
                                @php $assignedClasses = $exam->schoolClasses->pluck('id')->toArray(); @endphp
                                @forelse($availableClasses as $class)
                                    <label
                                        class="flex items-center gap-3 p-3 border-2 border-duo-border rounded-lg cursor-pointer hover:border-secondary/50 transition-colors {{ in_array($class->id, $assignedClasses) ? 'bg-secondary/10 border-secondary' : '' }}">
                                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" {{ in_array($class->id, $assignedClasses) ? 'checked' : '' }}
                                            class="size-5 text-secondary border-gray-300 rounded focus:ring-secondary">
                                        <span class="font-bold text-gray-800 text-sm">{{ $class->name }}
                                            ({{ $class->year }})</span>
                                    </label>
                                @empty
                                    <p class="text-gray-500 text-xs text-center italic">Nenhuma turma cadastrada.</p>
                                @endforelse
                            </div>
                            <button type="submit"
                                class="w-full btn-secondary py-3 rounded-lg font-bold text-xs uppercase tracking-wider block">
                                Atualizar Turmas
                            </button>
                        </form>
                    </div>

                    {{-- Publishing --}}
                    <div
                        class="bg-primary/5 rounded-2xl border-2 border-primary/20 p-6 text-center shadow-sm flex flex-col justify-center">
                        <h3 class="font-extrabold text-primary mb-2 text-lg">Pronto para lançar?</h3>
                        <p class="text-xs text-gray-600 mb-6 font-medium px-4">Após adicionar as questões e turmas alvo,
                            publique a prova.</p>
                        <form action="{{ route('exams.update', $exam->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="title" value="{{ $exam->title }}">

                            @if($exam->status === 'draft')
                                <input type="hidden" name="status" value="published">
                                <button type="submit"
                                    class="w-full btn-primary py-4 rounded-xl text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">send</span>
                                    Publicar avaliação
                                </button>
                            @elseif($exam->status === 'published')
                                <input type="hidden" name="status" value="closed">
                                <button type="submit"
                                    class="w-full p-4 border-2 border-red-500 text-red-500 hover:bg-red-50 rounded-xl font-extrabold text-sm uppercase tracking-wider transition-colors flex justify-center gap-2 items-center">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">do_not_disturb_on</span> Encerrar
                                    Prova
                                </button>
                            @else
                                <input type="hidden" name="status" value="draft">
                                <button type="submit"
                                    class="w-full p-4 bg-gray-200 text-gray-600 hover:bg-gray-300 rounded-xl font-extrabold text-sm uppercase tracking-wider transition-colors flex justify-center gap-2 items-center">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">draft</span> Reabrir Rascunho
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            {{-- Right Column: Live Preview --}}
            <div class="lg:col-span-2 lg:sticky lg:top-8">
                <div
                    class="bg-background-light rounded-2xl border-2 border-duo-border p-6 shadow-sm flex flex-col min-h-[600px]">
                    {{-- Preview Header --}}
                    <div class="flex flex-col gap-3 mb-6 border-b-2 border-gray-200 pb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-500">visibility</span>
                                <h2 class="text-lg font-extrabold text-gray-800">Pré-visualização</h2>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="$dispatch('open-modal', 'print-advanced-modal')"
                                    class="btn-secondary btn-sm flex items-center gap-1 shadow-sm text-xs font-bold py-1 px-3">
                                    <span class="material-symbols-outlined text-[14px]">picture_as_pdf</span> Imprimir
                                    Lote
                                </button>
                            </div>
                            <div class="flex bg-gray-200 p-1 rounded-xl items-center">
                                <button type="button" @click="previewMode = 'web'"
                                    :class="previewMode === 'web' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                                    class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">desktop_windows</span> Web
                                </button>
                                <button type="button" @click="previewMode = 'mobile'"
                                    :class="previewMode === 'mobile' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                                    class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">smartphone</span> Mobile
                                </button>
                                <button type="button" @click="previewMode = 'print'"
                                    :class="previewMode === 'print' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                                    class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">print</span> Impressa
                                </button>
                            </div>
                        </div>

                        {{-- Pagination Controls --}}
                        <div x-show="questions.length > 0" class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-500"
                                x-text="'Página ' + previewPage + ' de ' + previewTotalPages"></span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="previewPage = 1" :disabled="previewPage <= 1"
                                    class="p-1 rounded-lg text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">first_page</span>
                                </button>
                                <button type="button" @click="previewPage--" :disabled="previewPage <= 1"
                                    class="p-1 rounded-lg text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                                </button>

                                {{-- Page dots --}}
                                <div class="flex items-center gap-1 mx-1">
                                    <template x-for="p in previewTotalPages" :key="'page-' + p">
                                        <button type="button" @click="previewPage = p"
                                            :class="previewPage === p ? 'bg-primary w-6' : 'bg-gray-300 w-2'"
                                            class="h-2 rounded-full transition-all duration-200"></button>
                                    </template>
                                </div>

                                <button type="button" @click="previewPage++"
                                    :disabled="previewPage >= previewTotalPages"
                                    class="p-1 rounded-lg text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                </button>
                                <button type="button" @click="previewPage = previewTotalPages"
                                    :disabled="previewPage >= previewTotalPages"
                                    class="p-1 rounded-lg text-gray-400 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">last_page</span>
                                </button>
                            </div>
                            <label class="flex items-center gap-1.5 text-[11px] font-bold text-gray-500">
                                <span>Por pág:</span>
                                <select x-model.number="previewPerPage" @change="previewPage = 1"
                                    class="w-14 px-1.5 py-1 bg-white border border-gray-300 rounded-lg text-[11px] font-bold text-center focus:border-primary focus:outline-none">
                                    <option value="3">3</option>
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="999">Todas</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- Preview Content --}}
                    <div class="flex-1 flex justify-center items-start overflow-hidden">

                        {{-- ===== WEB MODE ===== --}}
                        <div x-show="previewMode === 'web'"
                            class="w-full bg-white rounded-2xl border-2 border-duo-border p-4 shadow-sm transition-all duration-300 ease-in-out overflow-y-auto max-h-[70vh]">

                            {{-- Empty Preview --}}
                            <div x-show="questions.length === 0" class="text-center py-12 text-gray-400">
                                <span class="material-symbols-outlined text-4xl mb-4 opacity-50 block">quiz</span>
                                <p class="font-medium text-sm">Adicione questões para visualizar a prova.</p>
                            </div>

                            {{-- Questions Preview --}}
                            <div x-show="questions.length > 0" class="space-y-4">
                                {{-- Exam Title --}}
                                <div class="text-center mb-4 pb-3 border-b-2 border-duo-border">
                                    <h3 class="text-base font-extrabold text-gray-800">{{ $exam->title }}</h3>
                                    <p class="text-xs text-gray-400 mt-1"
                                        x-text="questions.length + ' questão(ões) · ' + totalPoints.toFixed(1) + ' pontos'">
                                    </p>
                                </div>

                                {{-- Progress Bar --}}
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-3">
                                    <div class="bg-primary h-2.5 rounded-full transition-all duration-300"
                                        :style="'width: ' + Math.round((previewPage / previewTotalPages) * 100) + '%'">
                                    </div>
                                </div>

                                <template x-for="(q, idx) in previewPageQuestions" :key="'prev-web-' + q.id">
                                    <div class="bg-white rounded-xl border-2 border-duo-border p-4 shadow-sm">
                                        <div class="flex items-start gap-3">
                                            {{-- Number Circle --}}
                                            <div class="shrink-0 w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center font-extrabold text-xs text-gray-500 border-2 border-duo-border"
                                                x-text="q._globalIndex"></div>

                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-bold text-gray-800 mb-3 leading-relaxed"
                                                    x-text="q.content?.statement || ''"></p>

                                                {{-- Multiple Choice --}}
                                                <template x-if="q.type === 'multiple_choice' && q.content?.options">
                                                    <div class="space-y-2">
                                                        <template x-for="(opt, oIdx) in q.content.options"
                                                            :key="'wo-' + oIdx">
                                                            <label
                                                                class="flex items-center gap-3 p-2.5 border-2 border-duo-border rounded-lg cursor-default hover:border-secondary/40 transition-colors">
                                                                <div class="relative flex items-center justify-center">
                                                                    <div
                                                                        class="size-5 rounded-full border-2 border-gray-300 shrink-0">
                                                                    </div>
                                                                </div>
                                                                <span class="text-xs font-medium text-gray-700"
                                                                    x-text="opt"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>

                                                {{-- True/False --}}
                                                <template x-if="q.type === 'true_false'">
                                                    <div class="flex gap-2">
                                                        <div
                                                            class="flex-1 flex flex-col items-center p-3 border-2 border-duo-border rounded-xl text-center hover:border-green-300 transition-colors cursor-default">
                                                            <span
                                                                class="material-symbols-outlined text-2xl text-gray-300 mb-1">check_circle</span>
                                                            <span
                                                                class="font-bold text-xs text-gray-500">Verdadeiro</span>
                                                        </div>
                                                        <div
                                                            class="flex-1 flex flex-col items-center p-3 border-2 border-duo-border rounded-xl text-center hover:border-red-300 transition-colors cursor-default">
                                                            <span
                                                                class="material-symbols-outlined text-2xl text-gray-300 mb-1">cancel</span>
                                                            <span class="font-bold text-xs text-gray-500">Falso</span>
                                                        </div>
                                                    </div>
                                                </template>

                                                {{-- Essay --}}
                                                <template x-if="q.type === 'essay'">
                                                    <div
                                                        class="p-3 bg-background-light border-2 border-duo-border rounded-lg text-xs text-gray-400 italic">
                                                        O aluno digitará a resposta aqui...
                                                    </div>
                                                </template>

                                                <div class="mt-2 text-right">
                                                    <span class="text-[10px] font-bold text-gray-400"
                                                        x-text="q.points + ' pt(s)'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- ===== MOBILE MODE ===== --}}
                        <div x-show="previewMode === 'mobile'"
                            class="max-w-[375px] h-[667px] overflow-y-auto border-[12px] border-gray-800 bg-white rounded-[2.5rem] p-3 shadow-sm transition-all duration-300 ease-in-out">

                            {{-- Empty --}}
                            <div x-show="questions.length === 0" class="text-center py-12 text-gray-400">
                                <span class="material-symbols-outlined text-4xl mb-4 opacity-50 block">quiz</span>
                                <p class="font-medium text-xs">Adicione questões para visualizar.</p>
                            </div>

                            <div x-show="questions.length > 0" class="space-y-3">
                                {{-- Mobile Header --}}
                                <div class="text-center mb-3 pb-2 border-b border-gray-200">
                                    <h3 class="text-sm font-extrabold text-gray-800">{{ $exam->title }}</h3>
                                    <p class="text-[10px] text-gray-400 mt-0.5"
                                        x-text="questions.length + ' questão(ões)'"></p>
                                </div>

                                {{-- Progress --}}
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                    <div class="bg-primary h-2 rounded-full transition-all duration-300"
                                        :style="'width: ' + Math.round((previewPage / previewTotalPages) * 100) + '%'">
                                    </div>
                                </div>

                                <template x-for="(q, idx) in previewPageQuestions" :key="'prev-mob-' + q.id">
                                    <div class="bg-white rounded-xl border border-gray-200 p-3 shadow-sm">
                                        <div class="flex items-start gap-2">
                                            <div class="shrink-0 w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center font-extrabold text-[10px] text-gray-500 border border-gray-200"
                                                x-text="q._globalIndex"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-bold text-gray-800 mb-2 leading-relaxed"
                                                    x-text="q.content?.statement || ''"></p>

                                                {{-- MC --}}
                                                <template x-if="q.type === 'multiple_choice' && q.content?.options">
                                                    <div class="space-y-1.5">
                                                        <template x-for="(opt, oIdx) in q.content.options"
                                                            :key="'mo-' + oIdx">
                                                            <label
                                                                class="flex items-center gap-2 p-2 border border-gray-200 rounded-lg cursor-default">
                                                                <div
                                                                    class="size-4 rounded-full border-2 border-gray-300 shrink-0">
                                                                </div>
                                                                <span class="text-[10px] font-medium text-gray-700"
                                                                    x-text="opt"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>

                                                {{-- V/F --}}
                                                <template x-if="q.type === 'true_false'">
                                                    <div class="flex gap-1.5">
                                                        <div
                                                            class="flex-1 flex flex-col items-center p-2 border border-gray-200 rounded-lg text-center">
                                                            <span
                                                                class="material-symbols-outlined text-lg text-gray-300">check_circle</span>
                                                            <span
                                                                class="font-bold text-[9px] text-gray-500">Verdadeiro</span>
                                                        </div>
                                                        <div
                                                            class="flex-1 flex flex-col items-center p-2 border border-gray-200 rounded-lg text-center">
                                                            <span
                                                                class="material-symbols-outlined text-lg text-gray-300">cancel</span>
                                                            <span
                                                                class="font-bold text-[9px] text-gray-500">Falso</span>
                                                        </div>
                                                    </div>
                                                </template>

                                                {{-- Essay --}}
                                                <template x-if="q.type === 'essay'">
                                                    <div
                                                        class="p-2 bg-gray-50 border border-gray-200 rounded-lg text-[10px] text-gray-400 italic">
                                                        O aluno digitará a resposta aqui...
                                                    </div>
                                                </template>

                                                <div class="mt-1.5 text-right">
                                                    <span class="text-[9px] font-bold text-gray-400"
                                                        x-text="q.points + ' pt(s)'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- ===== PRINT MODE ===== --}}
                        <div x-show="previewMode === 'print'" class="w-full">

                            {{-- Shuffle Controls --}}
                            <div class="flex flex-wrap items-center gap-3 mb-3 p-2.5 bg-gray-50 border border-gray-200 rounded-xl"
                                style="font-family: sans-serif;">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" x-model="previewShuffle.questions" @change="reshuffle()"
                                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary size-4">
                                    <span class="text-[11px] font-bold text-gray-600">Questões</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" x-model="previewShuffle.mc" @change="reshuffle()"
                                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary size-4">
                                    <span class="text-[11px] font-bold text-gray-600">Múlt. Escolha</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" x-model="previewShuffle.tf" @change="reshuffle()"
                                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary size-4">
                                    <span class="text-[11px] font-bold text-gray-600">V/F</span>
                                </label>
                                <button type="button" @click="reshuffle()"
                                    class="ml-auto text-[11px] font-bold text-primary hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">refresh</span> Nova versão
                                </button>
                            </div>

                            {{-- Print Sub-tabs --}}
                            <div class="flex bg-gray-100 p-1 rounded-xl mb-3 items-center">
                                <button type="button" @click="printTab = 'exam'"
                                    :class="printTab === 'exam' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    class="flex-1 px-2 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-center">
                                    Prova
                                </button>
                                <button type="button" @click="printTab = 'answer_sheet'"
                                    :class="printTab === 'answer_sheet' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    class="flex-1 px-2 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-center">
                                    Cartão Resposta
                                </button>
                                <button type="button" @click="printTab = 'answer_key'"
                                    :class="printTab === 'answer_key' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    class="flex-1 px-2 py-1.5 text-[11px] font-bold rounded-lg transition-colors text-center">
                                    Gabarito Professor
                                </button>
                            </div>

                            {{-- === PRINT: PROVA === --}}
                            <div x-show="printTab === 'exam'"
                                class="bg-white border-2 border-gray-300 shadow-md p-5 overflow-y-auto max-h-[60vh]"
                                style="font-family: 'Times New Roman', serif;">

                                <div x-show="questions.length === 0" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-4 opacity-50 block">quiz</span>
                                    <p class="font-medium text-sm" style="font-family: sans-serif;">Adicione questões
                                        para visualizar.</p>
                                </div>

                                <div x-show="questions.length > 0">
                                    {{-- Watermark --}}
                                    <div class="text-right text-[9px] text-gray-400 mb-1"
                                        x-show="previewShuffle.questions || previewShuffle.mc || previewShuffle.tf">
                                        Versão com embaralhamento (preview)
                                    </div>

                                    {{-- Print Header --}}
                                    <div class="text-center border-b-2 border-black pb-3 mb-4">
                                        <h3 class="text-lg font-bold text-black">{{ $exam->title }}</h3>
                                        @if($exam->organization)
                                            <p class="text-sm text-gray-600">{{ $exam->organization->name ?? '' }}</p>
                                        @endif
                                        <p class="text-[11px] text-gray-500 mt-1">Caderno de Prova</p>
                                    </div>

                                    {{-- Student Info --}}
                                    <div class="border border-gray-400 rounded p-3 mb-5 text-sm text-black space-y-2">
                                        <div class="border-b border-dotted border-gray-400 pb-1">
                                            <strong>Nome do Aluno:</strong>
                                            <span
                                                class="inline-block border-b border-dotted border-gray-400 w-[60%] ml-1">&nbsp;</span>
                                        </div>
                                        <div class="flex gap-4 flex-wrap">
                                            <span><strong>Turma:</strong> <span
                                                    class="inline-block border-b border-dotted border-gray-400 w-24">&nbsp;</span></span>
                                            <span><strong>Data:</strong> <span
                                                    class="inline-block border-b border-dotted border-gray-400 w-28">&nbsp;</span></span>
                                            <span><strong>Nota:</strong> <span
                                                    class="inline-block border-b border-dotted border-gray-400 w-12">&nbsp;</span></span>
                                        </div>
                                    </div>

                                    {{-- Questions (uses shuffled data) --}}
                                    <div class="space-y-5">
                                        <template x-for="(q, idx) in printPageQuestions"
                                            :key="'prev-print-' + q.id + '-' + _shuffleSeed">
                                            <div class="text-sm text-black">
                                                {{-- Question Identifier --}}
                                                <p class="font-bold mb-1">
                                                    <span class="underline" x-text="'Questão ' + q._globalIndex"></span>
                                                    <span class="text-[11px] font-normal text-gray-500 ml-1"
                                                        x-text="'(Valor: ' + parseFloat(q.points).toFixed(1) + ')'"></span>
                                                </p>
                                                <p class="mb-2 text-justify leading-relaxed"
                                                    x-text="q.content?.statement || ''"></p>

                                                {{-- MC Options via optionsMap --}}
                                                <template x-if="q.type === 'multiple_choice' && q._optionsMap">
                                                    <ul class="list-none pl-4 space-y-1.5">
                                                        <template x-for="(origIdx, i) in q._optionsMap"
                                                            :key="'po-' + i">
                                                            <li class="flex items-center gap-2">
                                                                <span class="font-bold shrink-0 w-5 text-center"
                                                                    x-text="String.fromCharCode(65 + i) + ')'"></span>
                                                                <span
                                                                    class="inline-block w-4 h-4 rounded-full border-[1.5px] border-black shrink-0"></span>
                                                                <span x-text="q.content.options[origIdx]"></span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </template>

                                                {{-- V/F via optionsMap --}}
                                                <template x-if="q.type === 'true_false' && q._optionsMap">
                                                    <ul class="list-none pl-4 mt-1 space-y-1.5">
                                                        <template x-for="(origIdx, i) in q._optionsMap"
                                                            :key="'tf-' + i">
                                                            <li class="flex items-center gap-2">
                                                                <span class="font-bold shrink-0 w-5 text-center"
                                                                    x-text="String.fromCharCode(65 + i) + ')'"></span>
                                                                <span
                                                                    class="inline-block w-4 h-4 rounded-full border-[1.5px] border-black shrink-0"></span>
                                                                <span
                                                                    x-text="origIdx === 0 ? 'Verdadeiro' : 'Falso'"></span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </template>

                                                {{-- Essay --}}
                                                <template x-if="q.type === 'essay'">
                                                    <div class="mt-2 border border-gray-400 rounded h-28"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- === PRINT: CARTÃO RESPOSTA (GABARITO DO ALUNO) === --}}
                            <div x-show="printTab === 'answer_sheet'"
                                class="bg-white border-2 border-gray-300 shadow-md p-5 overflow-y-auto max-h-[60vh]"
                                style="font-family: Arial, sans-serif;">

                                <div x-show="questions.length === 0" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-4 opacity-50 block">quiz</span>
                                    <p class="font-medium text-sm">Adicione questões para visualizar.</p>
                                </div>

                                <div x-show="questions.length > 0">
                                    {{-- Header + QR placeholder --}}
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <div class="text-center border-b-2 border-black pb-3">
                                                <h3 class="text-base font-bold text-black">CARTÃO RESPOSTA</h3>
                                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $exam->title }}</p>
                                            </div>
                                        </div>
                                        <div class="shrink-0 ml-3 flex flex-col items-center">
                                            <div
                                                class="w-20 h-20 border-2 border-gray-400 rounded flex items-center justify-center bg-gray-50">
                                                <span
                                                    class="material-symbols-outlined text-3xl text-gray-300">qr_code_2</span>
                                            </div>
                                            <span class="text-[8px] text-gray-400 mt-1">QR Code</span>
                                        </div>
                                    </div>

                                    {{-- Student Info --}}
                                    <div
                                        class="border border-gray-400 rounded p-2.5 mb-4 text-xs text-black space-y-1.5">
                                        <div class="border-b border-dotted border-gray-400 pb-1">
                                            <strong>Nome:</strong>
                                            <span
                                                class="inline-block border-b border-dotted border-gray-400 w-[65%] ml-1">&nbsp;</span>
                                        </div>
                                        <div class="flex gap-3">
                                            <span><strong>Matrícula:</strong> <span
                                                    class="inline-block border-b border-dotted border-gray-400 w-20">&nbsp;</span></span>
                                            <span><strong>Turma:</strong> <span
                                                    class="inline-block border-b border-dotted border-gray-400 w-20">&nbsp;</span></span>
                                            <span><strong>Data:</strong> ___/___/____</span>
                                        </div>
                                    </div>

                                    {{-- Instructions --}}
                                    <div
                                        class="border border-gray-300 bg-gray-50 rounded p-2 mb-4 text-[10px] text-gray-600 text-center">
                                        Preencha completamente o círculo da alternativa escolhida. Use caneta azul ou
                                        preta.
                                    </div>

                                    {{-- Bubble Grid (uses shuffled order) --}}
                                    <div class="border border-gray-400 rounded p-3">
                                        <table class="w-full text-xs border-collapse">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th
                                                        class="border border-gray-400 px-2 py-1.5 text-center font-bold w-16">
                                                        Q.</th>
                                                    <th class="border border-gray-400 px-2 py-1.5 text-left font-bold">
                                                        Respostas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(q, idx) in previewShuffledQuestions"
                                                    :key="'bubble-' + q.id">
                                                    <tr>
                                                        <td class="border border-gray-400 px-2 py-1.5 text-center font-bold"
                                                            x-text="String(idx + 1).padStart(2, '0')"></td>
                                                        <td class="border border-gray-400 px-2 py-1.5">
                                                            <template
                                                                x-if="q.type === 'multiple_choice' && q.content?.options">
                                                                <div class="flex items-center gap-2">
                                                                    <template x-for="(origIdx, i) in q._optionsMap"
                                                                        :key="'bub-' + i">
                                                                        <div class="flex flex-col items-center">
                                                                            <span
                                                                                class="inline-block w-5 h-5 rounded-full border-[1.5px] border-black text-center text-[9px] font-bold leading-5"
                                                                                x-text="String.fromCharCode(65 + i)"></span>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                            <template x-if="q.type === 'true_false'">
                                                                <div class="flex items-center gap-2">
                                                                    <span
                                                                        class="inline-block w-5 h-5 rounded-full border-[1.5px] border-black text-center text-[9px] font-bold leading-5">A</span>
                                                                    <span
                                                                        class="inline-block w-5 h-5 rounded-full border-[1.5px] border-black text-center text-[9px] font-bold leading-5">B</span>
                                                                </div>
                                                            </template>
                                                            <template x-if="q.type === 'essay'">
                                                                <span
                                                                    class="text-[10px] text-gray-500 italic">Dissertativa</span>
                                                            </template>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- === PRINT: GABARITO DO PROFESSOR === --}}
                            <div x-show="printTab === 'answer_key'"
                                class="bg-white border-2 border-gray-300 shadow-md p-5 overflow-y-auto max-h-[60vh]"
                                style="font-family: Arial, sans-serif;">

                                <div x-show="questions.length === 0" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-4 opacity-50 block">quiz</span>
                                    <p class="font-medium text-sm">Adicione questões para visualizar.</p>
                                </div>

                                <div x-show="questions.length > 0">
                                    {{-- Header --}}
                                    <div class="text-center border-b-2 border-black pb-3 mb-4">
                                        <h3 class="text-lg font-bold text-black">{{ $exam->title }}</h3>
                                        <p class="text-sm text-red-600 font-bold mt-1">GABARITO DO PROFESSOR —
                                            CONFIDENCIAL</p>
                                    </div>

                                    {{-- Summary --}}
                                    <div class="flex gap-4 mb-4 text-sm text-gray-700">
                                        <span><strong>Total:</strong> <span x-text="questions.length"></span>
                                            questões</span>
                                        <span><strong>Pontos:</strong> <span
                                                x-text="totalPoints.toFixed(1)"></span></span>
                                        <span
                                            x-show="previewShuffle.questions || previewShuffle.mc || previewShuffle.tf"
                                            class="text-red-500 font-bold text-xs">(com embaralhamento)</span>
                                    </div>

                                    {{-- Answer Key Table (uses shuffled data) --}}
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="border border-gray-400 px-2 py-1.5 text-left font-bold">
                                                    Questão</th>
                                                <th class="border border-gray-400 px-2 py-1.5 text-left font-bold">Tipo
                                                </th>
                                                <th class="border border-gray-400 px-2 py-1.5 text-center font-bold">
                                                    Resposta</th>
                                                <th class="border border-gray-400 px-2 py-1.5 text-center font-bold">
                                                    Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(q, idx) in previewShuffledQuestions"
                                                :key="'key-' + q.id + '-' + _shuffleSeed">
                                                <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                                    <td class="border border-gray-400 px-2 py-1.5 font-bold"
                                                        x-text="'Q' + (idx + 1)"></td>
                                                    <td class="border border-gray-400 px-2 py-1.5 text-xs"
                                                        x-text="q.type === 'multiple_choice' ? 'MC' : q.type === 'true_false' ? 'V/F' : 'Dissert.'">
                                                    </td>
                                                    <td class="border border-gray-400 px-2 py-1.5 text-center font-bold text-primary text-base"
                                                        x-text="getCorrectAnswer(q)"></td>
                                                    <td class="border border-gray-400 px-2 py-1.5 text-center text-xs"
                                                        x-text="parseFloat(q.points).toFixed(1)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>

                                    {{-- Detailed Answers --}}
                                    <div class="mt-5 space-y-3">
                                        <h4
                                            class="font-bold text-xs text-gray-800 border-b border-gray-300 pb-1.5 uppercase tracking-wider">
                                            Detalhamento</h4>
                                        <template x-for="(q, idx) in previewShuffledQuestions"
                                            :key="'det-' + q.id + '-' + _shuffleSeed">
                                            <div class="text-xs pb-2 border-b border-dotted border-gray-200">
                                                <p class="font-bold mb-0.5">
                                                    <span x-text="'Questão ' + (idx + 1) + ':'"></span>
                                                    <span class="font-normal text-gray-600 ml-1"
                                                        x-text="(q.content?.statement || '').substring(0, 60) + ((q.content?.statement || '').length > 60 ? '...' : '')"></span>
                                                </p>
                                                <p class="pl-3 mt-0.5">
                                                    <span class="font-bold text-primary"
                                                        x-text="'Resposta: ' + getCorrectAnswer(q)"></span>
                                                    <span class="text-gray-500 ml-1"
                                                        x-text="' — ' + getCorrectAnswerDetail(q)"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Question Picker Modal --}}
        @include('exams.partials.question-picker-modal')

        {{-- Print Advanced Configuration Modal --}}
        @include('exams.partials.print-advanced-modal')
    </div>

    @php
        $questionsJson = $exam->questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'content' => $q->content,
                'level' => $q->level,
                'discipline' => $q->discipline?->name,
                'points' => $q->pivot->points,
                'order' => $q->pivot->order,
            ];
        })->values();
    @endphp

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('examBuilder', () => ({
                    // State
                    questions: {!! json_encode($questionsJson) !!},
                    showPicker: false,
                    previewMode: 'web',
                    previewPage: 1,
                    previewPerPage: 5,
                    printTab: 'exam',

                    // Shuffle preview state
                    previewShuffle: { questions: false, mc: false, tf: false },
                    _shuffleSeed: 0, // increment to re-shuffle

                    // Picker state
                    pickerTab: 'mine',
                    pickerSearch: '',
                    pickerFilters: { type: '', level: '', discipline_id: '', stage: '', grade: '', knowledge_area_id: '', sort: 'newest' },
                    showPickerFilters: false,
                    pickerResults: [],
                    pickerSelected: [],
                    pickerPoints: 1.0,
                    pickerLoading: false,
                    pickerAddingLoading: false,
                    pickerPage: 1,
                    pickerHasMore: false,
                    pickerExpanded: null,

                    // Computed
                    get totalPoints() {
                        return this.questions.reduce((sum, q) => sum + parseFloat(q.points || 0), 0);
                    },

                    get examQuestionIds() {
                        return this.questions.map(q => q.id);
                    },

                    get previewTotalPages() {
                        if (this.previewPerPage >= 999) return 1;
                        return Math.max(1, Math.ceil(this.questions.length / this.previewPerPage));
                    },

                    get previewPageQuestions() {
                        const perPage = this.previewPerPage >= 999 ? this.questions.length : this.previewPerPage;
                        const start = (this.previewPage - 1) * perPage;
                        return this.questions.slice(start, start + perPage).map((q, idx) => ({
                            ...q,
                            _globalIndex: start + idx + 1,
                        }));
                    },

                    // Shuffled questions for print preview (uses _shuffleSeed to trigger reactivity)
                    get previewShuffledQuestions() {
                        const seed = this._shuffleSeed; // reactive dependency
                        let qs = [...this.questions];
                        if (this.previewShuffle.questions) {
                            qs = this._shuffleArray(qs);
                        }
                        return qs.map((q, idx) => {
                            let optionsMap = null;
                            if (q.type === 'multiple_choice' && q.content?.options) {
                                let indices = q.content.options.map((_, i) => i);
                                if (this.previewShuffle.mc) indices = this._shuffleArray(indices);
                                optionsMap = indices;
                            } else if (q.type === 'true_false') {
                                let indices = [0, 1];
                                if (this.previewShuffle.tf) indices = this._shuffleArray(indices);
                                optionsMap = indices;
                            }
                            return { ...q, _globalIndex: idx + 1, _optionsMap: optionsMap };
                        });
                    },

                    // Paginated shuffled questions for print preview
                    get printPageQuestions() {
                        const all = this.previewShuffledQuestions;
                        const perPage = this.previewPerPage >= 999 ? all.length : this.previewPerPage;
                        const start = (this.previewPage - 1) * perPage;
                        return all.slice(start, start + perPage);
                    },

                    // Init
                    init() {
                        this.$nextTick(() => {
                            this.initSortable();
                        });
                    },

                    initSortable() {
                        const el = document.getElementById('questions-sortable');
                        if (!el || typeof Sortable === 'undefined') return;

                        Sortable.create(el, {
                            handle: '.drag-handle',
                            animation: 150,
                            ghostClass: 'opacity-30',
                            onEnd: (evt) => {
                                // Reorder in local state
                                const item = this.questions.splice(evt.oldIndex, 1)[0];
                                this.questions.splice(evt.newIndex, 0, item);

                                // Persist to server
                                const order = this.questions.map(q => q.id);
                                axios.post('{{ route("exams.reorderQuestions", $exam->id) }}', {
                                    order: order,
                                    _token: '{{ csrf_token() }}'
                                });
                            }
                        });
                    },

                    // Question actions
                    async removeQuestion(id) {
                        if (!confirm('Remover esta questão da prova?')) return;
                        try {
                            await axios.delete(`/exams/{{ $exam->id }}/questions/${id}`, {
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            });
                            this.questions = this.questions.filter(q => q.id !== id);
                            if (this.previewPage > this.previewTotalPages) {
                                this.previewPage = Math.max(1, this.previewTotalPages);
                            }
                        } catch (e) {
                            console.error('Erro ao remover questão', e);
                        }
                    },

                    async updateQuestionPoints(id, points) {
                        const q = this.questions.find(q => q.id === id);
                        if (q) q.points = parseFloat(points);
                        try {
                            await axios.put(`/exams/{{ $exam->id }}/questions/${id}/points`, {
                                points: parseFloat(points),
                                _token: '{{ csrf_token() }}'
                            });
                        } catch (e) {
                            console.error('Erro ao atualizar pontos', e);
                        }
                    },

                    // Picker methods
                    openPicker() {
                        this.showPicker = true;
                        this.pickerSelected = [];
                        this.pickerExpanded = null;
                        this.$nextTick(() => {
                            this.$refs.pickerClose?.focus();
                            this.fetchPickerQuestions();
                        });
                    },

                    async fetchPickerQuestions(loadMore = false) {
                        if (!loadMore) {
                            this.pickerPage = 1;
                        }
                        this.pickerLoading = true;

                        try {
                            const params = new URLSearchParams({
                                tab: this.pickerTab,
                                search: this.pickerSearch,
                                type: this.pickerFilters.type,
                                level: this.pickerFilters.level,
                                discipline_id: this.pickerFilters.discipline_id,
                                stage: this.pickerFilters.stage,
                                grade: this.pickerFilters.grade,
                                knowledge_area_id: this.pickerFilters.knowledge_area_id,
                                sort: this.pickerFilters.sort,
                                page: this.pickerPage,
                            });

                            const res = await axios.get(`{{ route("exams.searchQuestions", $exam->id) }}?${params}`);
                            const data = res.data;

                            // Mark in_exam based on current questions
                            const items = data.data.map(q => ({
                                ...q,
                                in_exam: this.examQuestionIds.includes(q.id)
                            }));

                            if (loadMore) {
                                this.pickerResults = [...this.pickerResults, ...items];
                            } else {
                                this.pickerResults = items;
                            }

                            this.pickerHasMore = data.next_page_url !== null;
                            if (this.pickerHasMore) this.pickerPage++;
                        } catch (e) {
                            console.error('Erro ao buscar questões', e);
                        }

                        this.pickerLoading = false;
                    },

                    togglePickerSelect(id) {
                        const idx = this.pickerSelected.indexOf(id);
                        if (idx === -1) {
                            this.pickerSelected.push(id);
                        } else {
                            this.pickerSelected.splice(idx, 1);
                        }
                    },

                    togglePickerExpand(id) {
                        this.pickerExpanded = this.pickerExpanded === id ? null : id;
                    },

                    getCorrectAnswer(q) {
                        const correctOrig = q.content?.correct_option ?? 0;
                        if (q._optionsMap) {
                            const shuffledIdx = q._optionsMap.indexOf(correctOrig);
                            if (shuffledIdx !== -1) return String.fromCharCode(65 + shuffledIdx);
                        }
                        if (q.type === 'multiple_choice' && q.content?.options) {
                            return String.fromCharCode(65 + correctOrig);
                        }
                        if (q.type === 'true_false') {
                            return correctOrig == 0 ? 'A' : 'B';
                        }
                        return 'Manual';
                    },

                    getCorrectAnswerDetail(q) {
                        const letter = this.getCorrectAnswer(q);
                        if (q.type === 'multiple_choice' && q.content?.options) {
                            const correctOrig = q.content.correct_option ?? 0;
                            return letter + ' — ' + (q.content.options[correctOrig] || '');
                        }
                        if (q.type === 'true_false') {
                            const correctOrig = q.content?.correct_option ?? 0;
                            return letter + ' (' + (correctOrig == 0 ? 'Verdadeiro' : 'Falso') + ')';
                        }
                        return 'Correção manual';
                    },

                    _shuffleArray(arr) {
                        const a = [...arr];
                        for (let i = a.length - 1; i > 0; i--) {
                            const j = Math.floor(Math.random() * (i + 1));
                            [a[i], a[j]] = [a[j], a[i]];
                        }
                        return a;
                    },

                    reshuffle() {
                        this._shuffleSeed++;
                    },

                    async addPickerSelected() {
                        if (this.pickerSelected.length === 0) return;
                        this.pickerAddingLoading = true;

                        try {
                            const res = await axios.post('{{ route("exams.addQuestions", $exam->id) }}', {
                                question_ids: this.pickerSelected,
                                points: this.pickerPoints,
                                _token: '{{ csrf_token() }}'
                            });

                            if (res.data.success) {
                                this.questions = res.data.questions;
                                // Mark added questions in results
                                this.pickerResults = this.pickerResults.map(q => ({
                                    ...q,
                                    in_exam: this.examQuestionIds.includes(q.id)
                                }));
                                this.pickerSelected = [];

                                // Re-init sortable
                                this.$nextTick(() => this.initSortable());
                            }
                        } catch (e) {
                            console.error('Erro ao adicionar questões', e);
                        }

                        this.pickerAddingLoading = false;
                    },
                }));
            });
        </script>
    @endpush
</x-app-layout>
