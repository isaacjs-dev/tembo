<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><div><p class="text-sm font-bold text-primary-dark">Conteúdo pedagógico</p><h1 class="text-3xl font-black text-duo-heading">Aulas</h1><p class="text-gray-600">Planeje, publique e gere revisões para suas turmas.</p></div>@if($canCreate)<a class="duo-button-primary rounded-xl px-5 py-3 font-extrabold" href="{{ route('lessons.create') }}">Nova aula</a>@endif</div></x-slot>
    <div class="mx-auto max-w-7xl space-y-4">
        @forelse($lessons as $lesson)
            <article class="rounded-2xl border-2 border-duo-border bg-white p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-900">{{ ucfirst($lesson->status) }}</span><h2 class="mt-2 text-xl font-extrabold">{{ $lesson->title }}</h2><p class="text-sm text-gray-600">{{ $lesson->discipline?->name ?: 'Geral' }} · {{ $lesson->schoolClasses->pluck('name')->join(', ') }}</p></div>@if(in_array((int) $lesson->id,$manageableIds,true))<a class="duo-button-secondary rounded-xl px-4 py-2 font-bold" href="{{ route('lessons.edit',$lesson) }}">Editar</a>@else<a class="duo-button-secondary rounded-xl px-4 py-2 font-bold" href="{{ route('lessons.show',$lesson) }}">Visualizar</a>@endif</div></article>
        @empty <div class="rounded-2xl border-2 border-dashed bg-white p-10 text-center"><h2 class="text-xl font-bold">Nenhuma aula cadastrada</h2></div> @endforelse
        {{ $lessons->links() }}
    </div>
</x-app-layout>
