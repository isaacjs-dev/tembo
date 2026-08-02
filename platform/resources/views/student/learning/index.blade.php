<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-bold text-primary-dark">Seu espaço de aprendizagem</p>
            <h1 class="text-3xl font-black text-duo-heading">Estudar e revisar</h1>
            <p class="mt-1 max-w-3xl text-gray-700">
                Materiais das suas turmas, priorizados pelos assuntos em que uma revisão pode ajudar mais.
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-7">
        <section class="overflow-hidden rounded-2xl border-2 border-green-200 bg-gradient-to-br from-green-50 to-blue-50 p-5 md:p-7"
            aria-labelledby="learning-journey-title">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-xl">
                    <p class="text-sm font-extrabold uppercase tracking-wide text-primary-dark">Sua jornada</p>
                    <h2 id="learning-journey-title" class="mt-1 text-2xl font-black text-gray-900">
                        Cada revisão é um passo adiante
                    </h2>
                    <p class="mt-2 text-gray-700">
                        Este painel reconhece seu histórico de prática. Ele não reduz pontos por erros: dificuldades
                        servem apenas para organizar o próximo estudo.
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-2 sm:gap-4">
                    <div class="rounded-xl border border-white bg-white/90 p-3 text-center shadow-sm">
                        <dt class="text-xs font-bold text-gray-600">Revisões abertas</dt>
                        <dd class="mt-1 text-2xl font-black text-primary-dark">{{ $learningProgress['materials_opened'] }}</dd>
                    </div>
                    <div class="rounded-xl border border-white bg-white/90 p-3 text-center shadow-sm">
                        <dt class="text-xs font-bold text-gray-600">Revisões concluídas</dt>
                        <dd class="mt-1 text-2xl font-black text-primary-dark">{{ $learningProgress['materials_completed'] }}</dd>
                    </div>
                    <div class="rounded-xl border border-white bg-white/90 p-3 text-center shadow-sm">
                        <dt class="text-xs font-bold text-gray-600">Revisões em destaque</dt>
                        <dd class="mt-1 text-2xl font-black text-primary-dark">{{ $learningProgress['recommended_now'] }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <form method="GET" class="grid gap-4 rounded-2xl border-2 border-duo-border bg-white p-4 md:grid-cols-[1fr_16rem_auto]"
            aria-label="Filtrar materiais de estudo">
            <div>
                <label for="learning-search" class="mb-1 block text-sm font-bold text-gray-800">Buscar material</label>
                <input id="learning-search" name="search" value="{{ request('search') }}" maxlength="120"
                    placeholder="Ex.: frações, leitura, ecologia"
                    class="w-full rounded-xl border-2 border-gray-300">
            </div>
            <div>
                <label for="learning-discipline" class="mb-1 block text-sm font-bold text-gray-800">Disciplina</label>
                <select id="learning-discipline" name="discipline_id" class="input-field">
                    <option value="">Todas</option>
                    @foreach($disciplines as $discipline)
                        <option value="{{ $discipline->id }}" @selected((string) request('discipline_id') === (string) $discipline->id)>
                            {{ $discipline->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="duo-button-secondary self-end rounded-xl px-5 py-3 font-extrabold">Filtrar</button>
        </form>

        <section aria-labelledby="available-materials-title">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="available-materials-title" class="text-2xl font-black text-gray-900">Materiais para você</h2>
                    <p class="text-sm text-gray-700">A razão de cada recomendação aparece no próprio cartão.</p>
                </div>
                <p class="text-sm font-bold text-gray-600">{{ $materials->total() }} material(is)</p>
            </div>

            @if($materials->isEmpty())
                <div class="rounded-2xl border-2 border-dashed border-gray-300 bg-white p-10 text-center">
                    <span class="material-symbols-outlined text-5xl text-gray-500" aria-hidden="true">auto_stories</span>
                    <h3 class="mt-3 text-xl font-extrabold text-gray-900">Nenhum material disponível</h3>
                    <p class="mt-1 text-gray-700">
                        Ainda não há materiais publicados para suas turmas ou os filtros não encontraram resultados.
                    </p>
                </div>
            @else
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($materials as $material)
                        @php($materialProgress = $material->progressRecords->first())
                        <article class="flex flex-col rounded-2xl border-2 {{ $material->recommendation_score > 0 ? 'border-green-300 bg-green-50/40' : 'border-duo-border bg-white' }} p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-extrabold text-blue-950">
                                    {{ $material->discipline?->name ?: 'Conteúdo geral' }}
                                </span>
                                @if($material->recommendation_score > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-extrabold text-primary-dark">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">recommend</span>
                                        Prioridade sugerida
                                    </span>
                                @endif
                            </div>
                            @if($materialProgress?->status === 'completed')
                                <p class="mt-3 inline-flex w-fit items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-xs font-extrabold text-green-900">
                                    <span class="material-symbols-outlined text-base" aria-hidden="true">task_alt</span>
                                    Revisão concluída
                                </p>
                            @elseif($materialProgress)
                                <p class="mt-3 inline-flex w-fit items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-extrabold text-blue-950">
                                    <span class="material-symbols-outlined text-base" aria-hidden="true">progress_activity</span>
                                    Em andamento
                                </p>
                            @endif
                            <h3 class="mt-4 text-xl font-extrabold text-gray-900">{{ $material->title }}</h3>
                            <p class="mt-2 line-clamp-3 text-sm text-gray-700">
                                {{ $material->description ?: 'Abra o material para iniciar esta revisão.' }}
                            </p>
                            <p class="mt-4 rounded-xl bg-white p-3 text-sm font-medium text-gray-800">
                                <span class="font-extrabold">Por que aparece aqui:</span>
                                {{ $material->recommendation_reason }}
                            </p>
                            <a href="{{ route('student.learning.show', $material) }}"
                                class="duo-button-primary mt-5 rounded-xl px-5 py-3 text-center font-extrabold">
                                Abrir revisão
                                <span class="sr-only">: {{ $material->title }}</span>
                            </a>
                        </article>
                    @endforeach
                </div>
                <div class="mt-6">{{ $materials->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
