<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Cargos Institucionais</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Cargos Institucionais
                </h1>
                <p class="text-sm text-gray-400 mt-1">Gerencie perfis extras com permissões limitadas (Coordenador,
                    Pedagogo, Secretário, etc.)</p>
            </div>
            <a href="{{ route('institution.roles.create') }}"
                class="duo-button-primary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">add</span>
                Novo Cargo
            </a>
        </div>
    </x-slot>

    @if (session('status'))
        <div
            class="mb-6 p-4 bg-green-50 border-2 border-green-200 rounded-xl text-green-700 font-medium text-sm flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    @if ($roles->isEmpty())
        <div class="bg-white rounded-2xl border-2 border-duo-border p-12 text-center">
            <span aria-hidden="true" class="material-symbols-outlined text-6xl text-gray-300 mb-4">badge</span>
            <p class="text-gray-500 font-medium">Nenhum cargo institucionais criado.</p>
            <p class="text-sm text-gray-400 mt-1">Crie cargos para atribuir permissões limitadas a membros da instituição.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($roles as $role)
                <div
                    class="bg-white rounded-2xl border-2 border-duo-border p-6 hover:border-primary hover:shadow-md transition-all group">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="size-10 rounded-full flex items-center justify-center {{ $role->is_active ? 'bg-primary/10' : 'bg-gray-100' }}">
                                <span aria-hidden="true"
                                    class="material-symbols-outlined {{ $role->is_active ? 'text-primary' : 'text-gray-400' }}">badge</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">{{ $role->name }}</h3>
                                <span class="text-xs {{ $role->is_active ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $role->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('institution.roles.edit', $role) }}"
                            class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-primary">
                            <span aria-hidden="true" class="material-symbols-outlined">edit</span>
                        </a>
                    </div>

                    @if ($role->description)
                        <p class="text-sm text-gray-500 mb-3">{{ $role->description }}</p>
                    @endif

                    <div class="flex items-center justify-between text-xs text-gray-400 border-t border-duo-border pt-3">
                        <span>{{ $role->permissions_count ?? $role->permissions->count() }} permissões</span>
                        <span>{{ $role->members_count }} membros</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>