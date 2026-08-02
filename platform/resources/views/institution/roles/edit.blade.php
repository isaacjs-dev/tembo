<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="{{ route('institution.roles.index') }}">Cargos</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">{{ isset($role) ? 'Editar Cargo' : 'Novo Cargo' }}</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">
                    {{ isset($role) ? 'Editar Cargo' : 'Novo Cargo' }}
                </h1>
            </div>
            <a href="{{ route('institution.roles.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Cancelar
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl">
        @if ($errors->any())
            <div class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form action="{{ isset($role) ? route('institution.roles.update', $role) : route('institution.roles.store') }}"
            method="POST" class="space-y-6">
            @csrf
            @if (isset($role))
                @method('PUT')
            @endif

            {{-- Dados básicos --}}
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8">
                <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">badge</span>
                    Dados do Cargo
                </h2>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label for="name" class="text-gray-800 font-bold text-sm">Nome do Cargo</label>
                        <input type="text" id="name" name="name"
                            value="{{ old('name', $role->name ?? '') }}" required
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                            placeholder="Ex: Coordenador, Pedagogo, Secretário">
                    </div>

                    <div class="space-y-2">
                        <label for="description" class="text-gray-800 font-bold text-sm">Descrição (Opcional)</label>
                        <textarea id="description" name="description" rows="2"
                            class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                            placeholder="Breve descrição das responsabilidades">{{ old('description', $role->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Checkbox Matrix de Permissões --}}
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8">
                <h2 class="text-lg font-extrabold text-gray-700 mb-1 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">shield</span>
                    Permissões
                </h2>
                <p class="text-sm text-gray-400 mb-6">Selecione as funcionalidades que este cargo pode acessar. Configurações sensíveis (billing, admin, senhas) não estão disponíveis para perfis extras.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach ($availablePermissions as $key => $label)
                        <label class="flex items-center gap-3 p-3 rounded-xl border-2 border-duo-border hover:border-primary/50 transition-all cursor-pointer {{ in_array($key, old('permissions', $currentPermissions ?? [])) ? 'bg-primary/5 border-primary/30' : 'bg-background-light' }}"
                            x-data="{ checked: {{ in_array($key, old('permissions', $currentPermissions ?? [])) ? 'true' : 'false' }} }"
                            :class="checked ? 'bg-primary/5 border-primary/30' : 'bg-background-light'">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                x-model="checked"
                                {{ in_array($key, old('permissions', $currentPermissions ?? [])) ? 'checked' : '' }}
                                class="rounded border-2 border-duo-border text-primary focus:ring-primary focus:ring-offset-0 w-5 h-5">
                            <div>
                                <span class="font-bold text-sm text-gray-700">{{ $label }}</span>
                                <span class="block text-xs text-gray-400 font-mono">{{ $key }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center justify-between">
                @if (isset($role))
                    <form action="{{ route('institution.roles.destroy', $role) }}" method="POST"
                        onsubmit="return confirm('Excluir este cargo? Membros perderão o vínculo.')">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="text-red-500 hover:text-red-700 font-bold text-sm flex items-center gap-1">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">delete</span>
                            Excluir Cargo
                        </button>
                    </form>
                @else
                    <div></div>
                @endif

                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    {{ isset($role) ? 'Atualizar Cargo' : 'Criar Cargo' }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
