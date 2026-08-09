<x-app-layout>
    <x-slot name="header"><div class="mb-8"><h1 class="text-4xl font-extrabold text-duo-heading">Conceder cortesia</h1><p class="mt-1 text-gray-500">Benefícios temporários, acumuláveis e totalmente auditados.</p></div></x-slot>
    <form method="POST" action="{{ route('admin.courtesies.store') }}">@csrf @include('admin.courtesies._form', ['submitLabel' => 'Conceder cortesia'])</form>
</x-app-layout>
