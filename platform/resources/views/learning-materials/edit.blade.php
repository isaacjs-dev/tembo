<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('learning-materials.index') }}" class="text-sm font-bold text-primary-dark hover:underline">Materiais de estudo</a>
            <h1 class="mt-1 text-3xl font-black text-duo-heading">Editar material</h1>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('learning-materials.update', $material) }}" class="mx-auto max-w-7xl">
        @csrf
        @method('PUT')
        @include('learning-materials._form', ['submitLabel' => 'Salvar alterações'])
    </form>
</x-app-layout>
