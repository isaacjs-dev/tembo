<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold text-primary-dark">Conteúdo pedagógico</p>
                <h1 class="text-3xl font-black text-duo-heading">Materiais de estudo</h1>
                <p class="mt-1 text-gray-600">Publique revisões direcionadas para suas turmas.</p>
            </div>
            <a href="{{ route('learning-materials.create') }}"
                class="duo-button-primary inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 font-extrabold">
                <span class="material-symbols-outlined" aria-hidden="true">add</span>
                Novo material
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <form method="GET" class="grid gap-4 rounded-2xl border-2 border-duo-border bg-white p-4 sm:grid-cols-[1fr_12rem_auto]"
            aria-label="Filtrar materiais">
            <div>
                <label for="material-search" class="mb-1 block text-sm font-bold text-gray-800">Buscar</label>
                <input id="material-search" name="search" value="{{ request('search') }}" maxlength="120"
                    class="w-full rounded-xl border-2 border-gray-300" placeholder="Título ou descrição">
            </div>
            <div>
                <label for="material-status" class="mb-1 block text-sm font-bold text-gray-800">Situação</label>
                <select id="material-status" name="status" class="input-field">
                    <option value="">Todas</option>
                    <option value="draft" @selected(request('status') === 'draft')>Rascunho</option>
                    <option value="published" @selected(request('status') === 'published')>Publicado</option>
                </select>
            </div>
            <button class="duo-button-secondary self-end rounded-xl px-5 py-3 font-extrabold">Filtrar</button>
        </form>

        @if($materials->isEmpty())
            <div class="rounded-2xl border-2 border-dashed border-gray-300 bg-white p-10 text-center">
                <span class="material-symbols-outlined text-5xl text-gray-500" aria-hidden="true">menu_book</span>
                <h2 class="mt-3 text-xl font-extrabold text-gray-900">Nenhum material encontrado</h2>
                <p class="mt-1 text-gray-600">Crie um material ou ajuste os filtros.</p>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach($materials as $material)
                    <article class="flex flex-col rounded-2xl border-2 border-duo-border bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $material->status === 'published' ? 'bg-green-100 text-green-900' : 'bg-amber-100 text-amber-950' }}">
                                {{ $material->status === 'published' ? 'Publicado' : 'Rascunho' }}
                            </span>
                            <span class="text-xs font-bold text-gray-600">{{ $material->discipline?->name ?: 'Geral' }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-extrabold text-gray-900">{{ $material->title }}</h2>
                        <p class="mt-2 line-clamp-3 text-sm text-gray-700">
                            {{ $material->description ?: 'Sem descrição breve.' }}
                        </p>
                        <p class="mt-4 text-xs font-bold text-gray-600">
                            {{ $material->schoolClasses->pluck('name')->join(', ') ?: 'Nenhuma turma' }}
                        </p>
                        @if($material->status === 'published')
                            <p class="mt-2 text-xs font-bold text-gray-600">
                                {{ $material->opened_count }} estudante(s) abriram ·
                                {{ $material->completed_count }} concluíram
                            </p>
                        @endif
                        <div class="mt-auto flex items-center gap-3 pt-5">
                            <a href="{{ route('learning-materials.edit', $material) }}"
                                class="duo-button-secondary flex-1 rounded-xl px-4 py-2 text-center font-extrabold">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('learning-materials.destroy', $material) }}"
                                onsubmit="return confirm('Enviar este material para a lixeira?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-xl border-2 border-red-300 px-3 py-2 font-bold text-red-800 hover:bg-red-50"
                                    aria-label="Excluir {{ $material->title }}">
                                    <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $materials->links() }}
        @endif
    </div>
</x-app-layout>
