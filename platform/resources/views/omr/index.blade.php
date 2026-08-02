<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Leitura OMR</span>
                </nav>
                <h1 class="page-title">Leitura OMR (Gabaritos)</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('institution.omr.reports') }}" class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">bar_chart</span>
                    Relatórios
                </a>
                <a href="{{ route('institution.omr.templates.index') }}" class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">dashboard_customize</span>
                    Templates
                </a>
                <a href="{{ route('institution.omr.webscan') }}" class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">photo_camera</span>
                    Web Scan
                </a>
                <a href="{{ route('institution.omr.create') }}" class="btn-primary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">upload_file</span>
                    Enviar Gabarito
                </a>
            </div>
        </div>
    </x-slot>

    {{-- KPI Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Total de Leituras</p>
                    <p class="stat-card-value">{{ $totalCount }}</p>
                </div>
                <div class="stat-card-icon bg-primary/10 text-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">analytics</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-gray-400">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">history</span>
                Histórico total
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Aguardando Revisão</p>
                    <p class="stat-card-value text-amber-500">{{ $reviewCount }}</p>
                </div>
                <div class="stat-card-icon bg-amber-50 text-amber-500">
                    <span aria-hidden="true" class="material-symbols-outlined">rate_review</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-amber-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">warning</span>
                Requer atenção manual
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Pendentes</p>
                    <p class="stat-card-value text-yellow-500">{{ $pendingCount }}</p>
                </div>
                <div class="stat-card-icon bg-yellow-50 text-yellow-500">
                    <span aria-hidden="true" class="material-symbols-outlined">hourglass_empty</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-yellow-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">info</span>
                Processamento em fila
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Confirmados</p>
                    <p class="stat-card-value text-green-500">{{ $confirmedCount }}</p>
                </div>
                <div class="stat-card-icon bg-green-50 text-green-500">
                    <span aria-hidden="true" class="material-symbols-outlined">check_circle</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-green-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">verified</span>
                Notas já atribuídas
            </div>
        </div>
    </div>

    {{-- Filtros e contadores --}}
    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('institution.omr.index') }}"
                class="btn-sm {{ !request('status') ? 'btn-primary' : 'btn-secondary' }}">
                Todos
            </a>
            <a href="{{ route('institution.omr.index', ['status' => 'reviewing']) }}"
                class="btn-sm {{ request('status') === 'reviewing' ? 'btn-primary' : 'btn-secondary' }}">
                Em Revisão
            </a>
            <a href="{{ route('institution.omr.index', ['status' => 'pending']) }}"
                class="btn-sm {{ request('status') === 'pending' ? 'btn-primary' : 'btn-secondary' }}">
                Pendentes
            </a>
            <a href="{{ route('institution.omr.index', ['status' => 'confirmed']) }}"
                class="btn-sm {{ request('status') === 'confirmed' ? 'btn-primary' : 'btn-secondary' }}">
                Confirmados
            </a>
        </div>

        <form action="{{ route('institution.omr.index') }}" method="GET" class="flex items-center gap-2">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <label for="omr-exam-filter" class="sr-only">Filtrar leituras por avaliação</label>
            <select id="omr-exam-filter" name="exam_id" class="input-field !py-2 !px-3 !text-xs min-w-[200px]" onchange="this.form.submit()">
                <option value="">Todas as Avaliações</option>
                @foreach($exams as $exam)
                    <option value="{{ $exam->id }}" {{ request('exam_id') == $exam->id ? 'selected' : '' }}>
                        {{ $exam->title }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <form action="{{ route('institution.omr.batchUpdate') }}" method="POST" id="batch-form" x-data="{
        selectedCount: 0,
        toggleAll(e) {
            document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = e.target.checked);
            this.updateCount();
        },
        updateCount() {
            this.selectedCount = document.querySelectorAll('.batch-checkbox:checked').length;
        }
    }">
        @csrf

        {{-- Barra de Seleção em Lote --}}
        <div class="flex items-center justify-between mb-3">
            <label class="flex items-center gap-2 text-sm text-gray-600 font-medium cursor-pointer">
                <input type="checkbox" class="form-checkbox h-4 w-4 text-primary rounded" @change="toggleAll($event)">
                Selecionar Todos
            </label>
            <div x-show="selectedCount > 0" x-cloak class="flex items-center gap-2">
                <span class="text-sm font-medium mr-2 text-gray-700">
                    <span x-text="selectedCount"></span> selecionado(s)
                </span>
                <button type="submit" name="action" value="confirm" class="btn-sm btn-success"
                    onclick="return confirm('Deseja aprovar em lote os selecionados?')">
                    Aprovar em Lote
                </button>
                <button type="submit" name="action" value="reject" class="btn-sm btn-warning"
                    onclick="return confirm('Deseja rejeitar os gabaritos selecionados?')">
                    Rejeitar
                </button>
                <button type="submit" name="action" value="delete" class="btn-sm btn-danger"
                    onclick="return confirm('ATENÇÃO! Excluir definitivamente os selecionados?')">
                    Excluir
                </button>
            </div>
        </div>

        <div class="table-wrapper mb-12">
            <div class="overflow-x-auto">
                <table>
                    <caption class="sr-only">Leituras de gabaritos enviadas para conferência</caption>
                    <thead>
                        <tr>
                            <th class="w-10"><span class="sr-only">Selecionar</span></th>
                            <th>Avaliação</th>
                            <th>Aluno</th>
                            <th>Status</th>
                            <th>Confiança</th>
                            <th>Enviado por</th>
                            <th>Data</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scans as $scan)
                            <tr>
                                <td>
                                    <input type="checkbox" name="scan_ids[]" value="{{ $scan->id }}"
                                        aria-label="Selecionar leitura da avaliação {{ $scan->exam->title ?? 'sem título' }}"
                                        class="batch-checkbox form-checkbox h-4 w-4 text-primary rounded"
                                        @change="updateCount()">
                                </td>
                                <td class="font-bold text-duo-heading">{{ $scan->exam->title ?? 'N/A' }}</td>
                                <td>{{ $scan->student->name ?? '—' }}</td>
                                <td>
                                    @switch($scan->status)
                                        @case('pending') <span class="badge badge-neutral">Pendente</span> @break
                                        @case('reviewing') <span class="badge badge-warning">Revisão</span> @break
                                        @case('confirmed') <span class="badge badge-success">Confirmado</span> @break
                                        @case('synced') <span class="badge badge-info">Sincronizado</span> @break
                                        @case('rejected') <span class="badge badge-danger">Rejeitado</span> @break
                                        @default <span class="badge badge-info">{{ $scan->status }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @php
                                        $conf = $scan->overall_confidence ?? $scan->confidence_score;
                                    @endphp
                                    @if($conf !== null)
                                        <span class="font-bold {{ $conf >= 0.8 ? 'text-primary' : 'text-amber-500' }}">
                                            {{ number_format($conf * 100, 0) }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-gray-500">{{ $scan->uploader->name ?? '—' }}</td>
                                <td class="text-xs text-gray-500">{{ $scan->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-right">
                                    @if(in_array($scan->status, ['pending', 'reviewing', 'synced']))
                                        <a href="{{ route('institution.omr.review', $scan->id) }}"
                                            class="btn-icon" title="Revisar">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">rate_review</span>
                                        </a>
                                    @elseif($scan->status === 'confirmed')
                                        <a href="{{ route('institution.omr.review', $scan->id) }}"
                                            class="btn-icon" title="Ver Detalhes">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">visibility</span>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-12">
                                    <span aria-hidden="true" class="material-symbols-outlined text-4xl text-gray-300 block mb-2">document_scanner</span>
                                    <p class="font-bold text-gray-500">Nenhum gabarito encontrado.</p>
                                    <p class="text-sm text-gray-400 mt-1">Envie um gabarito escaneado para começar.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($scans->hasPages())
                <div class="p-6 border-t-2 border-duo-border">{{ $scans->links() }}</div>
            @endif
        </div>
    </form>
</x-app-layout>
