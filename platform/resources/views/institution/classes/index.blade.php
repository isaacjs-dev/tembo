<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Turmas</span>
                </nav>
                <h1 class="page-title">Turmas</h1>
            </div>
            <a href="{{ route('institution.classes.create') }}" class="btn-primary">
                <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                Nova Turma
            </a>
        </div>
    </x-slot>

    <div class="table-wrapper mb-12">
        <div class="overflow-x-auto">
            <table>
                <caption class="sr-only">Turmas cadastradas na instituição</caption>
                <thead>
                    <tr>
                        <th>Nome da Turma</th>
                        <th>Ano</th>
                        <th>Alunos</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schoolClasses as $schoolClass)
                        <tr>
                            <td class="whitespace-nowrap font-bold text-duo-heading">
                                {{ $schoolClass->name }}
                            </td>
                            <td class="text-gray-600 font-medium">{{ $schoolClass->year ?? '—' }}</td>
                            <td>
                                <span class="badge badge-warning">
                                    {{ $schoolClass->students_count }} alunos
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('institution.classes.edit', $schoolClass->id) }}"
                                    class="btn-icon" title="Editar">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">edit</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-5xl text-gray-300 block mb-3">groups</span>
                                <h2 class="text-lg font-bold text-duo-heading">Nenhuma turma encontrada.</h2>
                                <p class="text-gray-400 mt-1">Clique em "Nova Turma" para começar.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($schoolClasses->hasPages())
            <div class="border-t-2 border-duo-border px-6 py-4">
                {{ $schoolClasses->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
