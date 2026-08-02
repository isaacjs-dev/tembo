<x-app-layout>
    <x-slot name="header">
        <div class="mb-8">
            <a href="{{ route('guardian.dashboard') }}"
                class="mb-3 inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
                Todos os estudantes
            </a>
            <h1 class="text-3xl font-extrabold tracking-tight text-duo-heading md:text-4xl">
                {{ $student->name }}
            </h1>
            <p class="mt-2 text-gray-500">
                @if ($student->schoolClasses->isNotEmpty())
                    Turmas: {{ $student->schoolClasses->pluck('name')->join(', ') }}
                @else
                    O estudante ainda não está matriculado em uma turma.
                @endif
            </p>
        </div>
    </x-slot>

    <section aria-labelledby="history-heading">
        <div class="mb-4">
            <h2 id="history-heading" class="text-xl font-extrabold text-duo-heading">Histórico de avaliações</h2>
            <p class="mt-1 text-sm text-gray-500">
                Somente informações cuja divulgação foi autorizada pelo professor são exibidas.
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border-2 border-duo-border bg-white">
            @forelse ($submissions as $item)
                <article class="border-b-2 border-duo-border p-5 last:border-b-0 md:p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="font-extrabold text-duo-heading">{{ $item['exam']->title }}</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Tentativa {{ $item['attempt_number'] }}
                                @if ($item['finished_at'])
                                    · enviada em
                                    {{ $item['finished_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </div>

                        @if ($item['score'] !== null)
                            <div class="rounded-xl bg-primary/10 px-4 py-2 text-center text-primary">
                                <span class="block text-xs font-bold uppercase tracking-wider">Nota</span>
                                <strong class="text-xl">{{ number_format($item['score'], 1, ',', '.') }}</strong>
                            </div>
                        @else
                            <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-600">
                                Resultado restrito
                            </span>
                        @endif
                    </div>

                    @if ($item['feedback'])
                        <div class="mt-4 rounded-xl border border-secondary/20 bg-secondary/5 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-secondary">Comentário do professor</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $item['feedback'] }}</p>
                        </div>
                    @elseif ($item['results_available_at'] && !$item['results_released'])
                        <p class="mt-4 text-sm text-gray-500">
                            Liberação programada para
                            {{ $item['results_available_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                        </p>
                    @endif
                </article>
            @empty
                <div class="p-10 text-center">
                    <span class="material-symbols-outlined mb-3 text-5xl text-gray-300" aria-hidden="true">
                        assignment
                    </span>
                    <p class="font-bold text-duo-heading">Nenhuma avaliação concluída até o momento.</p>
                </div>
            @endforelse
        </div>
    </section>
</x-app-layout>
