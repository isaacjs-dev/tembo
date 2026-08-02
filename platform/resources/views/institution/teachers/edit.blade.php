<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors"
                        href="{{ route('institution.teachers.index') }}">Professores</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar Professor</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Professor</h1>
            </div>
            <a href="{{ route('institution.teachers.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12 max-w-3xl">

        <!-- Error Handling -->
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

        <form action="{{ route('institution.teachers.update', $teacher->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                <label for="name" class="text-gray-800 font-bold text-sm">Nome Completo</label>
                <input type="text" id="name" name="name" value="{{ old('name', $teacher->name) }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                    placeholder="Nome do Professor">
            </div>

            <div class="space-y-2">
                <label for="email" class="text-gray-800 font-bold text-sm">E-mail (Usado para login)</label>
                <div class="relative group">
                    <div
                        class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">mail</span>
                    </div>
                    <input type="email" id="email" name="email" value="{{ old('email', $teacher->email) }}" required
                        class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="professor@escola.com.br">
                </div>
            </div>

            <div class="space-y-2">
                <label for="password" class="text-gray-800 font-bold text-sm">Nova Senha (deixe em branco para manter a
                    atual)</label>
                <div class="relative group">
                    <div
                        class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">lock</span>
                    </div>
                    <input type="password" id="password" name="password"
                        class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="Mínimo 8 caracteres (opcional)">
                </div>
            </div>

            <div class="space-y-2">
                <label for="status" class="text-gray-800 font-bold text-sm">Status</label>
                <select id="status" name="status"
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                    <option value="active" {{ (old('status', $teacher->status) == 'active') ? 'selected' : '' }}>Ativo
                    </option>
                    <option value="inactive" {{ (old('status', $teacher->status) == 'inactive') ? 'selected' : '' }}>
                        Inativo</option>
                </select>
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end gap-4">
                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    Atualizar Professor
                </button>
            </div>
        </form>

        <!-- DELETE form should be separate -->
        <hr class="my-8 border-duo-border border-2">
        <div class="mt-8 flex items-center justify-between p-6 bg-red-50 border-2 border-red-200 rounded-xl">
            <div>
                <h3 class="text-red-700 font-bold text-lg">Zona de Perigo</h3>
                <p class="text-red-600 text-sm mt-1">Desativar e remover este professor do sistema. (Soft Delete)</p>
            </div>
            <form action="{{ route('institution.teachers.destroy', $teacher->id) }}" method="POST"
                onsubmit="return confirm('Tem certeza que deseja inativar/remover este professor?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="px-6 py-3 bg-red-600 text-white font-bold rounded-xl shadow-[0_4px_0_0_#991b1b] hover:bg-red-700 active:shadow-none active:translate-y-1 transition-all">
                    Remover Professor
                </button>
            </form>
        </div>

    </div>
</x-app-layout>