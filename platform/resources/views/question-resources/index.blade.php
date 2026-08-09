@php
    $typeLabels = ['text' => 'Texto', 'image' => 'Imagem', 'chart' => 'Gráfico', 'table' => 'Tabela', 'formula' => 'Fórmula', 'diagram' => 'Diagrama', 'document' => 'Documento'];
    $visibilityLabels = ['private' => 'Privado', 'shared_specific' => 'Compartilhado', 'organization' => 'Institucional', 'platform_public' => 'Público'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb"><a href="{{ route('questions.index') }}">Banco de Questões</a><span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Materiais de apoio</span></nav>
                <h1 class="page-title">Materiais de apoio</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">Textos, imagens, gráficos, tabelas, fórmulas e documentos reutilizáveis com versões preservadas.</p>
            </div>
            <a href="{{ route('question-resources.create') }}" class="btn-primary"><span aria-hidden="true" class="material-symbols-outlined">add_circle</span>Novo recurso</a>
        </div>
    </x-slot>

    @php
        $tabs = [
            'mine' => ['label' => 'Meus recursos', 'short' => 'Meus', 'icon' => 'person'],
            'shared' => ['label' => 'Compartilhados comigo', 'short' => 'Comigo', 'icon' => 'group'],
            'institution' => ['label' => 'Institucionais', 'short' => 'Instituição', 'icon' => 'domain'],
            'platform' => ['label' => 'Públicos da plataforma', 'short' => 'Públicos', 'icon' => 'public'],
        ];
    @endphp
    <nav class="mb-6 grid grid-cols-2 gap-2 border-b-2 border-duo-border sm:grid-cols-4" aria-label="Escopos dos materiais de apoio">
        @foreach($tabs as $key => $tab)
            <a href="{{ route('question-resources.index', ['scope' => $key]) }}"
                @if($scope === $key) aria-current="page" @endif
                class="flex min-h-12 items-center justify-center gap-2 border-b-2 px-2 py-3 text-center text-xs font-extrabold transition-colors sm:text-sm {{ $scope === $key ? '-mb-[2px] border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-duo-heading' }}">
                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                <span class="hidden lg:inline">{{ $tab['label'] }}</span><span class="lg:hidden">{{ $tab['short'] }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $scope === $key ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="mb-6 grid gap-3 rounded-xl border-2 border-duo-border bg-white p-4 sm:grid-cols-2 xl:grid-cols-[1fr_14rem_12rem_auto_auto]">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div><label for="resource-search" class="sr-only">Buscar recurso</label><input id="resource-search" name="search" type="search" value="{{ request('search') }}" class="input-field" placeholder="Buscar por título, conteúdo ou autor"></div>
        <div><label for="resource-type" class="sr-only">Filtrar por tipo</label><select id="resource-type" name="type" class="input-field"><option value="">Todos os tipos</option>@foreach($typeLabels as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="resource-sort" class="sr-only">Ordenar recursos</label><select id="resource-sort" name="sort" class="input-field"><option value="newest" @selected(request('sort', 'newest') === 'newest')>Mais recentes</option><option value="oldest" @selected(request('sort') === 'oldest')>Mais antigos</option><option value="title" @selected(request('sort') === 'title')>Título A–Z</option></select></div>
        <button class="btn-primary justify-center" type="submit">Filtrar</button>
        @if(request()->hasAny(['search', 'type', 'sort']))<a href="{{ route('question-resources.index', ['scope' => $scope]) }}" class="btn-ghost justify-center">Limpar</a>@endif
    </form>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($items as $resource)
            <article class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="text-xs font-extrabold uppercase tracking-wide text-primary">{{ $typeLabels[$resource->type] ?? ucfirst($resource->type) }}</p><h2 class="mt-1 break-words text-lg font-extrabold text-duo-heading">{{ $resource->title }}</h2></div>
                    <span class="badge badge-neutral shrink-0">{{ $visibilityLabels[$resource->visibility_scope] ?? $resource->visibility_scope }}</span>
                </div>
                <p class="mt-3 line-clamp-3 text-sm text-gray-700">{{ data_get($resource->currentVersion?->content, 'body') ?: 'Recurso baseado em arquivo ou URL.' }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500">Versão</dt><dd class="font-extrabold">v{{ $resource->currentVersion?->version_number ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Uso neste espaço</dt><dd class="font-extrabold">{{ $resource->questions_count }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500">Autor</dt><dd class="font-extrabold">{{ $resource->owner?->name ?: 'Não informado' }}</dd></div>
                </dl>
                <div class="mt-auto flex flex-wrap gap-2 pt-5">
                    @if((int) $resource->owner_id === (int) auth()->id() && $resource->visibility_scope !== 'platform_public')
                        @if($resource->has_active_public_submission)
                            <span class="badge badge-warning">Em moderação</span>
                        @else
                            <a href="{{ route('public-catalog.submissions.create', ['type' => 'resource', 'id' => $resource->id]) }}" class="btn-secondary btn-sm">Enviar ao catálogo</a>
                        @endif
                    @elseif($resource->visibility_scope === 'platform_public' && (int) $resource->owner_id !== (int) auth()->id())
                        <a href="{{ route('public-catalog.reports.create', ['type' => 'resource', 'id' => $resource->id]) }}" class="btn-ghost btn-sm text-red-700">Denunciar</a>
                    @endif
                    @can('update', $resource)<a href="{{ route('question-resources.edit', $resource) }}" class="btn-secondary btn-sm">Editar</a>@endcan
                    @can('delete', $resource)
                        <form method="POST" action="{{ route('question-resources.destroy', $resource) }}" onsubmit="return confirm('Arquivar este recurso? Os vínculos históricos serão preservados.');">@csrf @method('DELETE')<button type="submit" class="btn-ghost btn-sm text-red-700">Arquivar</button></form>
                    @endcan
                </div>
            </article>
        @empty
            <div class="card p-10 text-center md:col-span-2 xl:col-span-3"><span aria-hidden="true" class="material-symbols-outlined text-5xl text-gray-300">collections_bookmark</span><h2 class="mt-3 text-xl font-extrabold text-duo-heading">Nenhum material encontrado</h2><p class="mt-2 text-gray-600">Não há recursos neste escopo com os filtros selecionados.</p></div>
        @endforelse
    </div>
    @if($items->hasPages())<div class="mt-6">{{ $items->links() }}</div>@endif
</x-app-layout>
