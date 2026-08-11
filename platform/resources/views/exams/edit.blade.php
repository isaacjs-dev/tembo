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

        <section class="card mb-6 p-4 md:p-6" aria-labelledby="assessment-wizard-heading">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="assessment-wizard-heading" class="text-lg font-extrabold text-duo-heading">Criação da Avaliação</h2>
                    <p class="text-xs text-gray-600">Seu rascunho é salvo enquanto você avança pelas oito etapas.</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-bold" role="status" aria-live="polite">
                    <span x-show="autosaveStatus === 'saving'" class="text-blue-700">Salvando…</span>
                    <span x-show="autosaveStatus === 'saved'" class="text-green-700">Rascunho salvo</span>
                    <span x-show="autosaveStatus === 'conflict'" class="text-red-700">Atualizado em outra aba — recarregue</span>
                    <span x-show="autosaveStatus === 'error'" class="text-red-700">Não foi possível salvar</span>
                </div>
            </div>
            <ol class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-8" aria-label="Etapas da Avaliação">
                @foreach($wizardSteps as $stepKey => $stepLabel)
                    <li>
                        <button type="button" @click="goToWizardStep('{{ $stepKey }}')"
                            :aria-current="wizardStep === '{{ $stepKey }}' ? 'step' : null"
                            :class="wizardStep === '{{ $stepKey }}'
                                ? 'border-primary bg-primary/10 text-primary'
                                : (wizardCompleted.includes('{{ $stepKey }}')
                                    ? 'border-green-300 bg-green-50 text-green-800'
                                    : 'border-duo-border bg-white text-gray-600')"
                            class="min-h-16 w-full rounded-xl border-2 px-2 py-3 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            <span class="block text-[10px] font-extrabold uppercase tracking-wider">Etapa {{ $loop->iteration }}</span>
                            <span class="mt-1 block text-xs font-bold">{{ $stepLabel }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- 5-col grid: 3-col editor + 2-col preview --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

            {{-- Left Column: Settings + Questions + Sidebar --}}
            <div x-show="['information', 'questions', 'audience', 'application', 'appearance', 'publication'].includes(wizardStep)"
                :class="['questions', 'audience'].includes(wizardStep) ? 'lg:col-span-5' : 'lg:col-span-3'"
                class="space-y-6" x-cloak>

                {{-- Settings Card --}}
                <div x-show="['information', 'application', 'appearance', 'publication'].includes(wizardStep)" class="card p-8" x-cloak>
                    <h2
                        class="text-xl font-extrabold text-duo-heading mb-6 flex items-center gap-2 border-b-2 border-duo-border pb-4">
                        <span class="material-symbols-outlined text-primary">settings</span> Configurações Gerais
                    </h2>
                    <form id="exam-settings-form" action="{{ route('exams.update', $exam->id) }}" method="POST"
                        @submit.prevent="submitSettingsForm($event)" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_form" value="1">
                        <input type="hidden" name="wizard_revision" :value="wizardRevision">
                        <input type="hidden" name="wizard_step" :value="wizardStep">

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

                        <div x-show="wizardStep === 'information'" class="space-y-2" x-cloak>
                            <label for="title" class="input-label">Título da Avaliação</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $exam->title) }}" required
                                @input="queueWizardAutosave('information')"
                                class="input-field">
                        </div>

                        <div x-show="wizardStep === 'information'" class="space-y-2" x-cloak>
                            <label for="instructions" class="input-label">Instruções ao estudante</label>
                            <textarea id="instructions" name="instructions" rows="4" maxlength="10000"
                                @input="queueWizardAutosave('information')"
                                class="input-field"
                                placeholder="Materiais permitidos, critérios e orientações.">{{ old('instructions', $exam->settings['instructions'] ?? '') }}</textarea>
                        </div>

                        <div x-show="['application', 'publication'].includes(wizardStep)" class="grid grid-cols-1 md:grid-cols-2 gap-6" x-cloak>
                            <div x-show="wizardStep === 'publication'" class="space-y-2" x-cloak>
                                <label for="status" class="input-label">Status da Avaliação</label>
                                <select id="status" name="status" class="input-field">
                                    <option value="draft" {{ old('status', $exam->status) == 'draft' ? 'selected' : '' }}>
                                        Rascunho (invisível)</option>
                                    <option value="published" {{ old('status', $exam->status) == 'published' ? 'selected' : '' }}>Publicada</option>
                                    <option value="closed" {{ old('status', $exam->status) == 'closed' ? 'selected' : '' }}>Encerrada</option>
                                </select>
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="application_mode" class="input-label">Modalidade</label>
                                <select id="application_mode" name="application_mode" @change="queueWizardAutosave('application')" class="input-field">
                                    @foreach($applicationModes as $mode => $label)
                                        <option value="{{ $mode }}" @selected(old('application_mode', $exam->settings['application_mode'] ?? 'hybrid') === $mode)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="digital_presentation" class="input-label">Apresentação digital</label>
                                <select id="digital_presentation" name="digital_presentation"
                                    @change="queueWizardAutosave('application')" class="input-field">
                                    <option value="auto" @selected(old('digital_presentation', $exam->settings['digital_presentation'] ?? 'auto') === 'auto')>Automática pelo conteúdo</option>
                                    <option value="paginated" @selected(old('digital_presentation', $exam->settings['digital_presentation'] ?? '') === 'paginated')>Quantidade fixa por tela</option>
                                    <option value="full" @selected(old('digital_presentation', $exam->settings['digital_presentation'] ?? '') === 'full')>Avaliação inteira</option>
                                </select>
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="questions_per_page" class="input-label">Questões por tela</label>
                                <input type="number" id="questions_per_page" name="questions_per_page"
                                    value="{{ old('questions_per_page', $exam->settings['questions_per_page'] ?? 5) }}"
                                    min="1" max="20" @input="queueWizardAutosave('application')" class="input-field">
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="attempts" class="input-label">Tentativas Permitidas</label>
                                <input type="number" id="attempts" name="attempts"
                                    value="{{ old('attempts', $exam->settings['attempts'] ?? 1) }}" min="1"
                                    max="20" @input="queueWizardAutosave('application')" class="input-field">
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="time_limit" class="input-label">Tempo Limite (Minutos)</label>
                                <input type="number" id="time_limit" name="time_limit"
                                    value="{{ old('time_limit', $exam->settings['time_limit'] ?? '') }}" min="1" max="1440"
                                    @input="queueWizardAutosave('application')" class="input-field" placeholder="Sem limite">
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="available_from" class="input-label">Disponível a partir de</label>
                                <input type="datetime-local" id="available_from" name="available_from"
                                    value="{{ old('available_from', $settingDate('available_from')) }}"
                                    @change="queueWizardAutosave('application')" class="input-field">
                            </div>
                            <div x-show="wizardStep === 'application'" class="space-y-2" x-cloak>
                                <label for="available_until" class="input-label">Prazo final</label>
                                <input type="datetime-local" id="available_until" name="available_until"
                                    value="{{ old('available_until', $settingDate('available_until')) }}"
                                    @change="queueWizardAutosave('application')" class="input-field">
                            </div>
                        </div>

                        <div x-show="['appearance', 'publication'].includes(wizardStep)" class="grid grid-cols-1 gap-4 rounded-xl border-2 border-duo-border bg-gray-50 p-5" x-cloak>
                            <fieldset x-show="wizardStep === 'appearance'" class="space-y-3" x-cloak>
                                <legend class="mb-2 font-extrabold text-duo-heading">Apresentação</legend>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="shuffle_questions" value="1" @change="queueWizardAutosave('appearance')"
                                        @checked(old('shuffle_questions', $exam->settings['shuffle_questions'] ?? false))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Embaralhar questões</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="shuffle_options" value="1" @change="queueWizardAutosave('appearance')"
                                        @checked(old('shuffle_options', $exam->settings['shuffle_options'] ?? false))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Embaralhar alternativas</span>
                                </label>
                            </fieldset>

                            <fieldset x-show="wizardStep === 'publication'" class="space-y-3" x-cloak>
                                <legend class="mb-2 font-extrabold text-duo-heading">Resultados liberados</legend>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_score" value="1" @change="queueWizardAutosave('publication')"
                                        @checked(old('show_score', $exam->settings['show_score'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Nota</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_feedback" value="1" @change="queueWizardAutosave('publication')"
                                        @checked(old('show_feedback', $exam->settings['show_feedback'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Comentários e recomendações</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="show_answers" value="1" @change="queueWizardAutosave('publication')"
                                        @checked(old('show_answers', $exam->settings['show_answers'] ?? ($exam->settings['show_results'] ?? false)))
                                        class="size-6 text-primary border-gray-400 rounded focus:ring-primary">
                                    <span class="font-bold text-gray-800 text-sm">Respostas e gabarito</span>
                                </label>
                                <div>
                                    <label for="results_available_from" class="input-label">Liberar a partir de</label>
                                    <input type="datetime-local" id="results_available_from"
                                        name="results_available_from"
                                        value="{{ old('results_available_from', $settingDate('results_available_from')) }}"
                                        @change="queueWizardAutosave('publication')" class="input-field">
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
                <div x-show="wizardStep === 'questions'" class="card p-8" x-cloak>
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

                {{-- Audience + Publish (row) --}}
                <div x-show="['audience', 'publication'].includes(wizardStep)" class="grid grid-cols-1 gap-6" x-cloak>
                    {{-- Disciplina e público --}}
                    <div x-show="wizardStep === 'audience'" class="card p-6" x-cloak>
                        <h2
                            class="text-lg font-extrabold text-duo-heading mb-4 flex items-center gap-2 border-b-2 border-duo-border pb-3">
                            <span class="material-symbols-outlined text-secondary">groups</span> Disciplina e público
                        </h2>
                        <form action="{{ route('exams.syncAudience', $exam->id) }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label for="discipline_id" class="input-label">Disciplina (opcional)</label>
                                <select id="discipline_id" name="discipline_id" class="input-field">
                                    <option value="">Sem disciplina definida</option>
                                    @foreach($availableDisciplines as $discipline)
                                        <option value="{{ $discipline->id }}" @selected((string) old('discipline_id', $exam->discipline_id) === (string) $discipline->id)>
                                            {{ $discipline->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <fieldset>
                                <legend class="mb-2 text-sm font-extrabold text-duo-heading">Turmas</legend>
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
                            </fieldset>
                            <fieldset>
                                <legend class="mb-2 text-sm font-extrabold text-duo-heading">Alunos específicos</legend>
                                <div class="max-h-48 overflow-y-auto space-y-2 pr-2">
                                    @php $assignedStudents = $exam->students->pluck('id')->toArray(); @endphp
                                    @forelse($availableStudents as $student)
                                        <label class="flex items-center gap-3 rounded-lg border-2 border-duo-border p-3 transition-colors hover:border-primary/50 {{ in_array($student->id, $assignedStudents) ? 'border-primary bg-primary/10' : '' }}">
                                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                                                @checked(in_array($student->id, old('student_ids', $assignedStudents)))
                                                class="size-5 rounded border-gray-300 text-primary focus:ring-primary">
                                            <span class="min-w-0 text-sm">
                                                <span class="block truncate font-bold text-gray-800">{{ $student->name }}</span>
                                                <span class="block truncate text-xs text-gray-500">{{ $student->email }}</span>
                                            </span>
                                        </label>
                                    @empty
                                        <p class="text-center text-xs italic text-gray-500">Nenhum aluno disponível no seu escopo.</p>
                                    @endforelse
                                </div>
                            </fieldset>
                            <p class="text-xs text-gray-500">Você pode combinar turmas e alunos ou deixar o público vazio enquanto prepara a avaliação.</p>
                            <button type="submit"
                                class="w-full btn-secondary py-3 rounded-lg font-bold text-xs uppercase tracking-wider block">
                                Atualizar público
                            </button>
                        </form>
                    </div>

                    {{-- Publishing --}}
                    <div x-show="wizardStep === 'publication'" x-cloak
                        class="bg-primary/5 rounded-2xl border-2 border-primary/20 p-6 text-center shadow-sm flex flex-col justify-center">
                        <h3 class="font-extrabold text-primary mb-2 text-lg">Pronto para lançar?</h3>
                        <p class="text-xs text-gray-600 mb-6 font-medium px-4">Após adicionar as questões e turmas alvo,
                            publique a prova.</p>
                        <form action="{{ route('exams.update', $exam->id) }}" method="POST"
                            @submit.prevent="submitStatusForm($event)">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="title" value="{{ $exam->title }}">
                            <input type="hidden" name="wizard_revision" :value="wizardRevision">
                            <input type="hidden" name="wizard_step" :value="wizardStep">

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
            <div x-show="['appearance', 'answer_sheet', 'preview'].includes(wizardStep)"
                :class="wizardStep === 'appearance' ? 'lg:col-span-2' : 'lg:col-span-5'"
                class="lg:sticky lg:top-8" x-cloak>
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

                            <div class="mb-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs font-semibold text-blue-800">
                                Esta prévia mostra o conteúdo e a aparência atuais. Embaralhamento e individualização são aplicados somente ao gerar o lote; cada cópia gerada preserva seu próprio snapshot.
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
                            <div x-show="printTab === 'exam'" class="w-full overflow-hidden rounded-xl border-2 border-gray-300 bg-gray-100 shadow-md">
                                <div class="flex justify-end border-b border-gray-200 bg-white px-3 py-2">
                                    <button type="button"
                                        @click="$refs.canonicalPrintPreview.src = $refs.canonicalPrintPreview.src"
                                        class="inline-flex min-h-11 items-center gap-1 rounded-lg px-3 text-xs font-extrabold text-primary hover:bg-primary/5">
                                        <span class="material-symbols-outlined text-base">refresh</span>
                                        Atualizar preview real
                                    </button>
                                </div>
                                <iframe
                                    x-ref="canonicalPrintPreview"
                                    src="{{ route('exams.previewPrint', $exam->id) }}"
                                    title="Pré-visualização canônica da Avaliação impressa"
                                    class="h-[70vh] min-h-[560px] w-full bg-white"
                                    loading="lazy"
                                    sandbox></iframe>
                            </div>

                            {{-- Renderizador local legado mantido temporariamente apenas para comparação durante PREV-001. --}}
                            <div x-show="false"
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

        <nav class="sticky bottom-4 z-20 mt-6 flex items-center justify-between gap-3 rounded-2xl border-2 border-duo-border bg-white/95 p-4 shadow-lg backdrop-blur"
            aria-label="Navegação entre etapas">
            <button type="button" @click="goToWizardStep(previousWizardStep, false)" :disabled="!previousWizardStep"
                class="btn-secondary min-w-28 disabled:cursor-not-allowed disabled:opacity-40">
                Voltar
            </button>
            <div class="text-center">
                <p class="text-xs font-extrabold text-duo-heading" x-text="wizardStepLabel"></p>
                <p class="text-[11px] text-gray-500" x-text="(wizardIndex + 1) + ' de ' + wizardStepKeys.length"></p>
            </div>
            <button type="button" x-show="nextWizardStep" @click="goToWizardStep(nextWizardStep, true)"
                class="btn-primary min-w-28">
                Salvar e avançar
            </button>
            <span x-show="!nextWizardStep" class="min-w-28 text-right text-xs font-bold text-green-700">Etapa final</span>
        </nav>

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
                    wizardStepKeys: @json(array_keys($wizardSteps)),
                    wizardStepLabels: @json($wizardSteps),
                    wizardStep: @json($wizardState['current_step']),
                    wizardCompleted: @json($wizardState['completed_steps']),
                    wizardRevision: @json($wizardState['revision']),
                    wizardAutosaveEnabled: @json($exam->status === 'draft'),
                    autosaveStatus: 'idle',
                    _autosaveTimer: null,
                    _autosaveChain: Promise.resolve(),
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

                    get wizardIndex() {
                        return Math.max(0, this.wizardStepKeys.indexOf(this.wizardStep));
                    },

                    get previousWizardStep() {
                        return this.wizardIndex > 0 ? this.wizardStepKeys[this.wizardIndex - 1] : null;
                    },

                    get nextWizardStep() {
                        return this.wizardIndex < this.wizardStepKeys.length - 1
                            ? this.wizardStepKeys[this.wizardIndex + 1]
                            : null;
                    },

                    get wizardStepLabel() {
                        return this.wizardStepLabels[this.wizardStep] || '';
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
                        this.syncWizardPreview();
                        this.$nextTick(() => {
                            this.initSortable();
                        });
                    },

                    syncWizardPreview() {
                        if (this.wizardStep === 'answer_sheet') {
                            this.previewMode = 'print';
                            this.printTab = 'answer_sheet';
                        } else if (this.wizardStep === 'appearance') {
                            this.previewMode = 'print';
                            this.printTab = 'exam';
                        }
                    },

                    wizardPayload(step) {
                        const fields = {
                            information: ['title', 'instructions'],
                            application: ['application_mode', 'digital_presentation', 'questions_per_page', 'time_limit', 'attempts', 'available_from', 'available_until'],
                            appearance: ['shuffle_questions', 'shuffle_options'],
                            publication: ['show_score', 'show_answers', 'show_feedback', 'results_available_from'],
                        }[step] || [];
                        const form = document.getElementById('exam-settings-form');

                        return fields.reduce((payload, name) => {
                            const field = form?.elements.namedItem(name);
                            if (!field) return payload;

                            payload[name] = field.type === 'checkbox'
                                ? field.checked
                                : (field.value === '' ? null : field.value);

                            return payload;
                        }, {});
                    },

                    queueWizardAutosave(step) {
                        if (!this.wizardAutosaveEnabled) return;

                        window.clearTimeout(this._autosaveTimer);
                        this._autosaveTimer = window.setTimeout(() => {
                            this.saveWizardStep(step);
                        }, 700);
                    },

                    saveWizardStep(step, complete = false, targetStep = null) {
                        if (!this.wizardAutosaveEnabled) return Promise.resolve(true);

                        window.clearTimeout(this._autosaveTimer);
                        const operation = async () => {
                            this.autosaveStatus = 'saving';

                            try {
                                const response = await axios.patch('{{ route('exams.autosaveDraft', $exam->id) }}', {
                                    step,
                                    payload: this.wizardPayload(step),
                                    revision: this.wizardRevision,
                                    complete,
                                    target_step: targetStep,
                                }, {
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                });

                                const wizard = response.data.wizard;
                                this.wizardRevision = wizard.revision;
                                this.wizardCompleted = wizard.completed_steps;
                                this.wizardStep = wizard.current_step;
                                this.autosaveStatus = 'saved';
                                this.syncWizardPreview();

                                return true;
                            } catch (error) {
                                this.autosaveStatus = error.response?.status === 409 ? 'conflict' : 'error';

                                return false;
                            }
                        };

                        this._autosaveChain = this._autosaveChain.then(operation, operation);

                        return this._autosaveChain;
                    },

                    syncWizardFormState(form) {
                        const revision = form.elements.namedItem('wizard_revision');
                        const step = form.elements.namedItem('wizard_step');
                        if (revision) revision.value = this.wizardRevision;
                        if (step) step.value = this.wizardStep;
                    },

                    async submitSettingsForm(event) {
                        const form = event.currentTarget;
                        const saved = await this.saveWizardStep(this.wizardStep);
                        if (!saved) return;

                        this.syncWizardFormState(form);
                        HTMLFormElement.prototype.submit.call(form);
                    },

                    async submitStatusForm(event) {
                        const form = event.currentTarget;
                        const desiredStatus = form.elements.namedItem('status')?.value;
                        if (desiredStatus === 'published') {
                            const saved = await this.saveWizardStep('publication', true, 'publication');
                            if (!saved) return;
                        }

                        const currentTitle = document.getElementById('title')?.value;
                        if (currentTitle) form.elements.namedItem('title').value = currentTitle;
                        this.syncWizardFormState(form);
                        HTMLFormElement.prototype.submit.call(form);
                    },

                    async goToWizardStep(targetStep, completeCurrent = true) {
                        if (!targetStep || targetStep === this.wizardStep || this.autosaveStatus === 'conflict') return;

                        if (!this.wizardAutosaveEnabled) {
                            this.wizardStep = targetStep;
                            this.syncWizardPreview();

                            return;
                        }

                        await this.saveWizardStep(this.wizardStep, completeCurrent, targetStep);
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
