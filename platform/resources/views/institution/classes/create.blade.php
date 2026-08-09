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
                    <span class="text-primary">Nova Turma</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Nova Turma</h1>
            </div>
            <a href="{{ route('institution.classes.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Cancelar
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

        <form action="{{ route('institution.classes.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="space-y-2">
                <label for="name" class="text-gray-800 font-bold text-sm">Nome da Turma</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                    placeholder="Ex: 3º Ano do Ensino Médio - A">
            </div>

            <div class="space-y-2">
                <label for="year" class="text-gray-800 font-bold text-sm">Ano Letivo</label>
                <input type="text" id="year" name="year" value="{{ old('year') }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                    placeholder="Ex: 2026">
            </div>

            <fieldset class="space-y-3 rounded-xl border-2 border-duo-border p-5">
                <legend class="px-2 text-sm font-extrabold text-gray-800">Disciplinas da turma</legend>
                <p class="text-sm text-gray-500">Selecione as disciplinas que fazem parte desta turma. Você pode alterar esta lista depois.</p>

                @if ($disciplines->isEmpty())
                    <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">Nenhuma disciplina cadastrada neste workspace.</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($disciplines as $discipline)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-duo-border p-3 hover:border-primary">
                                <input type="checkbox" name="discipline_ids[]" value="{{ $discipline->id }}"
                                    @checked(in_array($discipline->id, old('discipline_ids', [])))
                                    class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="font-semibold text-gray-700">{{ $discipline->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end">
                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    Salvar Turma
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
