<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">Leitura OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Relatórios de Escaneamento</span>
                </nav>
                <h1 class="page-title">Relatórios OMR (Saúde da Captura)</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('institution.omr.index') }}" class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Voltar para Lista
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Filtros -->
    <div class="card mb-6">
        <form method="GET" action="{{ route('institution.omr.reports') }}" class="flex items-end gap-4 p-4">
            <div class="w-1/3">
                <label class="form-label">Filtrar por Avaliação</label>
                <select name="exam_id" class="input-field">
                    <option value="">Todas as Avaliações</option>
                    @foreach($exams as $exam)
                        <option value="{{ $exam->id }}" {{ request('exam_id') == $exam->id ? 'selected' : '' }}>
                            {{ $exam->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary">Filtrar</button>
                @if(request()->hasAny(['exam_id']))
                    <a href="{{ route('institution.omr.reports') }}" class="btn-secondary ml-2">Limpar</a>
                @endif
            </div>
        </form>
    <!-- Estatísticas Gerais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Total Digitalizado</p>
                    <p class="stat-card-value">{{ $totalScans }}</p>
                </div>
                <div class="stat-card-icon bg-primary/10 text-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">document_scanner</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-gray-400">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">history</span>
                Volume total de capturas
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Aprovados</p>
                    <p class="stat-card-value text-green-500">{{ $confirmedScans + $syncedScans }}</p>
                </div>
                <div class="stat-card-icon bg-green-50 text-green-500">
                    <span aria-hidden="true" class="material-symbols-outlined">verified</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-green-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">check_circle</span>
                Sincronizados com o acadêmico
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Taxa de Intervenção</p>
                    <p class="stat-card-value text-amber-500">
                        @if($totalScans > 0)
                            {{ number_format(($reviewingScans / $totalScans) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </p>
                </div>
                <div class="stat-card-icon bg-amber-50 text-amber-500">
                    <span aria-hidden="true" class="material-symbols-outlined">psychology</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-amber-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">warning</span>
                {{ $reviewingScans }} aguardando revisão
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="stat-card-label">Rejeitados</p>
                    <p class="stat-card-value text-red-500">{{ $rejectedScans }}</p>
                </div>
                <div class="stat-card-icon bg-red-50 text-red-500">
                    <span aria-hidden="true" class="material-symbols-outlined">cancel</span>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-red-600/60">
                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">delete</span>
                Falhas críticas de leitura
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
        <!-- Nível de Confiança -->
        <div class="lg:col-span-2 card p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="text-xl font-extrabold text-duo-heading flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-primary text-[28px]">analytics</span>
                        Distribuição de Confiança
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Nível de segurança do processamento AI em cada leitura.</p>
                </div>
            </div>

            <div class="space-y-6">
                @foreach($confidenceDistribution as $label => $count)
                    @php
                        $percentage = $totalScans > 0 ? ($count / $totalScans) * 100 : 0;
                        $colorClass = match($label) {
                            'Alto (>= 90%)' => 'bg-green-500',
                            'Médio (70% - 89%)' => 'bg-yellow-500',
                            'Baixo (< 70%)' => 'bg-red-500',
                            default => 'bg-gray-400'
                        };
                        $icon = match($label) {
                            'Alto (>= 90%)' => 'verified',
                            'Médio (70% - 89%)' => 'info',
                            'Baixo (< 70%)' => 'report_problem',
                            default => 'help'
                        };
                    @endphp
                    <div>
                        <div class="flex justify-between items-end mb-2">
                            <div class="flex items-center gap-2">
                                <span aria-hidden="true" class="material-symbols-outlined text-[18px] {{ str_replace('bg-', 'text-', $colorClass) }}">{{ $icon }}</span>
                                <span class="font-bold text-duo-heading text-sm">{{ $label }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-black text-duo-heading">{{ $count }}</span>
                                <span class="text-xs text-gray-400 ml-1">({{ number_format($percentage, 1) }}%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden border border-duo-border/50">
                            <div class="{{ $colorClass }} h-full rounded-full shadow-[0_0_10px_rgba(0,0,0,0.1)] transition-all duration-1000" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Dicas de Qualidade -->
        <div class="card p-8 bg-primary/5 border-primary/20">
            <h3 class="text-lg font-extrabold text-primary flex items-center gap-2 mb-6">
                <span aria-hidden="true" class="material-symbols-outlined">lightbulb</span>
                Dicas de Qualidade
            </h3>
            <ul class="space-y-4">
                <li class="flex gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-[20px] shrink-0">light_mode</span>
                    <p class="text-sm text-gray-600 font-medium leading-relaxed">
                        <strong class="text-duo-heading">Iluminação:</strong> Evite sombras sobre o papel. Luz natural ou branca uniforme produz melhores resultados.
                    </p>
                </li>
                <li class="flex gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-[20px] shrink-0">fit_screen</span>
                    <p class="text-sm text-gray-600 font-medium leading-relaxed">
                        <strong class="text-duo-heading">Enquadramento:</strong> Mantenha os 4 marcadores de canto visíveis e o papel o mais plano possível.
                    </p>
                </li>
                <li class="flex gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-[20px] shrink-0">edit</span>
                    <p class="text-sm text-gray-600 font-medium leading-relaxed">
                        <strong class="text-duo-heading">Preenchimento:</strong> Oriente os alunos a preencherem as bolhas completamente com caneta preta ou azul escura.
                    </p>
                </li>
                <li class="flex gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-[20px] shrink-0">print</span>
                    <p class="text-sm text-gray-600 font-medium leading-relaxed">
                        <strong class="text-duo-heading">Impressão:</strong> Use impressoras a laser com boa tonalidade para garantir que os marcadores e QR Codes fiquem nítidos.
                    </p>
                </li>
            </ul>
            
            <div class="mt-8 pt-6 border-t-2 border-primary/10">
                <p class="text-xs text-primary/70 font-bold uppercase tracking-widest text-center">
                    Confiança média da instituição
                </p>
                <p class="text-4xl font-black text-primary text-center mt-2">
                    @php
                        $totalConfidence = \App\Models\OmrScan::where('organization_id', auth()->user()->organization_id)->whereNotNull('confidence_score')->avg('confidence_score');
                    @endphp
                    {{ number_format(($totalConfidence ?? 0) * 100, 1) }}%
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
