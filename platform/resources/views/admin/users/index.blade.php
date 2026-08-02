<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Usuários</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Gestão Global de Usuários</h1>
                <p class="text-gray-400 mt-1">Gerencie todos os usuários cadastrados na plataforma através do painel principal.</p>
            </div>
            <a href="{{ route('admin.users.create') }}"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-extrabold text-white hover:bg-primary-dark">
                <span class="material-symbols-outlined" aria-hidden="true">person_add</span>
                Novo usuário
            </a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="flex items-center gap-3 bg-green-50 border-2 border-green-200 text-green-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm"
            role="status" aria-live="polite">
            <span aria-hidden="true" class="material-symbols-outlined text-green-500">check_circle</span>
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-3 bg-red-50 border-2 border-red-200 text-red-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm"
            role="alert">
            <span aria-hidden="true" class="material-symbols-outlined text-red-500">error</span>
            {{ session('error') }}
        </div>
    @endif

    {{-- Search Bar --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex flex-wrap gap-4">
        <label for="admin-user-search" class="sr-only">Buscar usuário por nome ou e-mail</label>
        <input id="admin-user-search" type="search" name="search" value="{{ $search }}" placeholder="Buscar por nome ou email..."
            class="flex-1 px-4 py-3 border-2 border-duo-border rounded-xl focus:border-primary focus:ring-0 outline-none text-gray-700 font-medium">
        <button type="submit" class="bg-primary text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wider duo-button-shadow hover:-translate-y-0.5 active:translate-y-0 transition-all">
            Buscar
        </button>
        @if($search)
            <a href="{{ route('admin.users.index') }}" class="bg-gray-100 text-gray-500 border border-gray-200 px-6 py-3 rounded-xl font-bold uppercase tracking-wider hover:bg-gray-200 transition-all flex items-center justify-center">
                Limpar
            </a>
        @endif
    </form>

    {{-- Table Card --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-12">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <caption class="sr-only">Usuários cadastrados na plataforma</caption>
                <thead class="bg-background-light border-b-2 border-duo-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Usuário</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Perfil</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Organização</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-duo-border">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-400 font-medium">{{ $user->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-xl border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">person</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $roleMap = [
                                        'global_admin'      => ['label' => 'SaaS Admin', 'class' => 'bg-yellow-100 text-yellow-700 border-yellow-200'],
                                        'institution_admin' => ['label' => 'Admin. Inst.', 'class' => 'bg-accent/15 text-secondary-dark border-accent/25'],
                                        'teacher'           => ['label' => 'Professor', 'class' => 'bg-blue-100 text-blue-700 border-blue-200'],
                                        'student'           => ['label' => 'Aluno', 'class' => 'bg-gray-100 text-gray-700 border-gray-200'],
                                        'guardian'          => ['label' => 'Responsável', 'class' => 'bg-green-100 text-green-700 border-green-200'],
                                    ];
                                    $role = $roleMap[$user->type] ?? ['label' => $user->type, 'class' => 'bg-gray-100 text-gray-700 border-gray-200'];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-tight border {{ $role['class'] }}">
                                    {{ $role['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($user->organization)
                                    <span class="text-sm font-bold text-gray-900">{{ $user->organization->name }}</span>
                                @else
                                    <span class="text-gray-400 italic text-sm">Sem organização</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                    @if(auth()->id() !== $user->id)
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Deseja realmente apagar este usuário?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Excluir">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-[48px] text-gray-300 mb-3">inbox</span>
                                <h2 class="text-xl font-bold text-gray-800">Nenhum usuário encontrado.</h2>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4">
        {{ $users->links() }}
    </div>
</x-app-layout>
