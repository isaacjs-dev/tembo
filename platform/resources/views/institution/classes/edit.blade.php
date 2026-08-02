<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors"
                        href="{{ route('institution.classes.index') }}">Turmas</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar Turma</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Turma</h1>
            </div>
            <a href="{{ route('institution.classes.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12 max-w-3xl">

        @if ($errors->any())
            <div
                class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('institution.classes.update', $schoolClass->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                <label for="name" class="text-gray-800 font-bold text-sm">Nome da Turma</label>
                <input type="text" id="name" name="name" value="{{ old('name', $schoolClass->name) }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
            </div>

            <div class="space-y-2">
                <label for="year" class="text-gray-800 font-bold text-sm">Ano Letivo</label>
                <input type="text" id="year" name="year" value="{{ old('year', $schoolClass->year) }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end gap-4">
                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    Atualizar Turma
                </button>
            </div>
        </form>

        <hr class="my-8 border-duo-border border-2">
        <div class="mt-8 flex items-center justify-between p-6 bg-red-50 border-2 border-red-200 rounded-xl">
            <div>
                <h3 class="text-red-700 font-bold text-lg">Remover Turma</h3>
                <p class="text-red-600 text-sm mt-1">Excluir a turma permanentemente.</p>
            </div>
            <form action="{{ route('institution.classes.destroy', $schoolClass->id) }}" method="POST"
                onsubmit="return confirm('Certeza? Essa ação não pode ser desfeita e vai remover a associação de alunos a esta turma.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="px-6 py-3 bg-red-600 text-white font-bold rounded-xl shadow-[0_4px_0_0_#991b1b] hover:bg-red-700 active:shadow-none active:translate-y-1 transition-all">
                    Excluir Turma
                </button>
            </form>
        </div>

    </div>
</x-app-layout>