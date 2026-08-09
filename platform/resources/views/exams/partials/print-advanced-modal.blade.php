{{-- Print Advanced Configuration Modal --}}
<x-app-modal name="print-advanced-modal" maxWidth="2xl">
    <form action="{{ route('exams.printAdvanced', $exam->id) }}" method="POST" target="_blank" x-data="{
              copies: 1,
              schoolClassId: '',
              outputType: 'both',
              individualize: false,
              shuffleQuestions: false,
              shuffleMc: false,
              shuffleTf: false,

              groupDisciplines: {{ json_encode($printSettings['group_disciplines'] ?? true) }},
              shuffleDisciplines: {{ json_encode($printSettings['shuffle_disciplines'] ?? false) }},
              showDisciplineName: {{ json_encode($printSettings['show_discipline_name'] ?? true) }},
              
              hideQuestionTerm: {{ json_encode($printSettings['hide_question_term'] ?? false) }},
              showQuestionValue: {{ json_encode($printSettings['show_question_value'] ?? true) }},
              showOptionBrackets: {{ json_encode($printSettings['show_option_brackets'] ?? false) }},
              
              questionSeparator: '{{ in_array($printSettings['question_separator'] ?? '.', ['.', ')']) ? ($printSettings['question_separator'] ?? '.') : 'custom' }}',
              customSeparator: '{{ !in_array($printSettings['question_separator'] ?? '.', ['.', ')']) ? ($printSettings['question_separator'] ?? '') : '' }}'
          }" class="flex flex-col max-h-[90vh]">
        @csrf

        {{-- ===== HEADER ===== --}}
        <div class="relative px-5 pt-6 pb-4 sm:px-6 sm:pt-7 border-b border-gray-100 flex-shrink-0">
            <button type="button" @click="show = false"
                class="absolute top-4 right-4 flex items-center justify-center h-8 w-8 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30"
                aria-label="Fechar">
                <span class="material-symbols-outlined text-[20px] leading-none">close</span>
            </button>
            <div class="flex items-center gap-3 pr-8">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100">
                    <span class="material-symbols-outlined text-blue-600 text-[22px]">print</span>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold leading-6 text-gray-900">
                        Configurar Impressão de Lote
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Defina parâmetros, agrupamento de disciplinas e formatação.
                    </p>
                </div>
            </div>
        </div>

        {{-- ===== BODY (Scrollable) ===== --}}
        <div class="px-4 py-5 sm:px-8 overflow-y-auto custom-scrollbar flex-1 relative">
            <div class="grid grid-cols-1 md:grid-cols-2 md:divide-x md:divide-gray-100 gap-y-8">

                {{-- Coluna 1: Geral e Embaralhamento Interno --}}
                <div class="space-y-6 ml-4 mr-4">

                    {{-- Turma e Cópias --}}
                    <div class="space-y-4">
                        <h4 class="font-bold text-sm text-gray-800 uppercase tracking-wider">Configurações Gerais</h4>
                        <div>
                            <label for="output_type" class="input-label block">Saída do PDF</label>
                            <select id="output_type" name="output_type" x-model="outputType" class="input-field w-full">
                                <option value="exam">Somente Avaliação</option>
                                <option value="answer_sheet">Somente cartão-resposta</option>
                                <option value="both">Avaliação + cartão-resposta</option>
                                <option value="answer_key">Somente gabarito do professor</option>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-500">O gabarito nunca é anexado ao material do aluno sem seleção explícita.</p>
                        </div>
                        <div>
                            <label class="input-label block">Turma Base (opcional)</label>
                            <select name="school_class_id" x-model="schoolClassId" class="input-field w-full" @change="
                                    let sel = $event.target.options[$event.target.selectedIndex];
                                    let count = sel.dataset.studentsCount;
                                    if(count > 0) copies = parseInt(count);
                                ">
                                <option value="">Nenhuma turma</option>
                                @foreach($exam->schoolClasses as $class)
                                    <option value="{{ $class->id }}"
                                        data-students-count="{{ $class->students()->count() }}">
                                        {{ $class->name }} ({{ $class->students()->count() }} alunos)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="input-label block">Qtd. de Cópias (Provas)</label>
                            <input type="number" name="quantity" x-model.number="copies" min="1" max="200" required
                                :readonly="individualize"
                                class="input-field w-full">
                        </div>
                        <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <input type="checkbox" name="individualize" value="1" x-model="individualize"
                                class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary">
                            <span>
                                <span class="block text-sm font-bold text-gray-800">Uma cópia identificada por aluno</span>
                                <span class="block text-[11px] text-gray-500">Usa a turma selecionada ou todo o público deduplicado da Avaliação.</span>
                            </span>
                        </label>
                    </div>

                    {{-- Embaralhamento Interno --}}
                    <div class="space-y-3 pt-4 border-t border-gray-100">
                        <h4 class="font-bold text-sm text-gray-800 uppercase tracking-wider">Embaralhamento (Interno)
                        </h4>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="shuffle_questions" x-model="shuffleQuestions" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Embaralhar ordem das Questões</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="shuffle_options_mc" x-model="shuffleMc" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Embaralhar Múltipla Escolha (A-E)</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="shuffle_options_tf" x-model="shuffleTf" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Embaralhar Verdadeiro/Falso</span>
                                <span class="text-[11px] text-gray-500 mt-0.5 leading-tight">Mantém o layout original
                                    A/B na folha.</span>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Coluna 2: Disciplinas e Formatação --}}
                <div class="space-y-6 ml-4 mr-4">

                    {{-- Agrupamento e Disciplinas --}}
                    <div class="space-y-3">
                        <h4 class="font-bold text-sm text-gray-800 uppercase tracking-wider">Agrupamento (Blocos)</h4>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="group_disciplines" x-model="groupDisciplines" value="1"
                                @change="if(!groupDisciplines) shuffleDisciplines = false"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Agrupar por Disciplinas</span>
                                <span class="text-[11px] text-gray-500 mt-0.5 leading-tight">Garante que questões de uma
                                    mesma matéria fiquem juntas.</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 transition-colors"
                            :class="groupDisciplines ? 'hover:border-gray-300' : 'opacity-60 cursor-not-allowed'">
                            <input type="checkbox" name="shuffle_disciplines" x-model="shuffleDisciplines" value="1"
                                :disabled="!groupDisciplines"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary disabled:opacity-50 h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Embaralhar blocos de disciplinas</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="show_discipline_name" x-model="showDisciplineName" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Mostrar título da disciplina no
                                    cabeçalho</span>
                            </div>
                        </label>
                    </div>

                    {{-- Formatação da Prova --}}
                    <div class="space-y-3 pt-4 border-t border-gray-100">
                        <h4 class="font-bold text-sm text-gray-800 uppercase tracking-wider">Estética da Folha</h4>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="hide_question_term" x-model="hideQuestionTerm" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Ocultar a palavra "Questão"</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="show_question_value" x-model="showQuestionValue" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Mostrar pontuação/valor</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 cursor-pointer bg-gray-50 p-3 rounded-xl border border-gray-200 hover:border-gray-300 transition-colors">
                            <input type="checkbox" name="show_option_brackets" x-model="showOptionBrackets" value="1"
                                class="mt-0.5 rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700">Mostrar ( ) vazio nas
                                    alternativas</span>
                            </div>
                        </label>

                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 mt-2">
                            <label class="text-[13px] font-bold text-gray-700 block mb-2">Separador do número da
                                questão</label>
                            <div class="flex gap-2 items-center">
                                <select name="question_separator" x-model="questionSeparator"
                                    class="input-field py-1.5 text-sm flex-1">
                                    <option value=".">Ponto (.)</option>
                                    <option value=")">Parêntese ())</option>
                                    <option value="custom">Outro...</option>
                                </select>

                                <div x-show="questionSeparator === 'custom'" x-cloak>
                                    <input type="text" name="custom_separator" x-model="customSeparator" maxlength="3"
                                        class="input-field py-1.5 w-16 text-center text-sm font-mono" placeholder="-">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== FOOTER (Sticky) ===== --}}
        <div
            class="flex flex-col sm:flex-row sm:justify-between gap-3 px-5 py-4 sm:px-6 bg-white border-t border-gray-200 rounded-b-2xl flex-shrink-0 relative z-20 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <button type="button" @click="show = false"
                class="btn-secondary w-full sm:w-auto order-2 sm:order-1 font-bold">
                Cancelar
            </button>
            <button type="submit" @click="setTimeout(() => show = false, 100)"
                class="btn-primary w-full sm:w-auto order-1 sm:order-2 px-6">
                <span class="material-symbols-outlined text-[20px] mr-1">picture_as_pdf</span>
                <span class="font-extrabold">Gerar Lote (PDF)</span>
            </button>
        </div>
    </form>
</x-app-modal>
