<x-app-layout>
    <x-slot name="header"><div class="mb-8"><h1 class="text-4xl font-extrabold text-duo-heading">Editar cortesia #{{ $courtesy->id }}</h1><p class="mt-1 text-gray-500">Toda alteração ficará registrada na auditoria.</p></div></x-slot>
    <form method="POST" action="{{ route('admin.courtesies.update', $courtesy) }}">@csrf @method('PUT') @include('admin.courtesies._form', ['submitLabel' => 'Salvar alterações'])</form>
</x-app-layout>
