{{-- Modal de Seleção de Questões --}}
<div x-show="showPicker" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    @keydown.escape.window="showPicker = false"
    class="fixed inset-0 z-50 overflow-hidden" style="display: none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40" @click="showPicker = false" aria-hidden="true"></div>

    {{-- Modal Panel --}}
    <div class="absolute inset-0 m-auto w-[95%] max-w-6xl h-[90vh] flex flex-col bg-white rounded-2xl border-2 border-duo-border shadow-2xl overflow-hidden"
        role="dialog" aria-modal="true" aria-labelledby="question-picker-title" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between p-6 border-b-2 border-duo-border bg-gray-50 shrink-0">
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="material-symbols-outlined text-primary text-2xl">library_add</span>
                <h2 id="question-picker-title" class="text-xl font-extrabold text-duo-heading">Adicionar Questões</h2>
            </div>
            <button x-ref="pickerClose" @click="showPicker = false" class="btn-icon" type="button"
                aria-label="Fechar seletor de questões">
                <span aria-hidden="true" class="material-symbols-outlined">close</span>
            </button>
        </div>

        {{-- Tabs + Search + Filters --}}
        <div class="shrink-0 border-b-2 border-duo-border">
            {{-- Tabs --}}
            <div class="flex border-b-2 border-duo-border px-6" role="tablist"
                aria-label="Origem das questões">
                <template x-for="t in [{key:'mine', label:'Minhas', icon:'person'}, {key:'public', label:'Instituição', icon:'public'}, {key:'shared', label:'Compartilhadas', icon:'group'}]" :key="t.key">
                    <button type="button"
                        @click="pickerTab = t.key; pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                        role="tab" :aria-selected="(pickerTab === t.key).toString()"
                        :class="pickerTab === t.key ? 'border-primary text-primary' : 'border-transparent text-gray-400 hover:text-gray-600'"
                        class="flex items-center gap-2 px-4 py-3 text-sm font-extrabold border-b-2 -mb-[2px] transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]" x-text="t.icon"></span>
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </div>

            {{-- Search + Filters --}}
            <div class="p-4 space-y-3">
                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <span aria-hidden="true" class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <label for="question-picker-search" class="sr-only">Buscar por enunciado</label>
                        <input id="question-picker-search" type="text" x-model="pickerSearch" @input.debounce.400ms="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            placeholder="Buscar por enunciado..."
                            class="w-full pl-10 pr-4 py-2.5 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:outline-none focus:border-primary transition-colors">
                    </div>
                    <button type="button" @click="showPickerFilters = !showPickerFilters"
                        :aria-expanded="showPickerFilters.toString()"
                        :class="showPickerFilters ? 'border-primary text-primary bg-primary/5' : 'border-duo-border text-gray-500'"
                        class="px-4 py-2.5 border-2 rounded-xl text-sm font-bold flex items-center gap-1 transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">tune</span>
                        Filtros
                    </button>
                </div>

                {{-- Collapsible Filters --}}
                <div x-show="showPickerFilters" x-collapse class="space-y-3">
                    {{-- Row 1: Tipo | Dificuldade | Etapa | Ano/Série --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <select x-model="pickerFilters.type" @change="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="">Tipo</option>
                            <option value="multiple_choice">Múltipla Escolha</option>
                            <option value="true_false">Verdadeiro/Falso</option>
                            <option value="essay">Dissertativa</option>
                        </select>

                        <select x-model="pickerFilters.level" @change="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="">Dificuldade</option>
                            <option value="very_easy">Muito Fácil</option>
                            <option value="easy">Fácil</option>
                            <option value="medium">Médio</option>
                            <option value="hard">Difícil</option>
                            <option value="very_hard">Muito Difícil</option>
                        </select>

                        <select x-model="pickerFilters.stage" @change="pickerFilters.grade = ''; pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="">Etapa</option>
                            <option value="ef_iniciais">Fundamental I</option>
                            <option value="ef_finais">Fundamental II</option>
                            <option value="em">Ensino Médio</option>
                        </select>

                        <select x-model="pickerFilters.grade" @change="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            :disabled="!pickerFilters.stage"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none disabled:opacity-50">
                            <option value="">Ano/Série</option>
                            <template x-if="pickerFilters.stage === 'ef_iniciais' || pickerFilters.stage === 'ef_finais'">
                                <template x-for="y in [1,2,3,4,5,6,7,8,9]" :key="'g'+y">
                                    <option :value="y" x-text="y + 'º Ano'"></option>
                                </template>
                            </template>
                            <template x-if="pickerFilters.stage === 'em'">
                                <template x-for="y in [1,2,3]" :key="'s'+y">
                                    <option :value="y" x-text="y + 'ª Série'"></option>
                                </template>
                            </template>
                        </select>
                    </div>

                    {{-- Row 2: Área | Disciplina | Ordenação --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <select x-model="pickerFilters.knowledge_area_id" @change="pickerFilters.discipline_id = ''; pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="">Área de Conhecimento</option>
                            @foreach($knowledgeAreas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>

                        <select x-model="pickerFilters.discipline_id" @change="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="">Disciplina</option>
                            @foreach($disciplines as $disc)
                                <option value="{{ $disc->id }}" data-area="{{ $disc->knowledge_area_id }}"
                                    x-show="!pickerFilters.knowledge_area_id || '{{ $disc->knowledge_area_id }}' == pickerFilters.knowledge_area_id">
                                    {{ $disc->name }}
                                </option>
                            @endforeach
                        </select>

                        <select x-model="pickerFilters.sort" @change="pickerPage = 1; pickerResults = []; fetchPickerQuestions()"
                            class="px-3 py-2 bg-background-light border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:outline-none">
                            <option value="newest">Mais Recentes</option>
                            <option value="oldest">Mais Antigas</option>
                            <option value="level_asc">Dificuldade (Fácil → Difícil)</option>
                            <option value="level_desc">Dificuldade (Difícil → Fácil)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Results List --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="picker-results-container">
            {{-- Loading --}}
            <div x-show="pickerLoading && pickerResults.length === 0" class="flex items-center justify-center py-12">
                <div class="loading-state">
                    <span aria-hidden="true" class="loading-spinner size-5"></span>
                    <span class="font-bold text-sm">Carregando questões...</span>
                </div>
            </div>

            {{-- Empty --}}
            <div x-show="!pickerLoading && pickerResults.length === 0" class="text-center py-12">
                <span class="material-symbols-outlined text-4xl text-gray-300 mb-2 block">search_off</span>
                <p class="text-gray-500 font-bold text-sm">Nenhuma questão encontrada com os filtros aplicados.</p>
            </div>

            {{-- Question Cards --}}
            <template x-for="q in pickerResults" :key="q.id">
                <div :class="q.in_exam ? 'opacity-50 pointer-events-none' : ''"
                    class="border-2 border-duo-border rounded-xl overflow-hidden transition-all"
                    :data-id="q.id">

                    <div class="flex items-start gap-3 p-4 cursor-pointer" @click="if (!q.in_exam) togglePickerExpand(q.id)">
                        {{-- Checkbox --}}
                        <div class="pt-0.5 shrink-0" @click.stop>
                            <input type="checkbox"
                                :checked="pickerSelected.includes(q.id)"
                                @change="togglePickerSelect(q.id)"
                                :disabled="q.in_exam"
                                class="size-5 text-primary border-gray-300 rounded focus:ring-primary">
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="badge badge-primary" x-text="q.type === 'multiple_choice' ? 'Múltipla Escolha' : (q.type === 'true_false' ? 'V/F' : 'Dissertativa')"></span>
                                <span x-show="q.level" class="badge badge-neutral" x-text="
                                    q.level === 'very_easy' ? 'Muito Fácil' :
                                    q.level === 'easy' ? 'Fácil' :
                                    q.level === 'medium' ? 'Médio' :
                                    q.level === 'hard' ? 'Difícil' : 'Muito Difícil'
                                "></span>
                                <span x-show="q.discipline" class="badge badge-info" x-text="q.discipline"></span>
                                <span x-show="q.in_exam" class="badge badge-success flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px]">check</span> Já na prova
                                </span>
                            </div>
                            <p class="text-sm font-medium text-gray-800 leading-relaxed"
                                :class="pickerExpanded !== q.id ? 'line-clamp-2' : ''"
                                x-text="q.content?.statement || ''"></p>

                            {{-- Owner info --}}
                            <div x-show="pickerTab !== 'mine'" class="mt-1">
                                <span class="text-xs text-gray-400 font-medium" x-text="'Por: ' + (q.owner_name || '')"></span>
                            </div>
                        </div>

                        {{-- Expand icon --}}
                        <span class="material-symbols-outlined text-gray-400 shrink-0 transition-transform"
                            :class="pickerExpanded === q.id ? 'rotate-180' : ''">expand_more</span>
                    </div>

                    {{-- Expanded Preview --}}
                    <div x-show="pickerExpanded === q.id" x-collapse class="border-t-2 border-duo-border bg-gray-50 p-4">
                        {{-- Multiple Choice Options --}}
                        <template x-if="q.type === 'multiple_choice' && q.content?.options">
                            <div class="space-y-2">
                                <template x-for="(opt, idx) in q.content.options" :key="idx">
                                    <div class="flex items-center gap-3 p-3 rounded-xl border-2 text-sm"
                                        :class="idx == q.content.correct_option ? 'border-primary bg-primary/5' : 'border-duo-border bg-white'">
                                        <div class="size-7 rounded-full border-2 flex items-center justify-center font-bold text-xs shrink-0"
                                            :class="idx == q.content.correct_option ? 'border-primary text-primary bg-primary/10' : 'border-gray-300 text-gray-400'"
                                            x-text="String.fromCharCode(65 + idx)"></div>
                                        <span class="font-medium text-gray-700" x-text="opt"></span>
                                        <span x-show="idx == q.content.correct_option" class="material-symbols-outlined text-primary text-lg ml-auto">check_circle</span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- True/False --}}
                        <template x-if="q.type === 'true_false'">
                            <div class="flex gap-3">
                                <div class="flex-1 p-3 rounded-xl border-2 text-center"
                                    :class="q.content?.correct_option == 0 ? 'border-primary bg-primary/5' : 'border-duo-border bg-white'">
                                    <span class="material-symbols-outlined text-green-500 text-2xl">check_circle</span>
                                    <p class="font-bold text-sm mt-1">Verdadeiro</p>
                                </div>
                                <div class="flex-1 p-3 rounded-xl border-2 text-center"
                                    :class="q.content?.correct_option == 1 ? 'border-primary bg-primary/5' : 'border-duo-border bg-white'">
                                    <span class="material-symbols-outlined text-red-500 text-2xl">cancel</span>
                                    <p class="font-bold text-sm mt-1">Falso</p>
                                </div>
                            </div>
                        </template>

                        {{-- Essay --}}
                        <template x-if="q.type === 'essay'">
                            <div class="p-3 bg-white border-2 border-duo-border rounded-xl text-sm text-gray-400 italic">
                                Questão dissertativa — o aluno digitará a resposta.
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Load More --}}
            <div x-show="pickerHasMore && pickerResults.length > 0" class="text-center pt-2">
                <button type="button" @click="fetchPickerQuestions(true)"
                    :disabled="pickerLoading"
                    class="btn-ghost btn-sm border-2 border-duo-border px-6">
                    <span x-show="!pickerLoading">Carregar mais</span>
                    <span x-show="pickerLoading">Carregando...</span>
                </button>
            </div>
        </div>

        {{-- Footer --}}
        <div class="shrink-0 border-t-2 border-duo-border bg-gray-50 p-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-sm font-bold text-gray-600">
                    <span x-text="pickerSelected.length" class="text-primary font-extrabold"></span> selecionada(s)
                </span>
            </div>

            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-bold text-gray-600">
                    Pontos:
                    <input type="number" x-model.number="pickerPoints" step="0.5" min="0" value="1"
                        class="w-20 px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-bold text-center focus:border-primary focus:outline-none">
                </label>

                <button type="button" @click="addPickerSelected()"
                    :disabled="pickerSelected.length === 0 || pickerAddingLoading"
                    class="btn-primary btn-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Adicionar Selecionadas
                </button>
            </div>
        </div>
    </div>
</div>
