<x-app-layout>
    <x-slot name="header"><div><h1 class="text-3xl font-black text-duo-heading">Aulas e atividades</h1><p class="mt-1 text-gray-600">Conteúdos entregues às suas turmas.</p></div></x-slot>
    <div class="space-y-10">
        <section><h2 class="mb-4 text-2xl font-extrabold">Aulas</h2><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($lessons as $lesson) @php($progress=$lesson->progress->first())
                <article class="flex flex-col rounded-2xl border-2 border-duo-border bg-white p-5"><h3 class="text-xl font-extrabold">{{ $lesson->title }}</h3><p class="mt-1 text-sm text-gray-600">{{ $lesson->discipline?->name ?: 'Geral' }} · {{ $lesson->author?->name }}</p><p class="mt-3 text-sm font-bold">{{ $progress?->status === 'completed' ? 'Concluída' : ($progress ? 'Em andamento' : 'Disponível') }}</p><a class="duo-button-secondary mt-5 rounded-xl px-4 py-3 text-center font-bold" href="{{ route('student.pedagogical.lessons.show',$lesson) }}">{{ $progress ? 'Continuar' : 'Abrir aula' }}</a></article>
            @empty <p class="text-gray-600">Nenhuma aula disponível.</p> @endforelse
        </div><div class="mt-4">{{ $lessons->withQueryString()->links() }}</div></section>
        <section><h2 class="mb-4 text-2xl font-extrabold">Atividades</h2><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($activities as $activity) @php($attempt=$activity->attempts->first())
                <article class="flex flex-col rounded-2xl border-2 border-duo-border bg-white p-5"><h3 class="text-xl font-extrabold">{{ $activity->title }}</h3><p class="mt-1 text-sm text-gray-600">{{ $activity->discipline?->name ?: 'Geral' }} · {{ $activity->author?->name }}</p><p class="mt-3 text-sm font-bold">{{ $attempt ? str_replace('_',' ',ucfirst($attempt->status)) : 'Disponível' }}</p><p class="mt-1 text-sm text-gray-600">Prazo: {{ $activity->due_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: 'sem prazo' }}</p><a class="duo-button-secondary mt-5 rounded-xl px-4 py-3 text-center font-bold" href="{{ route('student.pedagogical.activities.show',$activity) }}">Ver atividade</a></article>
            @empty <p class="text-gray-600">Nenhuma atividade disponível.</p> @endforelse
        </div><div class="mt-4">{{ $activities->withQueryString()->links() }}</div></section>
    </div>
</x-app-layout>
