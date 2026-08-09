<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Professores</span>
                </nav>
                <h1 class="page-title">Professores</h1>
            </div>
            <a href="{{ route('institution.teachers.create') }}" class="btn-primary">
                <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                Novo Professor
            </a>
        </div>
    </x-slot>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <div class="flex items-center gap-2 bg-white border-2 border-duo-border px-4 py-2 rounded-xl text-sm font-bold text-gray-500">
            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">filter_list</span>
            Filtros:
        </div>
        <button class="px-4 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-bold text-gray-500 hover:border-primary transition-all">
            Status do vínculo: todos
        </button>
        <button class="px-4 py-2 text-sm font-bold text-red-400 hover:text-red-500">Limpar filtros</button>
    </div>

    <!-- Table -->
    <div class="table-wrapper mb-12">
        <div class="overflow-x-auto">
            <table>
                <caption class="sr-only">Professores vinculados à instituição</caption>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $teacher)
                        @php($membershipStatus = $teacher->organizationMembershipStatus((int) auth()->user()->organization_id))
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-xl border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                        {{ substr($teacher->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-duo-heading">{{ $teacher->name }}</div>
                                        <div class="text-xs text-gray-400">ID: {{ $teacher->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-gray-600 font-medium">{{ $teacher->email }}</td>
                            <td><span class="badge badge-info">Professor</span></td>
                            <td>
                                <span class="badge {{ $membershipStatus === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $membershipStatus === 'active' ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('institution.teachers.edit', $teacher->id) }}"
                                        class="btn-icon" title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                    <a href="{{ route('institution.teachers.permissions', $teacher->id) }}"
                                        class="p-2.5 rounded-xl transition-all duration-150 text-gray-400 hover:text-blue-500 hover:bg-blue-50"
                                        title="Permissões">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">shield</span>
                                    </a>
                                    <form action="{{ route('institution.teachers.destroy', $teacher->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Alternar status deste professor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2.5 rounded-xl transition-all duration-150 {{ $membershipStatus === 'active' ? 'text-gray-400 hover:text-red-500 hover:bg-red-50' : 'text-red-400 hover:text-green-600 hover:bg-green-50' }}"
                                            title="{{ $membershipStatus === 'active' ? 'Desativar vínculo' : 'Reativar vínculo' }}">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">
                                                {{ $membershipStatus === 'active' ? 'toggle_on' : 'toggle_off' }}
                                            </span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-5xl text-gray-300 block mb-3">person_add</span>
                                <h3 class="text-lg font-bold text-duo-heading">Nenhum professor encontrado.</h3>
                                <p class="text-gray-400 mt-1">Clique em "Novo Professor" para adicionar o primeiro.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($teachers->hasPages())
            <div class="border-t-2 border-duo-border px-6 py-4">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
