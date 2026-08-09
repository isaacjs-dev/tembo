<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="{{ route('questions.index') }}">Banco de
                        Questões</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar Questão</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Questão</h1>
            </div>
            <a href="{{ route('questions.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <!-- Layout Dividido (Editor + Preview) -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-12 w-full mx-auto items-start">

        <!-- Esquerda: Formulário -->
        <div class="lg:col-span-3 bg-white rounded-2xl border-2 border-duo-border p-8 shadow-sm">
            @if ($errors->any())
                <div
                    class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('questions.update', $question->id) }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')

                <!-- Section 1: Basic Configs -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Tipo -->
                    <div class="space-y-2">
                        <label for="type" class="text-gray-800 font-bold text-sm">Tipo de Questão</label>
                        <select id="type" name="type"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="multiple_choice" {{ old('type', $question->type) == 'multiple_choice' ? 'selected' : '' }}>Múltipla Escolha</option>
                            <option value="true_false" {{ old('type', $question->type) == 'true_false' ? 'selected' : '' }}>Verdadeiro ou Falso</option>
                            <option value="essay" {{ old('type', $question->type) == 'essay' ? 'selected' : '' }}>
                                Dissertativa</option>
                        </select>
                    </div>

                    <!-- Visibilidade -->
                    <div class="space-y-2">
                        <label for="visibility_scope" class="text-gray-800 font-bold text-sm">Visibilidade</label>
                        <select id="visibility_scope" name="visibility_scope"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="private" {{ old('visibility_scope', $question->visibility_scope) == 'private' ? 'selected' : '' }}>Privada (Só eu)</option>
                            @if(old('visibility_scope', $question->visibility_scope) === 'shared_specific')
                                <option value="shared_specific" selected>Compartilhada com professores selecionados</option>
                            @endif
                            <option value="org_public" {{ old('visibility_scope', $question->visibility_scope) == 'org_public' ? 'selected' : '' }}>Pública na Instituição
                            </option>
                        </select>
                    </div>

                    <!-- Nível -->
                    <div class="space-y-2">
                        <label for="level" class="text-gray-800 font-bold text-sm">Dificuldade</label>
                        <select id="level" name="level"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="">Selecione...</option>
                            <option value="very_easy" {{ old('level', $question->level) == 'very_easy' ? 'selected' : '' }}>Muito Fácil</option>
                            <option value="easy" {{ old('level', $question->level) == 'easy' ? 'selected' : '' }}>Fácil
                            </option>
                            <option value="medium" {{ old('level', $question->level) == 'medium' ? 'selected' : '' }}>
                                Médio</option>
                            <option value="hard" {{ old('level', $question->level) == 'hard' ? 'selected' : '' }}>Difícil
                            </option>
                            <option value="very_hard" {{ old('level', $question->level) == 'very_hard' ? 'selected' : '' }}>Muito Difícil</option>
                        </select>
                    </div>
                </div>

                <!-- Section 1.5: Etapa e Série -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-data="{
                    stage: '{{ old('stage', $question->stage ?? '') }}',
                    savedGrade: '{{ old('grade', $question->grade ?? '') }}',
                    grade: '',
                    get availableGrades() {
                        if (this.stage === 'em') return [
                            {v:'1', l:'1ª Série'}, {v:'2', l:'2ª Série'}, {v:'3', l:'3ª Série'}
                        ];
                        if (this.stage === 'ef_iniciais' || this.stage === 'ef_finais') {
                            return Array.from({length:9}, (_, i) => ({v: String(i+1), l: `${i+1}º Ano`}));
                        }
                        return [];
                    },
                    init() {
                        this.$nextTick(() => { this.grade = this.savedGrade; });
                    }
                }">
                    <!-- Etapa -->
                    <div class="space-y-2">
                        <label for="stage" class="text-gray-800 font-bold text-sm">Etapa</label>
                        <select id="stage" name="stage" x-model="stage" @change="grade = ''" required
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="">Selecione a Etapa...</option>
                            <option value="ef_iniciais">Ensino Fundamental - Anos Iniciais</option>
                            <option value="ef_finais">Ensino Fundamental - Anos Finais</option>
                            <option value="em">Ensino Médio</option>
                        </select>
                    </div>

                    <!-- Ano/Série -->
                    <div class="space-y-2">
                        <label for="grade" class="text-gray-800 font-bold text-sm">Ano/Série</label>
                        <select id="grade" name="grade" x-model="grade" :disabled="!stage" required
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                            <option value="">Selecione o Ano/Série...</option>
                            <template x-for="item in availableGrades" :key="item.v">
                                <option :value="item.v" x-text="item.l" :selected="item.v === grade"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Section 2: Area / Disciplina -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Área de Conhecimento -->
                    <div class="space-y-2" x-data="{
                        showNewInput: false,
                        newName: '',
                        loading: false,
                        async saveNew() {
                            if(!this.newName) return;
                            this.loading = true;
                            try {
                                const res = await axios.post('{{ route('institution.knowledge-areas.store') }}', { name: this.newName });
                                const select = document.getElementById('knowledge_area_id');
                                const option = new Option(res.data.name, res.data.id, true, true);
                                select.add(option);
                                this.showNewInput = false;
                                this.newName = '';
                            } catch (e) {
                                alert('Erro ao salvar Área de Conhecimento.');
                            }
                            this.loading = false;
                        }
                    }">
                        <div class="flex items-center justify-between">
                            <label for="knowledge_area_id" class="text-gray-800 font-bold text-sm">Área de
                                Conhecimento</label>
                            <button type="button" @click="showNewInput = !showNewInput"
                                class="text-xs font-bold text-primary hover:underline">
                                + Nova Área
                            </button>
                        </div>

                        <div x-show="showNewInput" style="display: none;"
                            class="flex gap-2 mb-2 p-3 bg-primary/5 rounded-xl border border-primary/20">
                            <input type="text" x-model="newName" placeholder="Nome da Área..."
                                aria-label="Nome da nova área de conhecimento"
                                class="flex-1 px-3 py-2 text-sm border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0">
                            <button type="button" @click="saveNew" :disabled="loading"
                                class="bg-primary text-white px-3 py-2 rounded-lg text-sm font-bold disabled:opacity-50">
                                Salvar
                            </button>
                        </div>

                        <select id="knowledge_area_id" name="knowledge_area_id"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="">Selecione... (Opcional)</option>
                            @foreach($knowledgeAreas as $ka)
                                <option value="{{ $ka->id }}" {{ old('knowledge_area_id', $question->knowledge_area_id) == $ka->id ? 'selected' : '' }}>{{ $ka->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Disciplina -->
                    <div class="space-y-2" x-data="{
                        showNewInput: false,
                        newName: '',
                        loading: false,
                        async saveNew() {
                            if(!this.newName) return;
                            this.loading = true;
                            try {
                                const res = await axios.post('{{ route('institution.disciplines.store') }}', { name: this.newName });
                                const select = document.getElementById('discipline_id');
                                const option = new Option(res.data.name, res.data.id, true, true);
                                select.add(option);
                                this.showNewInput = false;
                                this.newName = '';
                            } catch (e) {
                                alert('Erro ao salvar Disciplina.');
                            }
                            this.loading = false;
                        }
                    }">
                        <div class="flex items-center justify-between">
                            <label for="discipline_id" class="text-gray-800 font-bold text-sm">Disciplina</label>
                            <button type="button" @click="showNewInput = !showNewInput"
                                class="text-xs font-bold text-primary hover:underline">
                                + Nova Disciplina
                            </button>
                        </div>

                        <div x-show="showNewInput" style="display: none;"
                            class="flex gap-2 mb-2 p-3 bg-primary/5 rounded-xl border border-primary/20">
                            <input type="text" x-model="newName" placeholder="Nome da Disciplina..."
                                aria-label="Nome da nova disciplina"
                                class="flex-1 px-3 py-2 text-sm border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0">
                            <button type="button" @click="saveNew" :disabled="loading"
                                class="bg-primary text-white px-3 py-2 rounded-lg text-sm font-bold disabled:opacity-50">
                                Salvar
                            </button>
                        </div>

                        <select id="discipline_id" name="discipline_id"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                            <option value="">Selecione... (Opcional)</option>
                            @foreach($disciplines as $disc)
                                <option value="{{ $disc->id }}" {{ old('discipline_id', $question->discipline_id) == $disc->id ? 'selected' : '' }}>{{ $disc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Section 2.4: Custom Skills -->
                @include('questions.partials.custom-skills-card')

                <!-- Section 2.5: BNCC -->
                @include('questions.partials.bncc-card')

                <!-- Section 3: Texto da Questão -->
                <div class="space-y-2">
                    <label for="statement" class="text-gray-800 font-bold text-sm">Enunciado da Questão</label>
                    <textarea id="statement" name="statement" rows="4" required
                        class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="Digite o texto da questão aqui...">{{ old('statement', $question->content['statement'] ?? '') }}</textarea>
                </div>

                @include('questions.partials.resources-card')

                <!-- Section 4: Alternativas (Multiple Choice) -->
                <div id="multiple_choice_section" role="group" aria-labelledby="multiple-choice-heading"
                    class="space-y-4 p-6 bg-gray-50 border-2 border-duo-border rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <h2 id="multiple-choice-heading" class="text-lg font-bold text-gray-800">Alternativas</h2>
                        <span class="text-sm font-medium text-gray-500">Marque a correta no círculo.</span>
                    </div>

                    @php
                        $options = $question->content['options'] ?? [];
                        $correct_option = $question->content['correct_option'] ?? 0;
                    @endphp

                    @for ($i = 0; $i < 5; $i++)
                        <div class="flex items-start gap-4 alternative-row">
                            <div class="pt-3 flex-shrink-0">
                                <input type="radio" name="correct_option" value="{{ $i }}" {{ old('correct_option', $correct_option) == $i ? 'checked' : '' }} required
                                    aria-label="Marcar a alternativa {{ chr(65 + $i) }} como correta"
                                    class="size-6 text-primary focus:ring-primary border-gray-300">
                            </div>
                            <div class="flex-1">
                                <label for="question-option-{{ $i }}" class="sr-only">Texto da alternativa {{ chr(65 + $i) }}</label>
                                <textarea id="question-option-{{ $i }}" name="options[]" rows="2"
                                    class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium option-input"
                                    placeholder="Alternativa {{ chr(65 + $i) }}">{{ old('options.' . $i, $options[$i] ?? '') }}</textarea>
                            </div>
                        </div>
                    @endfor
                </div>

                <!-- Section 4b: Verdadeiro ou Falso -->
                @php
                    $tf_answer = $question->content['correct_option'] ?? 0;
                @endphp
                <div id="true_false_section" style="display: none;" role="group" aria-labelledby="true-false-heading"
                    class="space-y-4 p-6 bg-gray-50 border-2 border-duo-border rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <h2 id="true-false-heading" class="text-lg font-bold text-gray-800">Resposta Correta</h2>
                        <span class="text-sm font-medium text-gray-500">Selecione a resposta correta.</span>
                    </div>

                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 border-duo-border bg-white cursor-pointer hover:border-primary/50 transition-all tf-option">
                        <input type="radio" name="tf_answer" value="0" {{ old('tf_answer', $question->type === 'true_false' ? $tf_answer : '0') == '0' ? 'checked' : '' }}
                            class="size-6 text-primary focus:ring-primary border-gray-300">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="material-symbols-outlined text-green-500 text-2xl">check_circle</span>
                            <span class="font-bold text-gray-800 text-lg">Verdadeiro</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-4 p-4 rounded-2xl border-2 border-duo-border bg-white cursor-pointer hover:border-primary/50 transition-all tf-option">
                        <input type="radio" name="tf_answer" value="1" {{ old('tf_answer', $question->type === 'true_false' ? $tf_answer : '') == '1' ? 'checked' : '' }}
                            class="size-6 text-primary focus:ring-primary border-gray-300">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="material-symbols-outlined text-red-500 text-2xl">cancel</span>
                            <span class="font-bold text-gray-800 text-lg">Falso</span>
                        </div>
                    </label>
                </div>

                @include('questions.partials.rubric-card')

                <div class="pt-6 border-t-2 border-duo-border flex justify-end">
                    <button type="submit"
                        class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                        Atualizar Questão
                    </button>
                </div>
            </form>
        </div>

        <!-- Direita: Preview Panel -->
        <div
            class="lg:col-span-2 bg-background-light rounded-2xl border-2 border-duo-border p-6 lg:sticky lg:top-8 flex flex-col h-full min-h-[600px] shadow-sm">
            <div class="flex items-center justify-between mb-6 border-b-2 border-gray-200 pb-4">
                <div class="flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-gray-500">visibility</span>
                    <h2 class="text-lg font-extrabold text-gray-800">Pré-visualização</h2>
                </div>
                <!-- Toggle Web/Mobile -->
                <div class="flex bg-gray-200 p-1 rounded-xl items-center">
                    <button id="btn-preview-web" type="button"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors bg-white text-gray-800 shadow-sm flex items-center gap-1">
                        <span aria-hidden="true" class="material-symbols-outlined text-[16px]">desktop_windows</span> Web
                    </button>
                    <button id="btn-preview-mobile" type="button"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors text-gray-500 hover:text-gray-800 flex items-center gap-1">
                        <span aria-hidden="true" class="material-symbols-outlined text-[16px]">smartphone</span> Mobile
                    </button>
                </div>
            </div>

            <!-- Validation/Fallback Layer -->
            <div id="preview-wrapper"
                class="flex-1 flex justify-center items-start overflow-hidden transition-all duration-300 w-full relative">

                <!-- Inner Box that imitates the final rendering -->
                <div id="preview-box"
                    class="w-full bg-white rounded-2xl border-2 border-duo-border p-6 shadow-sm transition-all duration-300 ease-in-out">
                    <!-- Placeholder quando vazio -->
                    <div id="preview-placeholder" class="text-center py-12 text-gray-400">
                        <span aria-hidden="true" class="material-symbols-outlined text-4xl mb-4 opacity-50 block">edit_document</span>
                        <p class="font-medium text-sm">Digite o enunciado para visualizar...</p>
                    </div>

                    <!-- Renderização da Questão -->
                    <div id="preview-content" class="hidden h-full flex-col">
                        <div class="mb-4 flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20"
                                id="preview-type-badge">
                                Múltipla Escolha
                            </span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200 hidden"
                                id="preview-level-badge">
                                Fácil
                            </span>
                        </div>

                        <div id="preview-statement"
                            class="text-gray-800 font-medium text-base md:text-lg mb-6 whitespace-pre-wrap leading-relaxed">
                            <!-- Enunciado vai aqui -->
                        </div>

                        <!-- Alternativas (Multiple Choice) -->
                        <div id="preview-options-container" class="space-y-3">
                            <!-- JS Inject -->
                        </div>

                        <!-- Textarea (Essay) -->
                        <div id="preview-essay-container" class="hidden">
                            <textarea disabled rows="5"
                                aria-label="Exemplo do campo de resposta dissertativa do aluno"
                                class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-400 cursor-not-allowed resize-none"
                                placeholder="O aluno digitará a resposta aqui..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // == ELEMENT REFERENCES ==
            const typeSelect = document.getElementById('type');
            const levelSelect = document.getElementById('level');
            const statementInput = document.getElementById('statement');
            const optionInputs = document.querySelectorAll('.option-input');
            const radioInputs = document.querySelectorAll('input[name="correct_option"]');
            const mcSection = document.getElementById('multiple_choice_section');
            const tfSection = document.getElementById('true_false_section');
            const essaySection = document.getElementById('essay_section');
            const tfRadios = document.querySelectorAll('input[name="tf_answer"]');

            const previewBox = document.getElementById('preview-box');
            const previewPlaceholder = document.getElementById('preview-placeholder');
            const previewContent = document.getElementById('preview-content');
            const previewStatement = document.getElementById('preview-statement');
            const previewOptionsContainer = document.getElementById('preview-options-container');
            const previewEssayContainer = document.getElementById('preview-essay-container');
            const previewTypeBadge = document.getElementById('preview-type-badge');
            const previewLevelBadge = document.getElementById('preview-level-badge');

            const btnWeb = document.getElementById('btn-preview-web');
            const btnMobile = document.getElementById('btn-preview-mobile');

            let debounceTimer;

            // == TOGGLE SECTIONS ==
            function toggleSection() {
                const type = typeSelect.value;
                mcSection.style.display = type === 'multiple_choice' ? 'block' : 'none';
                tfSection.style.display = type === 'true_false' ? 'block' : 'none';
                essaySection.style.display = type === 'essay' ? 'block' : 'none';
                updatePreview();
            }

            // == PREVIEW LOGIC ==
            function updatePreview() {
                const statementText = statementInput.value.trim();
                const qType = typeSelect.value;
                const levelSel = levelSelect.options[levelSelect.selectedIndex];

                if (!statementText && qType !== 'true_false' && qType !== 'essay') {
                    previewPlaceholder.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    return;
                } else if (!statementText) {
                    previewPlaceholder.classList.remove('hidden');
                    previewContent.classList.add('hidden');
                    return;
                }

                previewPlaceholder.classList.add('hidden');
                previewContent.classList.remove('hidden');

                // Update Statement
                previewStatement.textContent = statementText;

                // Update Badges
                if (qType === 'multiple_choice') previewTypeBadge.textContent = 'Múltipla Escolha';
                else if (qType === 'true_false') previewTypeBadge.textContent = 'Verdadeiro ou Falso';
                else if (qType === 'essay') previewTypeBadge.textContent = 'Dissertativa';

                if (levelSel.value !== "") {
                    previewLevelBadge.textContent = levelSel.text;
                    previewLevelBadge.classList.remove('hidden');
                } else {
                    previewLevelBadge.classList.add('hidden');
                }

                // Handle Options based on Type
                previewOptionsContainer.innerHTML = '';
                previewEssayContainer.classList.add('hidden');
                previewOptionsContainer.classList.add('hidden');

                if (qType === 'multiple_choice') {
                    previewOptionsContainer.classList.remove('hidden');
                    optionInputs.forEach((input, index) => {
                        const optText = input.value.trim();
                        if (optText) {
                            const isCorrect = radioInputs[index].checked;
                            const letter = String.fromCharCode(65 + index);

                            // Visual style for the option
                            const optHtml = `
                                <div class="flex items-center gap-4 p-4 rounded-2xl border-2 transition-all ${isCorrect ? 'border-primary bg-primary/5' : 'border-duo-border bg-white'}">
                                    <div class="flex items-center justify-center size-8 rounded-full border-2 ${isCorrect ? 'border-primary text-primary bg-primary/10' : 'border-gray-300 text-gray-500'} font-bold flex-shrink-0">
                                        ${letter}
                                    </div>
                                    <div class="font-medium text-gray-700 whitespace-pre-wrap flex-1 leading-snug">${optText}</div>
                                    ${isCorrect ? '<span aria-hidden="true" class="material-symbols-outlined text-primary text-2xl">check_circle</span>' : ''}
                                </div>
                            `;
                            previewOptionsContainer.insertAdjacentHTML('beforeend', optHtml);
                        }
                    });
                } else if (qType === 'true_false') {
                    previewOptionsContainer.classList.remove('hidden');
                    const optionsTF = ['Verdadeiro', 'Falso'];
                    const selectedTF = document.querySelector('input[name="tf_answer"]:checked');
                    const correctIdx = selectedTF ? parseInt(selectedTF.value) : -1;
                    optionsTF.forEach((optText, index) => {
                        const letter = String.fromCharCode(65 + index);
                        const isCorrect = index === correctIdx;
                        const optHtml = `
                            <div class="flex items-center gap-4 p-4 rounded-2xl border-2 transition-all ${isCorrect ? 'border-primary bg-primary/5' : 'border-duo-border bg-white'}">
                                <div class="flex items-center justify-center size-8 rounded-full border-2 ${isCorrect ? 'border-primary text-primary bg-primary/10' : 'border-gray-300 text-gray-500'} font-bold flex-shrink-0">
                                    ${letter}
                                </div>
                                <div class="font-medium text-gray-700 flex-1">${optText}</div>
                                ${isCorrect ? '<span aria-hidden="true" class="material-symbols-outlined text-primary text-2xl">check_circle</span>' : ''}
                            </div>
                        `;
                        previewOptionsContainer.insertAdjacentHTML('beforeend', optHtml);
                    });
                } else if (qType === 'essay') {
                    previewEssayContainer.classList.remove('hidden');
                }
            }

            // Debounced listener
            function debouncedUpdate() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(updatePreview, 300);
            }

            // Attach listeners
            typeSelect.addEventListener('change', toggleSection);
            levelSelect.addEventListener('change', updatePreview);
            statementInput.addEventListener('input', debouncedUpdate);
            optionInputs.forEach(input => input.addEventListener('input', debouncedUpdate));
            radioInputs.forEach(input => input.addEventListener('change', updatePreview)); // Instant
            tfRadios.forEach(input => input.addEventListener('change', updatePreview));

            // == TOGGLE WEB/MOBILE ==
            btnWeb.addEventListener('click', function () {
                // Style buttons
                btnWeb.classList.replace('text-gray-500', 'text-gray-800');
                btnWeb.classList.replace('hover:text-gray-800', 'shadow-sm');
                btnWeb.classList.add('bg-white');

                btnMobile.classList.replace('text-gray-800', 'text-gray-500');
                btnMobile.classList.replace('shadow-sm', 'hover:text-gray-800');
                btnMobile.classList.remove('bg-white');

                // Style preview box
                previewBox.style.maxWidth = '100%';
                previewBox.style.height = 'auto';
                previewBox.classList.remove('overflow-y-auto', 'border-gray-800', 'border-[12px]');
                previewBox.classList.add('border-duo-border', 'border-2');
            });

            btnMobile.addEventListener('click', function () {
                // Style buttons
                btnMobile.classList.replace('text-gray-500', 'text-gray-800');
                btnMobile.classList.replace('hover:text-gray-800', 'shadow-sm');
                btnMobile.classList.add('bg-white');

                btnWeb.classList.replace('text-gray-800', 'text-gray-500');
                btnWeb.classList.replace('shadow-sm', 'hover:text-gray-800');
                btnWeb.classList.remove('bg-white');

                // Style preview box (Mobile size)
                previewBox.style.maxWidth = '375px'; // iPhone width approx
                previewBox.style.height = '667px';
                previewBox.classList.remove('border-duo-border', 'border-2');
                previewBox.classList.add('overflow-y-auto', 'border-gray-800', 'border-[12px]');
            });

            // Initial setup
            toggleSection();
        });
    </script>
</x-app-layout>
