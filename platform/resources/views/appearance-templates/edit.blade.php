<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div><nav class="breadcrumb"><a href="{{ route('appearance-templates.index') }}">Aparência</a><span class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Editar</span></nav><h1 class="page-title">{{ $template->name }}</h1><p class="text-sm text-gray-500">Versão atual {{ $template->current_version }}. Cada salvamento cria uma nova versão imutável.</p></div>
            <form method="POST" action="{{ route('appearance-templates.archive', $template) }}" onsubmit="return confirm('Arquivar este template? Avaliações históricas continuarão preservadas.')">@csrf<button class="btn-danger" type="submit"><span class="material-symbols-outlined">archive</span>Arquivar</button></form>
        </div>
    </x-slot>
    @include('appearance-templates.partials.form', [
        'action' => route('appearance-templates.update', $template), 'method' => 'PUT',
        'kind' => $template->kind, 'definition' => $definition,
    ])
    <section class="card mt-6 p-5"><h2 class="mb-3 text-lg font-extrabold">Histórico</h2><ol class="space-y-2">@foreach($template->versions as $version)<li class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm"><span>Versão {{ $version->version }}{{ $version->change_summary ? ' — '.$version->change_summary : '' }}</span><code class="text-xs text-gray-400">{{ substr($version->content_hash, 0, 10) }}</code></li>@endforeach</ol></section>
</x-app-layout>
