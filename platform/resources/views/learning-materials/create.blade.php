<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('learning-materials.index') }}" class="text-sm font-bold text-primary-dark hover:underline">Materiais de estudo</a>
            <h1 class="mt-1 text-3xl font-black text-duo-heading">Novo material</h1>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('learning-materials.store') }}" class="mx-auto max-w-7xl">
        @csrf
        @include('learning-materials._form', ['submitLabel' => 'Criar material'])
    </form>
</x-app-layout>
