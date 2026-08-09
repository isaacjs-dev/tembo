<x-app-layout>
    <x-slot name="header">
        <div class="page-header"><div><nav class="breadcrumb"><a href="{{ route('questions.index') }}">Banco de Questões</a><span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span><a href="{{ route('question-resources.index') }}">Materiais de apoio</a><span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Editar</span></nav><h1 class="page-title">{{ $resource->title }}</h1><p class="mt-1 text-sm text-gray-600">Versão atual: {{ $resource->currentVersion?->version_number ?? '—' }}</p></div></div>
    </x-slot>
    <form method="POST" action="{{ route('question-resources.update', $resource) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('question-resources.partials.form')</form>
    @if($resource->versions->isNotEmpty())
        <section class="card mt-8 p-5 md:p-7" aria-labelledby="resource-history-heading">
            <h2 id="resource-history-heading" class="text-xl font-extrabold text-duo-heading">Histórico imutável</h2>
            <div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b-2 border-duo-border text-gray-700"><tr><th class="p-3">Versão</th><th class="p-3">Criada em</th><th class="p-3">Responsável</th><th class="p-3">Arquivo</th></tr></thead><tbody>
                @foreach($resource->versions as $version)
                    <tr class="border-b border-gray-200"><td class="p-3 font-extrabold">v{{ $version->version_number }}</td><td class="p-3">{{ $version->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td><td class="p-3">{{ $version->creator?->name ?: 'Sistema' }}</td><td class="p-3">@if($version->storage_path)<a class="font-bold text-primary underline" href="{{ route('question-resources.versions.download', [$resource, $version]) }}">Baixar</a>@else<span class="text-gray-500">Sem arquivo</span>@endif</td></tr>
                @endforeach
            </tbody></table></div>
        </section>
    @endif
</x-app-layout>
