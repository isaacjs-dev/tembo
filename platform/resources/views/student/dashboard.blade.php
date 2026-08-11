<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5 mb-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold text-duo-heading tracking-tight">Portal do Aluno</h1>
                <p class="text-gray-600 font-medium mt-1">
                    Bem-vindo, {{ auth()->user()->name }}. Acompanhe suas avaliações e tentativas.
                </p>
            </div>

            <a href="{{ route('student.pedagogical.index') }}"
                class="duo-button-secondary rounded-xl px-5 py-3 text-center text-sm font-extrabold">
                Aulas e atividades
            </a>

            <form action="{{ route('student.joinByCode') }}" method="POST"
                class="flex flex-col sm:flex-row sm:items-end gap-2 w-full lg:w-auto">
                @csrf
                <div class="flex-1 lg:flex-none">
                    <label for="access_code" class="block text-sm font-bold text-gray-700 mb-1">
                        Código da avaliação
                    </label>
                    <input id="access_code" type="text" name="access_code" value="{{ old('access_code') }}"
                        placeholder="Ex.: K4R9T2" required maxlength="10" autocomplete="off"
                        aria-describedby="@error('access_code') access-code-error @enderror"
                        class="block w-full lg:w-48 px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-gray-800 uppercase focus:outline-none focus:border-secondary focus:ring-4 focus:ring-blue-100 transition-all font-bold tracking-widest">
                    @error('access_code')
                        <p id="access-code-error" class="text-sm font-semibold text-red-700 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="duo-button-secondary px-6 py-3 justify-center rounded-xl font-extrabold text-sm uppercase tracking-wider flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">login</span>
                    Entrar
                </button>
            </form>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-12">
        @forelse($availableExams as $exam)
            @php
                $submission = $submissions[$exam->id] ?? null;
                $gradedSubmission = $gradedSubmissions[$exam->id] ?? null;
                $meta = $portalMeta[$exam->id];
                $releaseAvailable = $meta['results_can_be_viewed'];
                $availability = $meta['availability'];
                $applicationModeLabel = $meta['application_mode_label'];
                $statusLabel = match (true) {
                    $availability['state'] === 'upcoming' => 'Aguardando abertura',
                    $submission?->status === 'graded' => 'Concluída',
                    $submission?->status === 'submitted' => 'Em correção',
                    $availability['state'] === 'closed' || $exam->status === 'closed' => 'Encerrada',
                    $submission?->status === 'in_progress' => 'Em andamento',
                    !$meta['supports_online'] => 'Aplicação presencial',
                    default => 'Disponível',
                };
                $statusClasses = match ($statusLabel) {
                    'Concluída' => 'bg-green-100 text-green-800',
                    'Em correção' => 'bg-amber-100 text-amber-900',
                    'Em andamento' => 'bg-blue-100 text-blue-800',
                    default => 'bg-gray-100 text-gray-700',
                };
                $formatPortalDate = fn ($date) => $date?->timezone(config('app.timezone'))->format('d/m/Y H:i');
                $deadlineExpired = $submission?->status === 'in_progress'
                    && $submission->deadline_at
                    && now()->greaterThanOrEqualTo($submission->deadline_at);
            @endphp

            <article class="bg-white rounded-2xl border-2 border-duo-border p-6 flex flex-col h-full shadow-sm">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <h2 class="text-xl font-extrabold text-duo-heading leading-tight">{{ $exam->title }}</h2>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-extrabold uppercase {{ $statusClasses }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-3 text-sm text-gray-600 sm:grid-cols-2 mb-6">
                    <div>
                        <dt class="font-medium">Professor</dt>
                        <dd class="font-extrabold text-gray-800">{{ $exam->author?->name ?: 'Não informado' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Instituição</dt>
                        <dd class="font-extrabold text-gray-800">{{ $exam->organization?->name ?: 'Professor independente' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium">Disciplina</dt>
                        <dd class="font-extrabold text-gray-800">{{ $exam->discipline?->name ?: 'Não informada' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Questões</dt>
                        <dd class="font-extrabold text-gray-800">{{ $exam->questions_count }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Tempo</dt>
                        <dd class="font-extrabold text-gray-800">
                            {{ !empty($exam->settings['time_limit']) ? $exam->settings['time_limit'] . ' min' : 'Livre' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Modalidade</dt>
                        <dd class="font-extrabold text-gray-800">{{ $applicationModeLabel }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium">Tentativas utilizadas</dt>
                        <dd class="font-extrabold text-gray-800">
                            {{ $meta['attempts_used'] }} de {{ $meta['attempts_allowed'] }}
                            · {{ $meta['attempts_remaining'] }} restante(s)
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">Abertura</dt>
                        <dd class="font-extrabold text-gray-800">{{ $formatPortalDate($availability['opens_at']) ?: 'Imediata' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">Prazo geral</dt>
                        <dd class="font-extrabold text-gray-800">{{ $formatPortalDate($availability['closes_at']) ?: 'Sem prazo' }}</dd>
                    </div>
                </dl>

                <div class="mt-auto pt-4 border-t-2 border-duo-border">
                    @if($submission?->status === 'graded' && $releaseAvailable)
                        <a href="{{ route('student.exam.results', $exam) }}"
                            class="w-full text-center bg-green-100 text-green-800 hover:bg-green-200 py-3 rounded-lg font-bold text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                            Ver resultados
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                        </a>
                    @elseif($submission?->status === 'graded' && $meta['results_available_at'] && now()->isBefore($meta['results_available_at']))
                        <div class="w-full rounded-lg bg-blue-50 p-3 text-center text-sm font-bold text-blue-900">
                            Resultados em {{ $formatPortalDate($meta['results_available_at']) }}
                        </div>
                    @elseif($submission?->status === 'submitted')
                        <div class="w-full text-center bg-amber-50 text-amber-900 border border-amber-200 p-3 rounded-lg font-bold text-sm">
                            Respostas enviadas; aguardando correção
                        </div>
                    @elseif($availability['state'] === 'upcoming')
                        <div class="w-full text-center bg-gray-100 text-gray-700 p-3 rounded-lg font-bold text-sm">
                            Disponível em {{ $formatPortalDate($availability['opens_at']) }}
                        </div>
                    @elseif($exam->status === 'closed' || $availability['state'] === 'closed')
                        <div class="w-full text-center bg-gray-100 text-gray-700 p-3 rounded-lg font-bold text-sm">
                            Prazo encerrado
                        </div>
                    @elseif($submission?->status === 'in_progress')
                        <a href="{{ route('student.exam.execution', $exam) }}"
                            class="w-full text-center duo-button-secondary py-3 rounded-lg font-bold text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                                {{ $deadlineExpired ? 'schedule' : 'play_arrow' }}
                            </span>
                            {{ $deadlineExpired ? 'Finalizar tentativa' : 'Continuar avaliação' }}
                        </a>
                    @elseif(!$meta['supports_online'])
                        <a href="{{ route('student.exam.show', $exam) }}"
                            class="w-full text-center duo-button-secondary py-3 rounded-lg font-bold text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">print</span>
                            Ver instruções da aplicação
                        </a>
                    @elseif($meta['attempts_remaining'] > 0)
                        <a href="{{ route('student.exam.show', $exam) }}"
                            class="w-full text-center duo-button-secondary py-3 rounded-lg font-bold text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                            {{ $submission ? 'Nova tentativa' : 'Ver instruções' }}
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span>
                        </a>
                    @else
                        <div class="w-full text-center bg-gray-100 text-gray-700 p-3 rounded-lg font-bold text-sm">
                            Avaliação concluída
                        </div>
                    @endif

                    @if($gradedSubmission && $submission?->id !== $gradedSubmission->id && $releaseAvailable)
                        <a href="{{ route('student.exam.results', $exam) }}"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-green-700 bg-white py-2.5 text-center text-sm font-extrabold text-green-800 hover:bg-green-50">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">history</span>
                            Ver resultado da tentativa {{ $gradedSubmission->attempt_number }}
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full bg-white rounded-2xl border-2 border-duo-border p-8 md:p-12 text-center shadow-sm">
                <span class="material-symbols-outlined text-6xl text-blue-400 mb-4 block" aria-hidden="true">event_available</span>
                <h2 class="text-2xl font-bold text-gray-800">Nenhuma avaliação disponível</h2>
                <p class="text-gray-600 mt-2 max-w-lg mx-auto">
                    Quando uma atividade for liberada para sua turma, ela aparecerá aqui. Você também pode usar um
                    código fornecido pelo professor.
                </p>
            </div>
        @endforelse
    </div>

    @if($availableExams->hasPages())
        <nav class="mt-6" aria-label="Paginação das avaliações">
            {{ $availableExams->links() }}
        </nav>
    @endif
</x-app-layout>
