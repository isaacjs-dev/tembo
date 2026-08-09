@php
    $selectedResourceIds = collect(old(
        'resource_ids',
        isset($question) && $question->exists ? $question->resources->modelKeys() : [],
    ))->map(fn ($id) => (int) $id)->all();
@endphp

@if(isset($question) && $question->exists)
    @php
        $availableResourceIds = $availableResources->modelKeys();
        $preservedResourceLinks = $question->resourceLinks
            ->filter(fn ($link) => !in_array($link->question_resource_id, $availableResourceIds, true));
    @endphp
@endif

<section class="space-y-4 rounded-2xl border-2 border-duo-border bg-gray-50 p-5 md:p-6"
    aria-labelledby="question-resources-heading">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 id="question-resources-heading" class="text-lg font-extrabold text-gray-900">Materiais de apoio reutilizáveis</h2>
            <p class="mt-1 text-sm text-gray-700">
                Vincule textos, imagens, tabelas, fórmulas ou documentos sem duplicar o conteúdo. A versão atual fica
                fixada nesta questão.
            </p>
        </div>
        <a href="{{ route('question-resources.create') }}" target="_blank" rel="noopener"
            class="duo-button-secondary shrink-0 rounded-xl px-4 py-2 text-center text-sm font-extrabold">
            Criar recurso
        </a>
    </div>

    @error('resource_ids')
        <p class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-bold text-red-800">{{ $message }}</p>
    @enderror

    @if(($preservedResourceLinks ?? collect())->isNotEmpty())
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            <p class="font-extrabold">Há {{ $preservedResourceLinks->count() }} vínculo histórico preservado.</p>
            <p class="mt-1">O recurso foi arquivado ou deixou de estar acessível. Ele não será removido ao salvar esta questão.</p>
        </div>
    @endif

    @if($availableResources->isEmpty())
        <p class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700">
            Nenhum recurso acessível neste workspace. A questão pode ser salva sem material de apoio.
        </p>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($availableResources as $resourceOption)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 hover:border-primary/50">
                    <input type="checkbox" name="resource_ids[]" value="{{ $resourceOption->id }}"
                        @checked(in_array($resourceOption->id, $selectedResourceIds, true))
                        class="mt-0.5 size-5 rounded border-gray-400 text-primary focus:ring-primary">
                    <span class="min-w-0">
                        <span class="block break-words font-extrabold text-gray-900">{{ $resourceOption->title }}</span>
                        <span class="mt-1 block text-sm text-gray-600">
                            {{ ucfirst($resourceOption->type) }} · versão {{ $resourceOption->currentVersion?->version_number ?? '—' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    @endif
</section>
