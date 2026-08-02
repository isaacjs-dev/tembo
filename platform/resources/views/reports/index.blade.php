<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb" aria-label="Navegação estrutural">
                    <a href="{{ route('institution.dashboard') }}">Painel</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Relatórios</span>
                </nav>
                <h1 class="page-title">Desempenho e aprendizagem</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Compare avaliações de escalas diferentes por percentual e localize habilidades, questões e
                    estudantes que precisam de intervenção.
                </p>
            </div>
        </div>
    </x-slot>

    <form method="GET" action="{{ route('institution.reports') }}" class="card mb-8 p-6">
        <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="report_exam_id" class="input-label">Avaliação</label>
                <select id="report_exam_id" name="exam_id" class="input-field">
                    <option value="">Todas</option>
                    @foreach($exams as $exam)
                        <option value="{{ $exam->id }}" @selected($selectedExamId == $exam->id)>
                            {{ $exam->title }} ({{ $exam->submissions_count }} entregas)
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="report_class_id" class="input-label">Turma</label>
                <select id="report_class_id" name="class_id" class="input-field">
                    <option value="">Todas</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClassId == $class->id)>
                            {{ $class->name }} ({{ $class->year }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="report_date_from" class="input-label">De</label>
                <input id="report_date_from" type="date" name="date_from" value="{{ $dateFrom }}" class="input-field">
            </div>

            <div>
                <label for="report_date_to" class="input-label">Até</label>
                <input id="report_date_to" type="date" name="date_to" value="{{ $dateTo }}" class="input-field">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">filter_list</span>
                    Filtrar
                </button>
                <a href="{{ route('institution.reports') }}"
                    class="btn-ghost btn-sm text-xs text-red-700 hover:text-red-800">Limpar</a>
            </div>
        </div>
    </form>

    <section aria-label="Indicadores gerais"
        class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-8">
        @php
            $kpis = [
                ['label' => 'Entregas', 'value' => $stats['total_submissions'], 'icon' => 'assignment_turned_in', 'tone' => 'bg-blue-50 text-blue-700'],
                ['label' => 'Corrigidas', 'value' => $stats['graded'], 'icon' => 'check_circle', 'tone' => 'bg-green-50 text-green-700'],
                ['label' => 'Pendentes', 'value' => $stats['pending'], 'icon' => 'pending', 'tone' => 'bg-yellow-50 text-yellow-800'],
                ['label' => 'Em andamento', 'value' => $stats['in_progress'], 'icon' => 'edit_note', 'tone' => 'bg-accent-light text-accent'],
                ['label' => 'Média', 'value' => $stats['average'].'%', 'icon' => 'trending_up', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => 'Mediana', 'value' => $stats['median'].'%', 'icon' => 'align_vertical_center', 'tone' => 'bg-accent-light text-secondary-dark'],
                ['label' => 'Mínima', 'value' => $stats['min'].'%', 'icon' => 'arrow_downward', 'tone' => 'bg-red-50 text-red-700'],
                ['label' => 'Máxima', 'value' => $stats['max'].'%', 'icon' => 'arrow_upward', 'tone' => 'bg-cyan-50 text-cyan-800'],
            ];
        @endphp

        @foreach($kpis as $kpi)
            <article class="stat-card">
                <div class="stat-card-icon {{ $kpi['tone'] }}">
                    <span aria-hidden="true" class="material-symbols-outlined">{{ $kpi['icon'] }}</span>
                </div>
                <p class="stat-card-label mt-3">{{ $kpi['label'] }}</p>
                <p class="stat-card-value">{{ $kpi['value'] }}</p>
            </article>
        @endforeach
    </section>

    <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="card p-6" aria-labelledby="distribution-heading">
            <h2 id="distribution-heading" class="mb-4 flex items-center gap-2 text-lg font-extrabold">
                <span aria-hidden="true" class="material-symbols-outlined text-secondary">bar_chart</span>
                Distribuição de desempenho
            </h2>
            <div class="h-64">
                <canvas id="scoreDistributionChart" role="img"
                    aria-label="Gráfico da distribuição percentual das notas"></canvas>
            </div>
        </section>

        <section class="card p-6" aria-labelledby="exam-performance-heading">
            <h2 id="exam-performance-heading" class="mb-4 flex items-center gap-2 text-lg font-extrabold">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">leaderboard</span>
                Média por avaliação
            </h2>
            <div id="examPerformanceChartWrapper" class="h-64">
                <canvas id="examPerformanceChart" role="img"
                    aria-label="Gráfico da média percentual por avaliação"></canvas>
            </div>
        </section>

        <section class="card p-6" aria-labelledby="trend-heading">
            <h2 id="trend-heading" class="mb-4 flex items-center gap-2 text-lg font-extrabold">
                <span aria-hidden="true" class="material-symbols-outlined text-accent">show_chart</span>
                Evolução no período
            </h2>
            <div id="performanceTrendChartWrapper" class="h-64">
                <canvas id="performanceTrendChart" role="img"
                    aria-label="Gráfico da evolução da média percentual por dia"></canvas>
            </div>
        </section>
    </div>

    @if($activeSubmissions->isNotEmpty())
        <section class="card mb-8 p-6" aria-labelledby="live-progress-heading">
            <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div>
                    <h2 id="live-progress-heading" class="flex items-center gap-2 text-lg font-extrabold">
                        <span aria-hidden="true" class="relative flex size-3">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-500 opacity-60"></span>
                            <span class="relative inline-flex size-3 rounded-full bg-green-700"></span>
                        </span>
                        Aplicações em andamento
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Atualize a página para ver os salvamentos mais recentes.</p>
                </div>
                <span class="badge badge-success">{{ $activeSubmissions->count() }} ativas</span>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($activeSubmissions as $active)
                    <article class="rounded-xl border-2 border-duo-border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-extrabold">{{ $active->user?->name ?? 'Estudante' }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $active->exam?->title ?? 'Avaliação' }}</p>
                            </div>
                            <span class="text-sm font-extrabold text-secondary">{{ $active->progress }}%</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200" role="progressbar"
                            aria-label="Progresso de {{ $active->user?->name ?? 'estudante' }}"
                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $active->progress }}">
                            <div class="h-full rounded-full bg-secondary" style="width: {{ $active->progress }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ $active->answered }} de {{ $active->total }} respondidas ·
                            salvo {{ $active->updated_at?->diffForHumans() }}
                        </p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="card p-6" aria-labelledby="skills-heading">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 id="skills-heading" class="text-lg font-extrabold">Domínio por habilidade</h2>
                    <p class="mt-1 text-sm text-gray-600">As menores taxas aparecem primeiro.</p>
                </div>
                <span class="badge badge-neutral">{{ $skillPerformance->count() }} habilidades</span>
            </div>

            <div class="space-y-4">
                @forelse($skillPerformance->take(8) as $skill)
                    <div>
                        <div class="mb-1.5 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-duo-heading">{{ $skill->label }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $skill->source }} · {{ $skill->responses }} respostas
                                </p>
                            </div>
                            <span class="font-extrabold {{ $skill->mastery < 60 ? 'text-red-700' : 'text-primary' }}">
                                {{ number_format($skill->mastery ?? 0, 1) }}%
                            </span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-gray-200" role="progressbar"
                            aria-label="Domínio em {{ $skill->label }}" aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="{{ round($skill->mastery ?? 0) }}">
                            <div class="h-full rounded-full {{ $skill->mastery < 60 ? 'bg-red-600' : 'bg-primary' }}"
                                style="width: {{ min(100, max(0, $skill->mastery ?? 0)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-600">
                        Vincule habilidades às questões e corrija avaliações para formar este painel.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="card p-6" aria-labelledby="disciplines-heading">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 id="disciplines-heading" class="text-lg font-extrabold">Desempenho por disciplina</h2>
                    <p class="mt-1 text-sm text-gray-600">Percentual dos pontos obtidos em cada componente.</p>
                </div>
                <span class="badge badge-neutral">{{ $disciplinePerformance->count() }}</span>
            </div>
            <div class="space-y-4">
                @forelse($disciplinePerformance as $discipline)
                    <div>
                        <div class="mb-1.5 flex justify-between gap-4 text-sm">
                            <span class="font-bold">{{ $discipline->label }}</span>
                            <span class="font-extrabold {{ $discipline->mastery < 60 ? 'text-red-700' : 'text-primary' }}">
                                {{ number_format($discipline->mastery ?? 0, 1) }}%
                            </span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-gray-200" role="progressbar"
                            aria-label="Desempenho em {{ $discipline->label }}" aria-valuemin="0"
                            aria-valuemax="100" aria-valuenow="{{ round($discipline->mastery ?? 0) }}">
                            <div class="h-full rounded-full {{ $discipline->mastery < 60 ? 'bg-red-600' : 'bg-primary' }}"
                                style="width: {{ min(100, max(0, $discipline->mastery ?? 0)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $discipline->responses }} respostas avaliadas</p>
                    </div>
                @empty
                    <p class="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-600">
                        Vincule disciplinas às questões para habilitar este recorte.
                    </p>
                @endforelse
            </div>
        </section>

        <section class="card p-6" aria-labelledby="support-heading">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 id="support-heading" class="text-lg font-extrabold">Prioridade de acompanhamento</h2>
                    <p class="mt-1 text-sm text-gray-600">Estudantes com média inferior a 60% nos filtros atuais.</p>
                </div>
                <span class="badge badge-warning">{{ $atRiskStudents->count() }} estudantes</span>
            </div>

            <div class="space-y-3">
                @forelse($atRiskStudents as $student)
                    <div class="flex items-center justify-between gap-4 rounded-xl border-2 border-duo-border p-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold">{{ $student->user?->name ?? 'Estudante' }}</p>
                            <p class="text-xs text-gray-500">{{ $student->assessments }} avaliação(ões) corrigida(s)</p>
                        </div>
                        <span class="badge badge-danger">{{ number_format($student->average, 1) }}%</span>
                    </div>
                @empty
                    <div class="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-600">
                        Nenhum estudante abaixo do corte nos filtros selecionados.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="table-wrapper mb-8" aria-labelledby="questions-analysis-heading">
        <div class="flex items-start justify-between gap-4 border-b-2 border-duo-border p-6">
            <div>
                <h2 id="questions-analysis-heading" class="text-lg font-extrabold">Análise por questão</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Itens com mais erros aparecem primeiro; considere o tamanho da amostra antes de revisar o enunciado.
                </p>
            </div>
            <span class="badge badge-neutral">{{ $questionPerformance->count() }} itens</span>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Questão</th>
                        <th scope="col">Disciplina</th>
                        <th scope="col">Respostas</th>
                        <th scope="col">Acerto</th>
                        <th scope="col">Domínio</th>
                        <th scope="col">Sinal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($questionPerformance as $item)
                        <tr>
                            <td>
                                <p class="max-w-xl font-bold">
                                    {{ \Illuminate\Support\Str::limit($item->statement, 120) }}
                                </p>
                            </td>
                            <td class="text-sm text-gray-600">{{ $item->discipline }}</td>
                            <td class="font-bold">{{ $item->responses }}</td>
                            <td class="font-extrabold">
                                {{ $item->accuracy === null ? '—' : number_format($item->accuracy, 1).'%' }}
                            </td>
                            <td class="font-extrabold">
                                {{ $item->mastery === null ? '—' : number_format($item->mastery, 1).'%' }}
                            </td>
                            <td>
                                @if($item->needs_attention)
                                    <span class="badge badge-danger">Revisar</span>
                                @elseif($item->evaluated < 3)
                                    <span class="badge badge-neutral">Amostra pequena</span>
                                @else
                                    <span class="badge badge-success">Estável</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-gray-600">
                                Ainda não há respostas corrigidas para analisar por item.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-wrapper mb-12" aria-labelledby="students-detail-heading">
        <div class="flex items-center justify-between border-b-2 border-duo-border p-6">
            <h2 id="students-detail-heading" class="flex items-center gap-2 text-lg font-extrabold">
                <span aria-hidden="true" class="material-symbols-outlined text-gray-500">list_alt</span>
                Detalhamento por aluno
            </h2>
            <span class="badge badge-neutral">{{ $submissions->count() }} registros</span>
        </div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Aluno</th>
                        <th scope="col">Avaliação</th>
                        <th scope="col">Desempenho</th>
                        <th scope="col">Status</th>
                        <th scope="col">Data</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $submission)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div aria-hidden="true"
                                        class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">
                                        {{ substr($submission->user->name ?? '?', 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold">{{ $submission->user->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-gray-500">{{ $submission->user->email ?? '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="font-medium">{{ $submission->exam->title ?? 'N/A' }}</td>
                            <td>
                                @if($submission->status === 'graded')
                                    <span class="text-lg font-extrabold {{ $submission->score_percent >= 60 ? 'text-primary' : 'text-red-700' }}">
                                        {{ number_format($submission->score_percent, 1) }}%
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        {{ number_format($submission->score, 1) }} /
                                        {{ number_format($submission->total_points, 1) }} pts
                                    </span>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                            <td>
                                @if($submission->status === 'graded')
                                    <span class="badge badge-success">Corrigida</span>
                                @elseif($submission->status === 'submitted')
                                    <span class="badge badge-warning">Pendente</span>
                                @else
                                    <span class="badge badge-info">Em andamento</span>
                                @endif
                            </td>
                            <td class="text-xs font-medium text-gray-600">
                                {{ ($submission->finished_at ?? $submission->updated_at)?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <span aria-hidden="true"
                                    class="material-symbols-outlined mb-2 block text-4xl text-gray-400">monitoring</span>
                                <p class="font-bold text-gray-600">Nenhuma submissão encontrada.</p>
                                <p class="mt-1 text-sm text-gray-500">Aplique avaliações para gerar os relatórios.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const Chart = await window.loadChart();
                const scoreLabels = @json($scoreDistribution->keys()->values());
                const scoreData = @json($scoreDistribution->values());
                const examLabels = @json($examPerformance->pluck('exam.title'));
                const examAverages = @json($examPerformance->pluck('avg_score'));
                const trendLabels = @json($performanceTrend->pluck('label'));
                const trendAverages = @json($performanceTrend->pluck('average'));

                new Chart(document.getElementById('scoreDistributionChart'), {
                    type: 'bar',
                    data: {
                        labels: scoreLabels.map(value => value === 100 ? '100%' : `${value}–${value + 9}%`),
                        datasets: [{
                            data: scoreData,
                            backgroundColor: 'rgba(29, 120, 166, 0.22)',
                            borderColor: '#1d78a6',
                            borderWidth: 2,
                            borderRadius: 8,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } },
                        },
                    },
                });

                if (examLabels.length === 0) {
                    document.getElementById('examPerformanceChartWrapper').innerHTML =
                        '<div class="flex h-full items-center justify-center text-center text-sm font-bold text-gray-500">' +
                        'Ainda não há avaliações corrigidas para comparar.</div>';
                    return;
                }

                new Chart(document.getElementById('examPerformanceChart'), {
                    type: 'bar',
                    data: {
                        labels: examLabels.map(label => label?.length > 24 ? `${label.slice(0, 24)}…` : label),
                        datasets: [{
                            label: 'Média (%)',
                            data: examAverages,
                            backgroundColor: 'rgba(71, 118, 191, 0.22)',
                            borderColor: '#4776bf',
                            borderWidth: 2,
                            borderRadius: 8,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, max: 100 },
                            y: { grid: { display: false } },
                        },
                    },
                });

                if (trendLabels.length === 0) {
                    document.getElementById('performanceTrendChartWrapper').innerHTML =
                        '<div class="flex h-full items-center justify-center text-center text-sm font-bold text-gray-500">' +
                        'Ainda não há histórico suficiente para exibir a evolução.</div>';
                    return;
                }

                new Chart(document.getElementById('performanceTrendChart'), {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Média (%)',
                            data: trendAverages,
                            borderColor: '#6670b5',
                            backgroundColor: 'rgba(109, 40, 217, 0.12)',
                            borderWidth: 3,
                            pointRadius: 4,
                            pointBackgroundColor: '#6670b5',
                            tension: 0.25,
                            fill: true,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, max: 100 },
                            x: { grid: { display: false } },
                        },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
