<x-app-layout>
    <x-slot name="header">
        <div class="page-header"><div><nav class="breadcrumb"><a href="{{ route('questions.index') }}">Banco de Questões</a><span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span><a href="{{ route('question-resources.index') }}">Materiais de apoio</a><span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Novo recurso</span></nav><h1 class="page-title">Novo material de apoio</h1></div></div>
    </x-slot>
    <form method="POST" action="{{ route('question-resources.store') }}" enctype="multipart/form-data">@csrf @include('question-resources.partials.form')</form>
</x-app-layout>
