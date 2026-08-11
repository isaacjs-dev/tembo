<x-app-layout>
    <x-slot name="header">
        <div class="page-header"><div><nav class="breadcrumb"><a href="{{ route('appearance-templates.index') }}">Aparência</a><span class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Novo</span></nav><h1 class="page-title">Novo {{ $kind === 'assessment_header' ? 'cabeçalho' : 'layout' }}</h1></div></div>
    </x-slot>
    @include('appearance-templates.partials.form', [
        'action' => route('appearance-templates.store'), 'method' => 'POST', 'template' => null,
        'kind' => $kind, 'definition' => $definition,
    ])
</x-app-layout>
