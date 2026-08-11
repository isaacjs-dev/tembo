<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-bold text-primary-dark">Aula · somente leitura</p><h1 class="text-3xl font-black text-duo-heading">{{ $lesson->title }}</h1></div></x-slot>
    <article class="mx-auto max-w-5xl space-y-5 rounded-2xl border-2 border-duo-border bg-white p-6">
        <div class="grid gap-4 sm:grid-cols-2"><div><p class="text-xs font-bold text-gray-500">AUTOR</p><p class="font-bold">{{ $lesson->author?->name }}</p></div><div><p class="text-xs font-bold text-gray-500">DISCIPLINA</p><p class="font-bold">{{ $lesson->discipline?->name ?: 'Geral' }}</p></div></div>
        <div><h2 class="font-extrabold">Objetivos</h2><p class="mt-2 whitespace-pre-wrap text-gray-700">{{ $lesson->objectives ?: 'Não informados.' }}</p></div>
        <div><h2 class="font-extrabold">Conteúdo</h2><p class="mt-2 whitespace-pre-wrap text-gray-700">{{ $lesson->content ?: 'Não informado.' }}</p></div>
        <div><h2 class="font-extrabold">Turmas</h2><p class="mt-2 text-gray-700">{{ $lesson->schoolClasses->pluck('name')->join(', ') ?: 'Nenhuma.' }}</p></div>
        <a href="{{ route('lessons.index') }}" class="duo-button-secondary inline-flex rounded-xl px-5 py-3 font-bold">Voltar</a>
    </article>
</x-app-layout>
