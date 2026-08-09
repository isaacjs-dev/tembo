@php
    $resourceContext = $context ?? 'web';
@endphp

@if($question->resourceLinks->isNotEmpty())
    <aside class="question-resources mt-4 space-y-3 rounded-xl border-2 border-blue-200 bg-blue-50 p-4 md:ml-14" aria-label="Materiais de apoio da questão">
        @foreach($question->resourceLinks as $resourceLink)
            @php
                $resource = $resourceLink->resource;
                $version = $resourceLink->version;
                $resourceContent = $version?->content ?? [];
                $imageDataUri = null;
                if ($resourceContext === 'pdf'
                    && $version?->storage_disk
                    && $version?->storage_path
                    && str_starts_with((string) $version->mime_type, 'image/')
                    && Illuminate\Support\Facades\Storage::disk($version->storage_disk)->exists($version->storage_path)) {
                    $imageDataUri = 'data:'.$version->mime_type.';base64,'.base64_encode(
                        Illuminate\Support\Facades\Storage::disk($version->storage_disk)->get($version->storage_path)
                    );
                }
            @endphp

            @if($resource && $version)
                <section class="question-resource space-y-2">
                    <p class="question-resource-title font-extrabold text-blue-950">{{ $resourceContent['title'] ?? $resource->title }}</p>
                    @if(filled($resourceContent['body'] ?? null))
                        <p class="question-resource-body whitespace-pre-wrap text-gray-900">{{ $resourceContent['body'] }}</p>
                    @endif
                    @if($imageDataUri)
                        <img class="question-resource-image max-h-96 max-w-full rounded-lg" src="{{ $imageDataUri }}" alt="{{ $resourceContent['alt_text'] ?? $resource->title }}">
                    @elseif($resourceContext !== 'pdf' && $version->storage_path && str_starts_with((string) $version->mime_type, 'image/'))
                        <a href="{{ route('student.exam.resource', [$exam, $version]) }}" target="_blank" rel="noopener">
                            <img class="question-resource-image max-h-96 max-w-full rounded-lg" src="{{ route('student.exam.resource', [$exam, $version]) }}" alt="{{ $resourceContent['alt_text'] ?? $resource->title }}">
                        </a>
                    @elseif($resourceContext !== 'pdf' && $version->storage_path)
                        <a class="question-resource-file inline-flex min-h-11 items-center font-extrabold text-blue-800 underline" href="{{ route('student.exam.resource', [$exam, $version]) }}" target="_blank" rel="noopener">
                            Abrir {{ str_starts_with((string) $version->mime_type, 'image/') ? 'imagem' : 'arquivo' }} de apoio
                        </a>
                    @elseif($resourceContext === 'pdf' && $version->storage_path)
                        <p class="question-resource-file text-sm text-gray-700">Arquivo de apoio: {{ $version->mime_type ?: 'documento' }}</p>
                    @endif
                    @if(filled($resourceContent['external_url'] ?? null))
                        <p class="question-resource-url break-all text-xs text-gray-700">Fonte: {{ $resourceContent['external_url'] }}</p>
                    @endif
                </section>
            @endif
        @endforeach
    </aside>
@endif
