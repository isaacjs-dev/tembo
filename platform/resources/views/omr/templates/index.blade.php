<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Templates</span>
                </nav>
                <h1 class="page-title">Templates OMR</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('institution.omr.templates.create') }}"
                    class="btn-primary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">add</span>
                    Novo Template
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <div class="table-wrapper mb-12">
        <div class="overflow-x-auto">
            <table>
                <caption class="sr-only">Templates de cartão OMR</caption>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Papel</th>
                        <th>Questões</th>
                        <th>Colunas</th>
                        <th>Opções</th>
                        <th>Status</th>
                        <th>Atualizado</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td>
                                <p class="font-bold text-duo-heading">{{ $tpl->name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ $tpl->slug }}</p>
                            </td>
                            <td class="text-sm">{{ $tpl->paper_size }}
                                {{ $tpl->orientation === 'portrait' ? 'Retrato' : 'Paisagem' }}</td>
                            <td class="font-bold text-primary">{{ $tpl->total_questions }}</td>
                            <td class="text-sm text-gray-600">{{ $tpl->columns }} col × {{ $tpl->rows_per_column }} lin</td>
                            <td class="text-sm text-gray-600">até {{ $tpl->max_options }}</td>
                            <td>
                                @if($tpl->is_active)
                                    <span class="badge badge-success">Ativo</span>
                                @else
                                    <span class="badge badge-neutral">Inativo</span>
                                @endif
                                @if(!$tpl->organization_id)
                                    <span class="badge badge-info ml-1">Global</span>
                                @endif
                            </td>
                            <td class="text-xs text-gray-500">{{ $tpl->updated_at->format('d/m/Y H:i') }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('institution.omr.templates.export', $tpl->id) }}" class="btn-icon"
                                        title="Exportar JSON" target="_blank">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">download</span>
                                    </a>
                                    <a href="{{ route('institution.omr.templates.edit', $tpl->id) }}" class="btn-icon"
                                        title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    @if($tpl->organization_id)
                                        <form action="{{ route('institution.omr.templates.destroy', $tpl->id) }}" method="POST"
                                            onsubmit="return confirm('Excluir template \'{{ $tpl->name }}\'? Esta ação não pode ser desfeita.')">
                                            @csrf @method('DELETE')
                                            <button class="btn-icon text-red-500" title="Excluir">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <span aria-hidden="true"
                                    class="material-symbols-outlined text-4xl text-gray-300 block mb-2">dashboard_customize</span>
                                <p class="font-bold text-gray-500">Nenhum template cadastrado.</p>
                                <p class="text-sm text-gray-400 mt-1">Crie um template para configurar o layout do
                                    cartão-resposta.</p>
                                <a href="{{ route('institution.omr.templates.create') }}"
                                    class="btn-primary btn-sm mt-4 inline-flex">
                                    Criar Template
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="p-6 border-t-2 border-duo-border">{{ $templates->links() }}</div>
        @endif
    </div>
</x-app-layout>
