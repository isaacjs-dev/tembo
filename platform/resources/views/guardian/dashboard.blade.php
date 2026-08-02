<x-app-layout>
    <x-slot name="header">
        <div class="mb-8">
            <p class="mb-2 text-sm font-bold text-primary">Área da família</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-duo-heading md:text-4xl">
                Acompanhamento dos estudantes
            </h1>
            <p class="mt-2 max-w-2xl text-gray-500">
                Consulte entregas e resultados liberados pela escola. Gabaritos e comentários restritos
                permanecem privados.
            </p>
        </div>
    </x-slot>

    <section aria-labelledby="students-heading">
        <h2 id="students-heading" class="sr-only">Estudantes vinculados</h2>

        @if ($students->isEmpty())
            <div class="rounded-2xl border-2 border-dashed border-duo-border bg-white p-10 text-center">
                <span class="material-symbols-outlined mb-3 text-5xl text-gray-300" aria-hidden="true">
                    family_restroom
                </span>
                <h2 class="text-xl font-extrabold text-duo-heading">Nenhum estudante vinculado</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm text-gray-500">
                    Solicite à administração da instituição que confirme seu vínculo de responsável.
                </p>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($students as $student)
                    @php($latest = $latestSubmissions->get($student->id))
                    <article class="flex flex-col rounded-2xl border-2 border-duo-border bg-white p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                                <span class="material-symbols-outlined" aria-hidden="true">school</span>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-extrabold text-duo-heading">{{ $student->name }}</h3>
                                <p class="text-xs font-semibold text-gray-500">
                                    {{ $student->pivot->relationship }}
                                    @if ($student->schoolClasses->isNotEmpty())
                                        · {{ $student->schoolClasses->pluck('name')->join(', ') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex-1 rounded-xl bg-background-light p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Entrega mais recente</p>
                            @if ($latest)
                                <p class="mt-2 font-bold text-duo-heading">{{ $latest['exam']->title }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if ($latest['score'] !== null)
                                        Nota liberada: <strong>{{ number_format($latest['score'], 1, ',', '.') }}</strong>
                                    @elseif ($latest['results_available_at'])
                                        Resultados previstos para
                                        {{ $latest['results_available_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @else
                                        Resultado ainda não liberado pelo professor
                                    @endif
                                </p>
                            @else
                                <p class="mt-2 text-sm text-gray-500">Nenhuma entrega registrada.</p>
                            @endif
                        </div>

                        <a href="{{ route('guardian.students.show', $student) }}"
                            class="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-extrabold text-white transition hover:bg-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            Ver acompanhamento
                            <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
