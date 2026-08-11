<x-app-layout>
    <x-slot name="header"><h1 class="text-3xl font-black text-duo-heading">Relatório · {{ $activity->title }}</h1></x-slot>
    <div class="space-y-4">
        @forelse($students as $student)
            <section class="rounded-2xl border-2 border-duo-border bg-white p-5"><h2 class="text-xl font-extrabold">{{ $student->name }}</h2>
                @forelse($attempts->get($student->id,collect()) as $attempt)
                    @php($manualOverall=empty($attempt->content_snapshot['questions']) || data_get($attempt->content_snapshot,'activity.modality')==='paper')
                    <div class="mt-3 rounded-xl border p-4"><div class="flex flex-wrap justify-between gap-2"><p class="font-bold">Tentativa {{ $attempt->attempt_number }} · {{ str_replace('_',' ',ucfirst($attempt->status)) }}</p><p>{{ number_format((float)$attempt->score,1,',','.') }} / {{ number_format((float)$attempt->total_points,1,',','.') }}</p></div>
                        @if(in_array($attempt->status,['submitted','graded'],true) && ($manualOverall || collect($attempt->content_snapshot['questions'] ?? [])->contains(fn($q)=>($q['type']??null)==='essay')))
                            <form class="mt-4 space-y-3" method="POST" action="{{ route('activities.attempts.grade',[$activity,$attempt]) }}">@csrf
                                @if($manualOverall)
                                    <label class="block">Pontuação final (máx. {{ $attempt->total_points }})<input class="input-field mt-1 w-full" type="number" min="0" max="{{ $attempt->total_points }}" step="0.01" name="overall_score" value="{{ $attempt->score }}" required></label>
                                @else
                                    @foreach(collect($attempt->content_snapshot['questions'] ?? [])->where('type','essay') as $index=>$question)
                                        @php($response=$attempt->responses->firstWhere('snapshot_question_key',$question['key']))
                                        <fieldset class="rounded-xl bg-gray-50 p-3"><legend class="font-bold">Discursiva {{ $index+1 }}</legend><p class="text-sm">{{ data_get($question,'content.statement') }}</p><p class="mt-2 text-sm">Resposta: {{ data_get($response?->answer,'value','Em branco') }}</p><div class="mt-2 grid gap-2 sm:grid-cols-2"><label>Pontos (máx. {{ $question['points'] }})<input class="input-field w-full" type="number" min="0" max="{{ $question['points'] }}" step="0.01" name="scores[{{ $question['key'] }}]" value="{{ $response?->points_awarded ?? 0 }}" required></label><label>Feedback<input class="input-field w-full" name="feedback[{{ $question['key'] }}]" value="{{ $response?->feedback }}"></label></div></fieldset>
                                    @endforeach
                                @endif
                                <button class="duo-button-primary rounded-xl px-5 py-2 font-bold">Salvar correção</button>
                            </form>
                        @endif
                    </div>
                @empty<p class="mt-2 text-gray-600">Não iniciou.</p>@endforelse
            </section>
        @empty<p>Nenhum aluno ativo nas turmas.</p>@endforelse
    </div>
    <div class="mt-4">{{ $students->withQueryString()->links() }}</div>
</x-app-layout>
