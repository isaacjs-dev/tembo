<x-app-layout>
    @php
        $statusLabel = match (true) {
            $availability['state'] === 'upcoming' => 'Aguardando abertura',
            $submission?->status === 'submitted' => 'Aguardando correção',
            $submission?->status === 'graded' => 'Concluída',
            $availability['state'] === 'closed' || $exam->status === 'closed' => 'Encerrada',
            $submission?->status === 'in_progress' => 'Em andamento',
            !$supportsOnline => 'Aplicação presencial',
            default => 'Disponível',
        };
        $statusClasses = match ($statusLabel) {
            'Em andamento' => 'border-blue-300 bg-blue-50 text-blue-950',
            'Concluída' => 'border-green-300 bg-green-50 text-green-950',
            'Aguardando correção' => 'border-amber-300 bg-amber-50 text-amber-950',
            default => 'border-gray-300 bg-gray-50 text-gray-800',
        };
        $formatPortalDate = fn ($date) => $date?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $canResume = $submission?->status === 'in_progress'
            && $exam->status === 'published'
            && $availability['state'] === 'open';
    @endphp
    <x-slot name="header">
        <div class="flex flex-col justify-center items-center text-center gap-3 mb-4 mt-4">
            <a href="{{ route('student.dashboard') }}"
                class="text-sm font-bold text-gray-600 hover:text-primary-dark focus-visible:ring-4 focus-visible:ring-green-100 rounded-lg">
                ← Voltar ao portal
            </a>
            <h1 class="text-3xl md:text-4xl font-extrabold text-duo-heading tracking-tight">{{ $exam->title }}</h1>
            <p class="text-gray-600 font-medium">Confira as regras antes de iniciar.</p>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto bg-white rounded-2xl border-2 border-duo-border p-5 md:p-8 mb-12 shadow-sm text-center">
        <div class="mb-8 text-left rounded-2xl border-2 border-duo-border bg-gray-50 p-4 md:p-6">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-extrabold text-gray-900">Resumo da aplicação</h2>
                <span class="self-start rounded-full border-2 px-3 py-1.5 text-sm font-extrabold {{ $statusClasses }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['Professor', $exam->author?->name ?: 'Não informado', 'person'],
                    ['Instituição', $exam->organization?->name ?: 'Professor independente', 'account_balance'],
                    ['Disciplina', $exam->discipline?->name ?: 'Não informada', 'menu_book'],
                    ['Questões', $exam->questions_count, 'format_list_numbered'],
                    ['Tempo por tentativa', !empty($exam->settings['time_limit']) ? $exam->settings['time_limit'].' minutos' : 'Sem limite', 'timer'],
                    ['Tentativas', $attemptsUsed.' utilizada(s) de '.$attemptsAllowed, 'replay'],
                    ['Modalidade', $applicationModeLabel, 'devices'],
                    ['Abertura', $formatPortalDate($availability['opens_at']) ?: 'Imediata', 'event_available'],
                    ['Prazo geral', $formatPortalDate($availability['closes_at']) ?: 'Sem prazo definido', 'event'],
                ] as [$label, $value, $icon])
                    <div class="flex min-w-0 items-start gap-3 rounded-xl border border-gray-200 bg-white p-3">
                        <span class="material-symbols-outlined text-gray-500" aria-hidden="true">{{ $icon }}</span>
                        <div class="min-w-0">
                            <dt class="text-sm text-gray-600">{{ $label }}</dt>
                            <dd class="break-words font-bold text-gray-900">{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>

        @if(!empty($exam->settings['instructions']))
            <section class="mb-6 rounded-xl border-2 border-blue-200 bg-blue-50 p-5 text-left"
                aria-labelledby="exam-instructions-heading">
                <h2 id="exam-instructions-heading" class="font-extrabold text-blue-950">Instruções do professor</h2>
                <p class="mt-2 whitespace-pre-wrap text-blue-950">{{ $exam->settings['instructions'] }}</p>
            </section>
        @endif

        @if($resultsAvailableAt && now()->isBefore($resultsAvailableAt))
            <div class="mb-6 rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-left text-blue-950">
                <p class="font-extrabold">Previsão de liberação dos resultados</p>
                <p class="mt-1">A partir de {{ $formatPortalDate($resultsAvailableAt) }}, conforme as opções definidas pelo professor.</p>
            </div>
        @endif

        @if(!$supportsOnline)
            <div class="rounded-xl border-2 border-accent/25 bg-accent-light p-5 text-left text-secondary-dark">
                <h2 class="font-extrabold">Aplicação presencial</h2>
                <p class="mt-2">
                    Esta avaliação será respondida em papel ou cartão-resposta. Aguarde as orientações do professor;
                    não é necessário iniciar uma tentativa digital.
                </p>
            </div>
        @elseif($canResume)
            <div class="bg-blue-50 text-blue-900 p-4 rounded-xl border-2 border-blue-200 mb-6 font-bold">
                Sua tentativa {{ $submission->attempt_number }} está em andamento. O prazo individual não reinicia ao
                sair da página.
            </div>
            <a href="{{ route('student.exam.execution', $exam) }}"
                class="duo-button-primary w-full py-4 rounded-xl font-extrabold text-base uppercase tracking-wider">
                Continuar avaliação
            </a>
        @elseif($submission?->status === 'in_progress')
            <div class="rounded-xl border-2 border-gray-300 bg-gray-100 p-5 text-left text-gray-800">
                <h2 class="font-extrabold">Tentativa sem acesso para continuação</h2>
                <p class="mt-2">
                    A tentativa {{ $submission->attempt_number }} permanece registrada, mas
                    @if($availability['state'] === 'upcoming')
                        a janela geral desta avaliação ainda não foi aberta.
                    @else
                        a janela geral desta avaliação está encerrada.
                    @endif
                    Procure o professor caso precise de orientação.
                </p>
            </div>
        @else
            @if($blockingRevision)
                <div class="mb-6 rounded-xl border-2 border-amber-300 bg-amber-50 p-5 text-left text-amber-950">
                    <h2 class="font-extrabold">Revisão obrigatória antes da avaliação</h2>
                    <p class="mt-2">Conclua “{{ $blockingRevision->title }}” para liberar o início desta prova.</p>
                    <a href="{{ route('student.revisions.show', $blockingRevision) }}"
                        class="duo-button-primary mt-4 inline-flex rounded-xl px-5 py-3 font-extrabold">
                        Fazer revisão agora
                    </a>
                </div>
            @endif
            @if($submission?->status === 'submitted')
                <div class="bg-amber-50 text-amber-900 p-5 rounded-xl border-2 border-amber-200 mb-6 font-bold">
                    A tentativa {{ $submission->attempt_number }} foi enviada e aguarda correção.
                </div>
            @elseif($submission?->status === 'graded')
                <div class="bg-green-50 text-green-900 p-5 rounded-xl border-2 border-green-200 mb-6 font-bold">
                    A tentativa {{ $submission->attempt_number }} foi concluída.
                </div>

                @if($resultsCanBeViewed)
                    <a href="{{ route('student.exam.results', $exam) }}"
                        class="duo-button-secondary w-full py-3 rounded-xl font-extrabold text-sm uppercase tracking-wider mb-4">
                        Ver resultados liberados
                    </a>
                @endif
            @endif

            @if($canStart)
                <form action="{{ route('student.exam.start', $exam) }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="duo-button-primary w-full py-4 rounded-xl font-extrabold text-base uppercase tracking-wider">
                        {{ $attemptsUsed > 0 ? 'Iniciar nova tentativa' : 'Começar avaliação' }}
                    </button>
                    <p class="text-sm text-gray-600 mt-3">
                        Ao iniciar, o cronômetro individual começa e continua contando mesmo se você fechar a página.
                    </p>
                </form>
            @elseif(!$blockingRevision && (!$submission || $submission->status !== 'in_progress'))
                <div class="bg-gray-100 text-gray-700 p-4 rounded-xl font-bold">
                    @if($availability['state'] === 'upcoming')
                        Esta avaliação poderá ser iniciada a partir de {{ $formatPortalDate($availability['opens_at']) }}.
                    @elseif($exam->status === 'closed' || $availability['state'] === 'closed')
                        O período para iniciar ou continuar esta avaliação foi encerrado.
                    @else
                        Todas as tentativas permitidas foram utilizadas.
                    @endif
                </div>
            @endif
        @endif


        @if($attempts->isNotEmpty())
            <section class="mt-8 border-t-2 border-duo-border pt-7 text-left" aria-labelledby="attempt-history-title">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="attempt-history-title" class="text-xl font-extrabold text-gray-900">Histórico de tentativas</h2>
                        <p class="text-sm text-gray-600">A nota só aparece quando sua liberação estiver autorizada.</p>
                    </div>
                    <p class="text-sm font-bold text-gray-700">{{ $attemptsUsed }} de {{ $attemptsAllowed }} utilizada(s)</p>
                </div>
                <ol class="space-y-3">
                    @foreach($attempts->sortByDesc('attempt_number') as $attempt)
                        @php
                            $attemptLabel = match ($attempt->status) {
                                'graded' => 'Corrigida',
                                'submitted' => 'Enviada para correção',
                                default => 'Em andamento',
                            };
                        @endphp
                        <li class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-extrabold text-gray-900">Tentativa {{ $attempt->attempt_number }} — {{ $attemptLabel }}</p>
                                    <p class="mt-1 text-sm text-gray-700">
                                        Iniciada em {{ $formatPortalDate($attempt->started_at) ?: 'horário não registrado' }}
                                        @if($attempt->finished_at)
                                            · enviada em {{ $formatPortalDate($attempt->finished_at) }}
                                        @elseif($attempt->deadline_at)
                                            · prazo individual {{ $formatPortalDate($attempt->deadline_at) }}
                                        @endif
                                    </p>
                                </div>
                                @if($attempt->status === 'graded' && $resultsCanBeViewed && $release['show_score'])
                                    <p class="shrink-0 font-extrabold text-primary-dark">
                                        Nota {{ number_format((float) $attempt->score, 1, ',', '.') }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-app-layout>
