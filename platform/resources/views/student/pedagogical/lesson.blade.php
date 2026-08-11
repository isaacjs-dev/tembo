<x-app-layout>
    @php($snapshot=$progress->content_snapshot['lesson'] ?? [])
    <x-slot name="header"><div><a class="text-sm font-bold text-primary" href="{{ route('student.pedagogical.index') }}">← Aulas e atividades</a><h1 class="mt-2 text-3xl font-black text-duo-heading">{{ $snapshot['title'] ?? $lesson->title }}</h1></div></x-slot>
    <article class="mx-auto max-w-4xl space-y-6 rounded-2xl border-2 border-duo-border bg-white p-6">
        @if(!empty($snapshot['objectives']))<section><h2 class="text-xl font-extrabold">Objetivos</h2><p class="mt-2 whitespace-pre-wrap text-gray-700">{{ $snapshot['objectives'] }}</p></section>@endif
        <section><h2 class="text-xl font-extrabold">Conteúdo</h2><div class="mt-2 whitespace-pre-wrap text-gray-800">{{ $snapshot['content'] ?? 'Conteúdo não informado.' }}</div></section>
        @if($progress->status === 'completed')<p class="rounded-xl bg-green-50 p-4 font-bold text-green-800">Aula concluída em {{ $progress->completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</p>@else
            <form method="POST" action="{{ route('student.pedagogical.lessons.complete',$lesson) }}">@csrf<button class="duo-button-primary min-h-11 rounded-xl px-6 py-3 font-extrabold">Marcar aula como concluída</button></form>
        @endif
    </article>
</x-app-layout>
