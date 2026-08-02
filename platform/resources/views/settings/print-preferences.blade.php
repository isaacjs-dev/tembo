<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Impressão</span>
                </nav>
                <h1 class="page-title">Padrões de Impressão</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">Configure suas preferências padrão de formatação e embaralhamento do Lote de Provas.</p>
            </div>
        </div>
    </x-slot>

    <div class="card p-8 mb-12 max-w-3xl" x-data="{
        groupDisciplines: {{ json_encode($effectiveSettings['group_disciplines']) }},
        questionSeparator: '{{ in_array($effectiveSettings['question_separator'], ['.', ')']) ? $effectiveSettings['question_separator'] : 'custom' }}',
        customSeparator: '{{ !in_array($effectiveSettings['question_separator'], ['.', ')']) ? $effectiveSettings['question_separator'] : '' }}'
    }">

        @if (session('status'))
            <div class="alert alert-success mb-6" role="status" aria-live="polite">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('settings.print.update') }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Agrupamento e Disciplinas</h3>
                
                <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                    <input type="checkbox" name="group_disciplines" x-model="groupDisciplines" value="1" 
                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-5 w-5">
                    <span class="text-sm font-medium text-gray-700">Disciplinas agrupadas (manter questões da mesma disciplina juntas)</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 transition-colors"
                       :class="groupDisciplines ? 'hover:border-gray-300' : 'opacity-60 cursor-not-allowed'">
                    <input type="checkbox" name="shuffle_disciplines" value="1" :disabled="!groupDisciplines"
                        {{ $effectiveSettings['shuffle_disciplines'] ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary disabled:opacity-50 h-5 w-5">
                    <span class="text-sm font-medium text-gray-700">Embaralhar ordem das disciplinas (Requer Agrupamento)</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                    <input type="checkbox" name="show_discipline_name" value="1" 
                        {{ $effectiveSettings['show_discipline_name'] ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-5 w-5">
                    <span class="text-sm font-medium text-gray-700">Mostrar nome da disciplina no cabeçalho</span>
                </label>
            </div>

            <div class="space-y-4 pt-4">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Formatação da Prova</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                        <input type="checkbox" name="hide_question_term" value="1" 
                            {{ $effectiveSettings['hide_question_term'] ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-5 w-5">
                        <span class="text-sm font-medium text-gray-700">Ocultar a palavra "Questão" (ex: 67. enunciado)</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                        <input type="checkbox" name="show_question_value" value="1" 
                            {{ $effectiveSettings['show_question_value'] ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-5 w-5">
                        <span class="text-sm font-medium text-gray-700">Mostrar valor da questão</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors md:col-span-2">
                        <input type="checkbox" name="show_option_brackets" value="1" 
                            {{ $effectiveSettings['show_option_brackets'] ? 'checked' : '' }}
                            class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-5 w-5">
                        <span class="text-sm font-medium text-gray-700">Mostrar campo de marcação ( ) nas alternativas</span>
                    </label>
                </div>
                
                <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                    <label class="text-sm font-bold text-gray-700 block mb-3">Separador do número da questão</label>
                    <div class="flex gap-4 items-center">
                        <select x-model="questionSeparator" class="input-field max-w-[200px]" @change="if(questionSeparator !== 'custom') $refs.actualInput.value = questionSeparator">
                            <option value=".">Ponto (.) -&gt; 67.</option>
                            <option value=")">Parêntese ()) -&gt; 67)</option>
                            <option value="custom">Personalizado...</option>
                        </select>
                        
                        <div x-show="questionSeparator === 'custom'" x-cloak>
                            <input type="text" x-model="customSeparator" maxlength="3" class="input-field w-32 text-center" placeholder="Ex: -"
                                @input="$refs.actualInput.value = customSeparator">
                        </div>
                        
                        <!-- Hidden input para envio do formulário consolidado -->
                        <input type="hidden" name="question_separator" x-ref="actualInput" value="{{ $effectiveSettings['question_separator'] }}">
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200 flex justify-end gap-3">
                <button type="submit" class="btn-primary">
                    Salvar Preferências
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
