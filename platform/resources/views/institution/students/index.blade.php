<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Alunos</span>
                </nav>
                <h1 class="page-title">Alunos</h1>
            </div>
            <div class="flex items-center gap-3">
                <button class="btn-secondary">
                    <span aria-hidden="true" class="material-symbols-outlined">upload_file</span>
                    Importar CSV
                </button>
                <a href="{{ route('institution.students.create') }}" class="btn-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                    Novo Aluno
                </a>
            </div>
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
                <caption class="sr-only">Alunos cadastrados na instituição</caption>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Contato</th>
                        <th>Matrícula</th>
                        <th>Turmas</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php($membershipStatus = $student->organizationMembershipStatus((int) auth()->user()->organization_id))
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-xl border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                        {{ substr($student->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-duo-heading">{{ $student->name }}</div>
                                        <div class="text-xs text-gray-400">ID: {{ $student->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-gray-600 font-medium">{{ $student->email }}</td>
                            <td class="font-medium text-gray-600">
                                {{ $student->studentProfiles->first()?->registration_number ?? '—' }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse($student->schoolClasses as $class)
                                        <span class="badge badge-neutral">{{ $class->name }}</span>
                                    @empty
                                        <span class="text-xs text-gray-400">Nenhuma</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $membershipStatus === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $membershipStatus === 'active' ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('institution.students.edit', $student->id) }}"
                                        class="btn-icon" title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-5xl text-gray-300 block mb-3">school</span>
                                <h3 class="text-lg font-bold text-duo-heading">Nenhum aluno encontrado.</h3>
                                <p class="text-gray-400 mt-1">Clique em "Novo Aluno" ou "Importar CSV" para começar.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
            <div class="border-t-2 border-duo-border px-6 py-4">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
