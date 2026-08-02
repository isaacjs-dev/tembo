<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('exams.index') }}">Avaliações</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('exams.show', $exam->id) }}">Resultados</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Correção</span>
                </nav>
                <h1 class="page-title">Correção: {{ $submission->user->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-neutral flex items-center gap-1">
                    Nota:
                    <span class="text-primary text-lg font-extrabold">
                        {{ number_format((float) $submission->score, 1, ',', '.') }}
                    </span>
                </span>
                <a href="{{ route('exams.show', $exam->id) }}" class="btn-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">
            <p class="font-extrabold mb-2">Revise os dados da correção:</p>
            <ul class="list-disc pl-5 space-y-1 text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('exams.storeGrade', [$exam->id, $submission->id]) }}" method="POST">
        @csrf

        <div class="space-y-8 mb-24">
            @foreach ($exam->questions as $index => $question)
                @php
                    $answer = $submission->answers->firstWhere('question_id', $question->id);
                    $answerData = $answer?->answer_data ?? [];

                    if (is_string($answerData)) {
                        $answerData = json_decode($answerData, true) ?: [];
                    }

                    $studentRawAnswer = is_array($answerData)
                        ? ($answerData['raw'] ?? $answerData['original_raw'] ?? $answerData['selected'] ?? null)
                        : $answerData;
                    $isCorrect = (bool) ($answer?->is_correct ?? false);
                    $questionPoints = (float) $question->pivot->points;
                    $rubric = $question->type === 'essay'
                        ? data_get($question->content, 'rubric')
                        : null;
                    $rubricCriteria = is_array($rubric)
                        ? ($rubric['criteria'] ?? [])
                        : [];

                    $trueFalseIndex = match (true) {
                        in_array($studentRawAnswer, [0, '0', true, 'true', 'verdadeiro', 'V', 'v'], true) => 0,
                        in_array($studentRawAnswer, [1, '1', false, 'false', 'falso', 'F', 'f'], true) => 1,
                        default => null,
                    };
                @endphp

                <section
                    class="bg-white rounded-2xl border-2 {{ $question->type === 'essay' && $submission->status === 'submitted' ? 'border-yellow-400' : 'border-duo-border' }} p-6 lg:p-8 shadow-sm"
                    id="question-{{ $question->id }}">
                    <div class="flex items-start gap-4 flex-col lg:flex-row">
                        <div
                            class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center font-extrabold text-gray-500 border-2 border-duo-border bg-gray-50">
                            {{ $index + 1 }}
                        </div>

                        <div class="flex-1 w-full space-y-4">
                            <div class="flex justify-between items-start gap-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-bold text-primary uppercase tracking-widest">
                                        @if ($question->type === 'multiple_choice')
                                            Múltipla escolha
                                        @elseif ($question->type === 'true_false')
                                            Verdadeiro/Falso
                                        @else
                                            Discursiva
                                        @endif
                                    </span>
                                    <h3 class="text-base font-bold text-gray-800 leading-relaxed">
                                        {{ data_get($question->content, 'statement') }}
                                    </h3>
                                </div>
                            </div>

                            <div
                                class="p-4 bg-background-light rounded-xl border-l-4 {{ $isCorrect ? 'border-green-500' : ($question->type === 'essay' ? 'border-yellow-400' : 'border-red-500') }}">
                                <h4 class="text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-2">
                                    Resposta do aluno
                                </h4>

                                @if (is_null($studentRawAnswer) || $studentRawAnswer === '')
                                    <span class="text-gray-400 italic font-medium">Não respondida</span>
                                @elseif ($question->type === 'multiple_choice')
                                    <div class="font-bold text-gray-800">
                                        {{ data_get($question->content, "options.{$studentRawAnswer}", 'Opção inválida') }}
                                    </div>
                                    @unless ($isCorrect)
                                        <div class="text-xs text-red-500 mt-2 font-bold flex flex-col gap-1">
                                            <span>Gabarito correto:</span>
                                            <span class="text-gray-600">
                                                {{ data_get(
                                                    $question->content,
                                                    'options.' . data_get($question->content, 'correct_option'),
                                                    '-'
                                                ) }}
                                            </span>
                                        </div>
                                    @endunless
                                @elseif ($question->type === 'true_false')
                                    @if (is_null($trueFalseIndex))
                                        <div class="font-bold text-gray-600">Resposta inválida</div>
                                    @else
                                        <div class="font-bold {{ $trueFalseIndex === 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $trueFalseIndex === 0 ? 'Verdadeiro' : 'Falso' }}
                                        </div>
                                    @endif
                                @else
                                    <p class="text-gray-800 whitespace-pre-wrap font-medium text-sm leading-relaxed">{{ $studentRawAnswer }}</p>
                                @endif
                            </div>

                            @if ($answer && count($rubricCriteria))
                                <div class="p-4 rounded-xl border-2 border-accent/15 bg-accent-light/50 space-y-4">
                                    <div>
                                        <h4 class="font-extrabold text-gray-800">
                                            {{ $rubric['title'] ?? 'Rubrica de correção' }}
                                        </h4>
                                        @if (!empty($rubric['description']))
                                            <p class="mt-1 text-sm text-gray-600">{{ $rubric['description'] }}</p>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach ($rubricCriteria as $criterionIndex => $criterion)
                                            <label class="block p-3 bg-white border border-accent/15 rounded-lg">
                                                <span class="block text-sm font-bold text-gray-800">
                                                    {{ $criterion['title'] ?? 'Critério ' . ($criterionIndex + 1) }}
                                                </span>
                                                @if (!empty($criterion['description']))
                                                    <span class="block text-xs text-gray-500 mt-1">
                                                        {{ $criterion['description'] }}
                                                    </span>
                                                @endif
                                                <span class="mt-2 flex items-center gap-2">
                                                    <input
                                                        type="number"
                                                        name="rubric_scores[{{ $answer->id }}][{{ $criterionIndex }}]"
                                                        value="{{ old(
                                                            "rubric_scores.{$answer->id}.{$criterionIndex}",
                                                            data_get($answer->rubric_scores, $criterionIndex)
                                                        ) }}"
                                                        min="0"
                                                        max="{{ (float) ($criterion['points'] ?? 0) }}"
                                                        step="0.25"
                                                        class="w-24 px-3 py-2 bg-white border-2 border-duo-border rounded-lg focus:border-primary focus:ring-0">
                                                    <span class="text-xs font-bold text-gray-500">
                                                        / {{ number_format((float) ($criterion['points'] ?? 0), 2, ',', '.') }}
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($answer)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="block">
                                        <span class="block text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-2">
                                            Feedback para o aluno
                                        </span>
                                        <textarea
                                            name="feedback[{{ $answer->id }}]"
                                            rows="3"
                                            maxlength="5000"
                                            class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0"
                                            placeholder="Orientação específica sobre esta resposta...">{{ old("feedback.{$answer->id}", $answer->feedback) }}</textarea>
                                    </label>

                                    <label class="block">
                                        <span class="block text-xs font-extrabold text-gray-500 uppercase tracking-widest mb-2">
                                            Justificativa da correção
                                        </span>
                                        <textarea
                                            name="justification[{{ $answer->id }}]"
                                            rows="3"
                                            maxlength="5000"
                                            class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0"
                                            placeholder="Registro interno do critério utilizado...">{{ old("justification.{$answer->id}", $answer->grading_justification) }}</textarea>
                                    </label>
                                </div>
                            @endif
                        </div>

                        <div
                            class="shrink-0 flex flex-col items-center gap-2 p-4 bg-gray-50 border-2 border-duo-border rounded-xl min-w-[140px]">
                            <label class="text-xs font-extrabold text-gray-500 uppercase tracking-widest">Nota</label>

                            @if ($answer)
                                <input
                                    type="number"
                                    step="0.25"
                                    min="0"
                                    max="{{ $questionPoints }}"
                                    name="points[{{ $answer->id }}]"
                                    value="{{ old("points.{$answer->id}", (float) ($answer->points_awarded ?? 0)) }}"
                                    required
                                    class="w-full text-center px-2 py-2 bg-white border-2 border-duo-border rounded-lg focus:outline-none focus:border-primary text-lg font-bold">
                            @else
                                <span class="text-xs text-red-600 text-center font-bold">
                                    Resposta ausente na submissão
                                </span>
                            @endif

                            <span class="text-xs font-bold text-gray-400 mt-1">
                                / {{ number_format($questionPoints, 2, ',', '.') }} pt
                            </span>
                        </div>
                    </div>
                </section>
            @endforeach

            <section class="bg-white rounded-2xl border-2 border-duo-border p-6 lg:p-8 shadow-sm">
                <label for="general_feedback" class="block text-sm font-extrabold text-gray-800 mb-2">
                    Feedback geral da avaliação
                </label>
                <p class="text-xs text-gray-500 mb-3">
                    Registre uma orientação final para contextualizar a nota e os próximos passos do aluno.
                </p>
                <textarea
                    id="general_feedback"
                    name="general_feedback"
                    rows="4"
                    maxlength="10000"
                    class="block w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0"
                    placeholder="Síntese do desempenho nesta avaliação...">{{ old('general_feedback', $submission->feedback) }}</textarea>
            </section>
        </div>

        <div
            class="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-duo-border p-4 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] z-50">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div class="text-sm font-bold text-gray-500 hidden sm:block">
                    Confira notas, rubricas e feedbacks antes de concluir.
                </div>
                <button
                    type="submit"
                    class="btn-primary px-8 py-4 w-full sm:w-auto rounded-xl text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">save</span>
                    Salvar correção final
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
