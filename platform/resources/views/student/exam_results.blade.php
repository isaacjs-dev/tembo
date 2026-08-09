<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-600 mb-2"
                    aria-label="Navegação estrutural">
                    <a class="hover:text-primary-dark focus-visible:ring-4 focus-visible:ring-green-100 rounded"
                        href="{{ route('student.dashboard') }}">Início</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">Resultados</span>
                </nav>
                <p class="text-sm font-bold text-gray-600">Tentativa nº {{ $submission->attempt_number }}</p>
                <h1 class="text-3xl font-extrabold text-duo-heading tracking-tight">
                    {{ $exam->title }}
                </h1>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                @if($release['show_score'])
                    @php
                        $percentage = $totalPoints > 0 ? round(((float) $submission->score / $totalPoints) * 100) : 0;
                    @endphp
                    <div class="px-5 py-3 bg-white border-2 border-duo-border rounded-xl shadow-sm">
                        <p class="text-sm font-bold text-gray-600">Sua nota</p>
                        <p class="text-2xl font-extrabold text-primary-dark">
                            {{ number_format((float) $submission->score, 1, ',', '.') }}
                            <span class="text-base text-gray-700">/ {{ number_format($totalPoints, 1, ',', '.') }}</span>
                        </p>
                        <p class="text-sm font-bold text-gray-700">{{ $percentage }}% dos pontos</p>
                    </div>
                @endif
                <a href="{{ route('student.dashboard') }}"
                    class="duo-button-secondary px-6 py-3 rounded-xl font-extrabold text-sm uppercase tracking-wider text-center">
                    Voltar ao portal
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-8 mb-24">
        <section class="rounded-2xl border-2 border-duo-border bg-white p-5 shadow-sm md:p-6"
            aria-labelledby="result-context-title">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="result-context-title" class="text-xl font-extrabold text-gray-900">Resumo da avaliação</h2>
                <span class="self-start rounded-full bg-green-100 px-3 py-1.5 text-sm font-extrabold text-green-900">
                    Tentativa corrigida
                </span>
            </div>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['Professor', $exam->author?->name ?: 'Não informado'],
                    ['Instituição', $exam->organization?->name ?: 'Professor independente'],
                    ['Disciplina', $exam->discipline?->name ?: 'Não informada'],
                    ['Modalidade', $applicationModeLabel],
                    ['Tentativa', 'Nº '.$submission->attempt_number],
                    ['Enviada em', $submission->finished_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: 'Horário não registrado'],
                ] as [$label, $value])
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <dt class="text-gray-600">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-extrabold text-gray-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            @if($availability['closes_at'])
                <p class="mt-4 text-sm text-gray-700">
                    Prazo geral da avaliação: {{ $availability['closes_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                </p>
            @endif
        </section>

        @if($release['show_feedback'] && $submission->feedback)
            <section class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6" aria-labelledby="overall-feedback-title">
                <h2 id="overall-feedback-title" class="text-xl font-extrabold text-blue-950">Comentário geral do professor</h2>
                <p class="text-blue-950 whitespace-pre-wrap mt-3">{{ $submission->feedback }}</p>
            </section>
        @endif

        @if($release['show_feedback'])
            <section class="bg-white border-2 border-duo-border rounded-2xl p-6" aria-labelledby="recommendations-title">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary" aria-hidden="true">school</span>
                    <div class="flex-1">
                        <h2 id="recommendations-title" class="text-xl font-extrabold text-gray-900">Próximos passos de estudo</h2>
                        <p class="text-gray-700 mt-1">
                            As recomendações abaixo usam as disciplinas e habilidades vinculadas às questões que
                            precisam de reforço.
                        </p>
                    </div>
                </div>

                @if($recommendations->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                        @foreach($recommendations as $recommendation)
                            <article class="rounded-xl border-2 border-green-200 bg-green-50 p-4">
                                <h3 class="font-extrabold text-green-950">{{ $recommendation['title'] }}</h3>
                                <p class="text-sm text-green-950 mt-2">{{ $recommendation['reason'] }}</p>
                                <p class="text-sm font-bold text-gray-800 mt-3">{{ $recommendation['action'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 p-4 bg-green-50 border border-green-200 rounded-xl text-green-950 font-bold">
                        Não identificamos conteúdos objetivos que precisem de reforço nesta tentativa.
                    </p>
                @endif

                @if($recommendedMaterials->isNotEmpty())
                    <div class="mt-7 border-t-2 border-duo-border pt-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 class="text-lg font-extrabold text-gray-900">Materiais para continuar</h3>
                                <p class="text-sm text-gray-700">
                                    Selecionados pelos assuntos da tentativa, sem expor respostas ou gabaritos restritos.
                                </p>
                            </div>
                            <a href="{{ route('student.learning.index') }}" class="text-sm font-extrabold text-primary-dark hover:underline">
                                Ver área de aprendizagem
                            </a>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            @foreach($recommendedMaterials as $material)
                                <article class="flex flex-col rounded-xl border-2 border-green-200 bg-green-50 p-4">
                                    <p class="text-xs font-extrabold uppercase tracking-wide text-green-900">
                                        {{ $material->discipline?->name ?: 'Conteúdo geral' }}
                                    </p>
                                    <h4 class="mt-2 font-extrabold text-gray-900">{{ $material->title }}</h4>
                                    <p class="mt-2 text-sm text-gray-800">{{ $material->recommendation_reason }}</p>
                                    <a href="{{ route('student.learning.show', $material) }}"
                                        class="duo-button-secondary mt-auto rounded-xl px-4 py-2 text-center text-sm font-extrabold">
                                        Abrir revisão
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        @if($release['show_answers'])
            <section class="space-y-6" aria-labelledby="answers-title">
                <div>
                    <h2 id="answers-title" class="text-2xl font-extrabold text-gray-900">Revisão das respostas</h2>
                    <p class="text-gray-700 mt-1">Confira o que foi liberado pelo professor para cada questão.</p>
                </div>

                @foreach($exam->questions as $index => $question)
                    @php
                        $answer = $submission->answers->firstWhere('question_id', $question->id);
                        $answerData = $answer
                            ? (is_array($answer->answer_data) ? $answer->answer_data : json_decode($answer->answer_data, true))
                            : [];
                        $studentRawAnswer = $answerData['raw'] ?? $answerData['selected'] ?? null;
                        $pointsAwarded = (float) ($answer?->points_awarded ?? 0);
                        $questionPoints = (float) ($question->pivot->points ?? 0);
                        $isCorrect = $answer?->is_correct === true;
                        $isPartial = !$isCorrect && $pointsAwarded > 0;
                        $statusText = $isCorrect ? 'Correta' : ($isPartial ? 'Parcialmente correta' : 'Incorreta');
                        $statusClasses = $isCorrect
                            ? 'border-green-400 bg-green-50 text-green-950'
                            : ($isPartial ? 'border-amber-400 bg-amber-50 text-amber-950' : 'border-red-400 bg-red-50 text-red-950');
                    @endphp

                    <article class="bg-white rounded-2xl border-2 border-duo-border p-5 md:p-7 shadow-sm"
                        aria-labelledby="result-question-{{ $question->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                            <div class="flex items-center gap-3">
                                <span aria-hidden="true"
                                    class="w-9 h-9 rounded-full bg-gray-100 border-2 border-gray-300 flex items-center justify-center font-extrabold text-gray-800">
                                    {{ $index + 1 }}
                                </span>
                                <span class="px-3 py-1.5 border-2 rounded-full text-sm font-extrabold {{ $statusClasses }}">
                                    {{ $statusText }}
                                </span>
                            </div>
                            <p class="font-bold text-gray-700">
                                {{ number_format($pointsAwarded, 1, ',', '.') }} de
                                {{ number_format($questionPoints, 1, ',', '.') }} ponto(s)
                            </p>
                        </div>

                        <h3 id="result-question-{{ $question->id }}"
                            class="text-lg font-bold text-gray-900 leading-relaxed mb-5">
                            {{ $question->content['statement'] }}
                        </h3>

                        @include('questions.partials.resource-list', ['context' => 'web'])

                        <div class="space-y-4">
                            <div class="p-4 bg-gray-50 rounded-xl border-l-4 border-gray-500">
                                <h4 class="text-sm font-extrabold text-gray-700 mb-2">Sua resposta</h4>

                                @if($studentRawAnswer === null || $studentRawAnswer === '')
                                    <p class="text-gray-700 italic font-medium">Não respondida</p>
                                @elseif($question->type === 'multiple_choice' || $question->type === 'true_false')
                                    <p class="font-bold text-gray-900">
                                        {{ $question->content['options'][(int) $studentRawAnswer] ?? 'Opção inválida' }}
                                    </p>
                                @else
                                    <p class="text-gray-900 whitespace-pre-wrap font-medium leading-relaxed">{{ $studentRawAnswer }}</p>
                                @endif
                            </div>

                            @if(!$isCorrect && in_array($question->type, ['multiple_choice', 'true_false'], true))
                                @php $correctIndex = (int) ($question->content['correct_option'] ?? -1); @endphp
                                <div class="p-4 bg-green-50 rounded-xl border-l-4 border-green-700">
                                    <h4 class="text-sm font-extrabold text-green-950 mb-2">Resposta correta</h4>
                                    <p class="font-bold text-green-950">
                                        {{ $question->content['options'][$correctIndex] ?? 'Gabarito não configurado' }}
                                    </p>
                                </div>
                            @endif

                            @if($release['show_feedback'])
                                @php
                                    $feedback = $answer?->feedback
                                        ?: ($question->content['feedback'] ?? $question->content['explanation'] ?? null);
                                @endphp
                                @if($feedback)
                                    <div class="p-4 bg-blue-50 rounded-xl border-l-4 border-blue-700">
                                        <h4 class="text-sm font-extrabold text-blue-950 mb-2">Comentário</h4>
                                        <p class="text-blue-950 whitespace-pre-wrap">{{ $feedback }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="bg-white border-2 border-duo-border rounded-2xl p-6 text-center">
                <span class="material-symbols-outlined text-4xl text-gray-500" aria-hidden="true">visibility_off</span>
                <h2 class="text-xl font-extrabold text-gray-900 mt-2">Respostas não liberadas</h2>
                <p class="text-gray-700 mt-2">
                    O professor optou por não exibir as respostas e o gabarito neste momento.
                </p>
            </section>
        @endif
    </div>
</x-app-layout>
