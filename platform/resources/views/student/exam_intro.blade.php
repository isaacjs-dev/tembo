<x-app-layout>
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

    <div class="max-w-2xl mx-auto bg-white rounded-2xl border-2 border-duo-border p-5 md:p-8 mb-12 shadow-sm text-center">
        <div class="space-y-4 mb-8 text-left max-w-md mx-auto p-5 md:p-6 bg-gray-50 border-2 border-duo-border rounded-2xl">
            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-4">
                <span class="material-symbols-outlined text-gray-500" aria-hidden="true">format_list_numbered</span>
                <div>
                    <dt class="text-sm text-gray-600">Questões</dt>
                    <dd class="font-bold text-gray-800">{{ $exam->questions_count }}</dd>
                </div>

                <span class="material-symbols-outlined text-gray-500" aria-hidden="true">timer</span>
                <div>
                    <dt class="text-sm text-gray-600">Tempo por tentativa</dt>
                    <dd class="font-bold text-gray-800">
                        {{ !empty($exam->settings['time_limit']) ? $exam->settings['time_limit'] . ' minutos' : 'Sem limite' }}
                    </dd>
                </div>

                <span class="material-symbols-outlined text-gray-500" aria-hidden="true">replay</span>
                <div>
                    <dt class="text-sm text-gray-600">Tentativas</dt>
                    <dd class="font-bold text-gray-800">{{ $attemptsUsed }} utilizada(s) de {{ $attemptsAllowed }}</dd>
                </div>

                <span class="material-symbols-outlined text-gray-500" aria-hidden="true">devices</span>
                <div>
                    <dt class="text-sm text-gray-600">Modalidade</dt>
                    <dd class="font-bold text-gray-800">
                        {{ match ($exam->settings['application_mode'] ?? 'hybrid') {
                            'online' => '100% on-line',
                            'printed_digital' => 'Avaliação impressa + resposta digital',
                            'printed_omr' => 'Avaliação impressa + cartão OMR',
                            'offline_omr' => 'OMR offline + sincronização posterior',
                            'paper' => 'Impressa / cartão-resposta',
                            default => 'Híbrida',
                        } }}
                    </dd>
                </div>

                @if($availability['closes_at'])
                    <span class="material-symbols-outlined text-gray-500" aria-hidden="true">event</span>
                    <div>
                        <dt class="text-sm text-gray-600">Prazo geral</dt>
                        <dd class="font-bold text-gray-800">{{ $availability['closes_at']->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if(!empty($exam->settings['instructions']))
            <section class="mb-6 rounded-xl border-2 border-blue-200 bg-blue-50 p-5 text-left"
                aria-labelledby="exam-instructions-heading">
                <h2 id="exam-instructions-heading" class="font-extrabold text-blue-950">Instruções do professor</h2>
                <p class="mt-2 whitespace-pre-wrap text-blue-950">{{ $exam->settings['instructions'] }}</p>
            </section>
        @endif

        @if(!$supportsOnline)
            <div class="rounded-xl border-2 border-accent/25 bg-accent-light p-5 text-left text-secondary-dark">
                <h2 class="font-extrabold">Aplicação presencial</h2>
                <p class="mt-2">
                    Esta avaliação será respondida em papel ou cartão-resposta. Aguarde as orientações do professor;
                    não é necessário iniciar uma tentativa digital.
                </p>
            </div>
        @elseif($submission?->status === 'in_progress')
            <div class="bg-blue-50 text-blue-900 p-4 rounded-xl border-2 border-blue-200 mb-6 font-bold">
                Sua tentativa {{ $submission->attempt_number }} está em andamento. O prazo individual não reinicia ao
                sair da página.
            </div>
            <a href="{{ route('student.exam.execution', $exam) }}"
                class="duo-button-primary w-full py-4 rounded-xl font-extrabold text-base uppercase tracking-wider">
                Continuar avaliação
            </a>
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

                @if(in_array(true, $release, true))
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
                    Todas as tentativas permitidas foram utilizadas.
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
