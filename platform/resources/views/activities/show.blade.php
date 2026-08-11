<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-bold text-primary-dark">Atividade · somente leitura</p><h1 class="text-3xl font-black text-duo-heading">{{ $activity->title }}</h1></div></x-slot>
    <article class="mx-auto max-w-5xl space-y-5 rounded-2xl border-2 border-duo-border bg-white p-6">
        <div class="grid gap-4 sm:grid-cols-3"><div><p class="text-xs font-bold text-gray-500">AUTOR</p><p class="font-bold">{{ $activity->author?->name }}</p></div><div><p class="text-xs font-bold text-gray-500">DISCIPLINA</p><p class="font-bold">{{ $activity->discipline?->name ?: 'Geral' }}</p></div><div><p class="text-xs font-bold text-gray-500">MODALIDADE</p><p class="font-bold">{{ ucfirst($activity->modality) }}</p></div></div>
        <div><h2 class="font-extrabold">Instruções</h2><p class="mt-2 whitespace-pre-wrap text-gray-700">{{ $activity->instructions ?: 'Não informadas.' }}</p></div>
        <div><h2 class="font-extrabold">Turmas</h2><p class="mt-2 text-gray-700">{{ $activity->schoolClasses->pluck('name')->join(', ') ?: 'Nenhuma.' }}</p></div>
        <section><h2 class="font-extrabold">Questões</h2><ol class="mt-2 list-decimal space-y-2 pl-6">@forelse($activity->questions as $question)<li>{{ data_get($question->content,'statement','Sem enunciado') }}</li>@empty<li class="list-none text-gray-600">Nenhuma questão vinculada.</li>@endforelse</ol></section>
        <a href="{{ route('activities.index') }}" class="duo-button-secondary inline-flex rounded-xl px-5 py-3 font-bold">Voltar</a>
    </article>
</x-app-layout>
