@php
    $storedRubric = isset($question)
        ? data_get($question->content, 'rubric', [])
        : [];
    $rubricCriteria = old('rubric_criteria', $storedRubric['criteria'] ?? []);

    if (!is_array($rubricCriteria) || $rubricCriteria === []) {
        $rubricCriteria = [
            ['title' => '', 'description' => '', 'points' => ''],
        ];
    }
@endphp

<div
    id="essay_section"
    style="display: none;"
    x-data="{ criteria: @js(array_values($rubricCriteria)) }"
    class="space-y-5 p-6 bg-accent-light/50 border-2 border-accent/15 rounded-xl">
    <div>
        <h2 class="text-lg font-bold text-gray-800">Rubrica de correção</h2>
        <p class="mt-1 text-sm text-gray-500">
            Opcional. Defina critérios objetivos para orientar a correção da resposta discursiva.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="block">
            <span class="block text-sm font-bold text-gray-700 mb-2">Título da rubrica</span>
            <input
                type="text"
                name="rubric_title"
                maxlength="255"
                value="{{ old('rubric_title', $storedRubric['title'] ?? '') }}"
                class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0"
                placeholder="Ex.: Produção textual">
        </label>

        <label class="block">
            <span class="block text-sm font-bold text-gray-700 mb-2">Descrição geral</span>
            <input
                type="text"
                name="rubric_description"
                maxlength="2000"
                value="{{ old('rubric_description', $storedRubric['description'] ?? '') }}"
                class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0"
                placeholder="Como a resposta será avaliada">
        </label>
    </div>

    <div class="space-y-3">
        <template x-for="(criterion, index) in criteria" :key="index">
            <div class="p-4 bg-white border-2 border-accent/15 rounded-xl">
                <div class="grid grid-cols-1 md:grid-cols-[1fr_140px_auto] gap-3 items-start">
                    <label class="block">
                        <span class="block text-xs font-extrabold text-gray-500 uppercase tracking-wider mb-1">
                            Critério
                        </span>
                        <input
                            type="text"
                            :name="`rubric_criteria[${index}][title]`"
                            x-model="criterion.title"
                            maxlength="255"
                            class="block w-full px-3 py-2 bg-white border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0"
                            placeholder="Ex.: Clareza e coerência">
                    </label>

                    <label class="block">
                        <span class="block text-xs font-extrabold text-gray-500 uppercase tracking-wider mb-1">
                            Pontos
                        </span>
                        <input
                            type="number"
                            :name="`rubric_criteria[${index}][points]`"
                            x-model="criterion.points"
                            min="0.01"
                            max="10000"
                            step="0.25"
                            class="block w-full px-3 py-2 bg-white border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0"
                            placeholder="0">
                    </label>

                    <button
                        type="button"
                        x-show="criteria.length > 1"
                        @click="criteria.splice(index, 1)"
                        class="mt-6 p-2 text-red-500 hover:bg-red-50 rounded-lg"
                        aria-label="Remover critério">
                        <span aria-hidden="true" class="material-symbols-outlined">delete</span>
                    </button>
                </div>

                <label class="block mt-3">
                    <span class="block text-xs font-extrabold text-gray-500 uppercase tracking-wider mb-1">
                        Descrição do critério
                    </span>
                    <textarea
                        :name="`rubric_criteria[${index}][description]`"
                        x-model="criterion.description"
                        rows="2"
                        maxlength="2000"
                        class="block w-full px-3 py-2 bg-white border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0"
                        placeholder="Evidências esperadas na resposta..."></textarea>
                </label>
            </div>
        </template>
    </div>

    <button
        type="button"
        x-show="criteria.length < 10"
        @click="criteria.push({ title: '', description: '', points: '' })"
        class="inline-flex items-center gap-2 px-4 py-2 bg-white border-2 border-accent/25 text-accent rounded-xl font-bold hover:border-accent/60">
        <span aria-hidden="true" class="material-symbols-outlined text-lg">add</span>
        Adicionar critério
    </button>
</div>
