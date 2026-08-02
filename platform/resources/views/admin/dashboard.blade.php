<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('admin.dashboard') }}">Home</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Dashboard</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Painel Administrativo
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <label for="admin-dashboard-period" class="sr-only">Período das métricas</label>
                <select id="admin-dashboard-period" onchange="window.location='?period='+this.value"
                    class="px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-bold focus:border-primary focus:ring-0">
                    <option value="7" {{ $periodDays == 7 ? 'selected' : '' }}>Últimos 7 dias</option>
                    <option value="30" {{ $periodDays == 30 ? 'selected' : '' }}>Últimos 30 dias</option>
                    <option value="90" {{ $periodDays == 90 ? 'selected' : '' }}>Últimos 90 dias</option>
                </select>
            </div>
        </div>
    </x-slot>

    {{-- KPIs Row — Clicáveis --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @php
            $kpis = [
                [
                    'label' => 'Usuários', 'value' => $totalUsers, 'icon' => 'group',
                    'growth' => $usersGrowth > 0 ? "+{$usersGrowth}%" : ($usersGrowth < 0 ? "{$usersGrowth}%" : null),
                    'iconBg' => 'bg-primary/10', 'iconText' => 'text-primary',
                    'detail' => "Ativos: {$activeUsers} · Inativos: {$inactiveUsers} · Pendentes: {$pendingUsers} · Bloqueados: {$blockedUsers}",
                ],
                [
                    'label' => 'Professores', 'value' => $totalTeachers, 'icon' => 'school', 'growth' => null,
                    'iconBg' => 'bg-green-50', 'iconText' => 'text-green-600',
                    'detail' => "Do total de {$totalUsers} usuários",
                ],
                [
                    'label' => 'Turmas', 'value' => $totalClasses, 'icon' => 'class', 'growth' => null,
                    'iconBg' => 'bg-orange-50', 'iconText' => 'text-orange-500',
                    'detail' => "Em {$totalOrgs} instituição(ões)",
                ],
                [
                    'label' => 'Planos Ativos', 'value' => $activePlans, 'icon' => 'workspace_premium', 'growth' => null,
                    'iconBg' => 'bg-blue-50', 'iconText' => 'text-blue-500',
                    'detail' => "{$totalPlans} planos cadastrados no total",
                    'href' => route('admin.plans.index'),
                ],
                [
                    'label' => 'Assinaturas', 'value' => $activeSubscriptions, 'icon' => 'receipt_long', 'growth' => null,
                    'iconBg' => 'bg-accent-light', 'iconText' => 'text-accent',
                    'detail' => "Assinaturas com status ativo",
                ],
            ];
        @endphp
        @foreach($kpis as $kpi)
            @php $tag = isset($kpi['href']) ? 'a' : 'div'; @endphp
            <{{ $tag }} @if(isset($kpi['href'])) href="{{ $kpi['href'] }}" @endif
                class="bg-white rounded-2xl border-2 border-duo-border p-5 flex flex-col gap-3 group hover:border-primary
                hover:shadow-lg transition-all duration-200 {{ isset($kpi['href']) ? 'cursor-pointer' : '' }}">
                <div class="flex items-center justify-between">
                    <div class="size-10 rounded-xl {{ $kpi['iconBg'] }} flex items-center justify-center group-hover:scale-110 transition-transform">
                        <span aria-hidden="true" class="material-symbols-outlined {{ $kpi['iconText'] }} text-[22px]">{{ $kpi['icon'] }}</span>
                    </div>
                    @if($kpi['growth'])
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-extrabold">{{ $kpi['growth'] }}</span>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ $kpi['label'] }}</p>
                    <p class="text-3xl font-extrabold text-gray-900">{{ number_format($kpi['value'], 0, ',', '.') }}</p>
                </div>
                {{-- Detail tooltip --}}
                <div class="text-xs text-gray-500 font-medium border-t border-duo-border pt-2 mt-auto">
                    {{ $kpi['detail'] }}
                </div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Alertas Operacionais --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border-2 border-duo-border overflow-hidden">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light flex items-center justify-between">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-yellow-500">warning</span> Atenção Necessária
                </h2>
                <span
                    class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-extrabold border border-yellow-200">
                    {{ $pendingInvites + $pendingUsers + $inactiveUsers }} pendências
                </span>
            </div>
            <div class="divide-y-2 divide-duo-border">
                @if($pendingInvites > 0)
                    <div class="flex items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 rounded-full bg-yellow-400"></span>
                            <div>
                                <p class="text-sm font-bold text-gray-700">{{ $pendingInvites }} convite(s) pendente(s)</p>
                                <p class="text-xs text-gray-400">Convites aguardando aceite</p>
                            </div>
                        </div>
                    </div>
                @endif
                @if($pendingUsers > 0)
                    <div class="flex items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 rounded-full bg-blue-400"></span>
                            <div>
                                <p class="text-sm font-bold text-gray-700">{{ $pendingUsers }} usuário(s) pendente(s)</p>
                                <p class="text-xs text-gray-400">Contas aguardando ativação</p>
                            </div>
                        </div>
                    </div>
                @endif
                @if($blockedUsers > 0)
                    <div class="flex items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 rounded-full bg-red-400"></span>
                            <div>
                                <p class="text-sm font-bold text-gray-700">{{ $blockedUsers }} usuário(s) bloqueado(s)</p>
                                <p class="text-xs text-gray-400">Contas com restrições de acesso</p>
                            </div>
                        </div>
                    </div>
                @endif
                @if($pendingInvites + $pendingUsers + $blockedUsers === 0)
                    <div class="px-6 py-8 text-center">
                        <span aria-hidden="true" class="material-symbols-outlined text-green-400 text-[36px]">check_circle</span>
                        <p class="text-sm font-bold text-gray-600 mt-2">Tudo limpo! Nenhuma pendência encontrada.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Ações Rápidas + Resumo --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700">Ações Rápidas</h2>
            </div>
            <div class="p-5 space-y-3">
                <a href="{{ route('admin.plans.create') }}"
                    class="flex items-center gap-3 w-full px-4 py-3 bg-primary text-white font-extrabold rounded-xl duo-button-shadow text-sm duo-button-primary transition-all hover:brightness-110">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">add_circle</span> Criar Plano
                </a>
                <a href="{{ route('admin.audit-logs.index') }}"
                    class="flex items-center gap-3 w-full px-4 py-3 bg-white border-2 border-duo-border text-gray-700 font-extrabold rounded-xl text-sm hover:border-primary transition-all">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">history</span> Ver Logs de Auditoria
                </a>
                <a href="{{ route('admin.plans.index') }}"
                    class="flex items-center gap-3 w-full px-4 py-3 bg-white border-2 border-duo-border text-gray-700 font-extrabold rounded-xl text-sm hover:border-primary transition-all">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">diamond</span> Gerenciar Planos
                </a>
            </div>
            <div class="px-6 py-4 border-t-2 border-duo-border">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Resumo de Convites</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Pendentes</span><span
                            class="font-bold text-yellow-600">{{ $pendingInvites }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Aceitos</span><span
                            class="font-bold text-green-600">{{ $acceptedInvites }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Recusados</span><span
                            class="font-bold text-red-500">{{ $declinedInvites }}</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Distribuição por Plano — clicável --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light flex items-center justify-between">
            <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">bar_chart</span> Distribuição por Plano
            </h2>
            <a href="{{ route('admin.plans.index') }}" class="text-sm text-primary font-bold hover:underline">Ver todos
                →</a>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($planDistribution as $plan)
                    <a href="{{ route('admin.plans.edit', $plan) }}"
                        class="flex items-center gap-4 bg-background-light border-2 border-duo-border rounded-xl px-5 py-4 hover:border-primary hover:shadow-md transition-all group">
                        <div
                            class="size-12 rounded-xl bg-primary/10 border-2 border-primary flex items-center justify-center group-hover:scale-110 transition-transform">
                            <span class="text-primary font-extrabold text-lg">{{ $plan->tier_level }}</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-gray-900 group-hover:text-primary transition-colors">
                                {{ $plan->name }}</p>
                            <p class="text-2xl font-extrabold text-primary">{{ $plan->subscriptions_count }}</p>
                            <p class="text-xs text-gray-400">assinatura(s) ativa(s)</p>
                        </div>
                        <span aria-hidden="true"
                            class="material-symbols-outlined text-gray-300 group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Status dos Usuários — interativo --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
            <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">people</span> Usuários por Status
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @php
                    $statusCards = [
                        [
                            'label' => 'Ativos', 'value' => $activeUsers, 'icon' => 'check_circle',
                            'desc' => 'Usuários com acesso normal ao sistema',
                            'cardCls' => 'bg-green-50 border-green-200',
                            'iconCls' => 'text-green-500', 'valueCls' => 'text-green-700',
                            'labelCls' => 'text-green-500', 'descCls' => 'text-green-600 border-green-200',
                        ],
                        [
                            'label' => 'Inativos', 'value' => $inactiveUsers, 'icon' => 'cancel',
                            'desc' => 'Contas desativadas',
                            'cardCls' => 'bg-gray-50 border-gray-200',
                            'iconCls' => 'text-gray-400', 'valueCls' => 'text-gray-600',
                            'labelCls' => 'text-gray-400', 'descCls' => 'text-gray-500 border-gray-200',
                        ],
                        [
                            'label' => 'Pendentes', 'value' => $pendingUsers, 'icon' => 'schedule',
                            'desc' => 'Aguardando ativação ou confirmação',
                            'cardCls' => 'bg-yellow-50 border-yellow-200',
                            'iconCls' => 'text-yellow-500', 'valueCls' => 'text-yellow-700',
                            'labelCls' => 'text-yellow-500', 'descCls' => 'text-yellow-600 border-yellow-200',
                        ],
                        [
                            'label' => 'Bloqueados', 'value' => $blockedUsers, 'icon' => 'block',
                            'desc' => 'Acesso restringido por violação',
                            'cardCls' => 'bg-red-50 border-red-200',
                            'iconCls' => 'text-red-500', 'valueCls' => 'text-red-700',
                            'labelCls' => 'text-red-500', 'descCls' => 'text-red-600 border-red-200',
                        ],
                    ];
                @endphp
                @foreach($statusCards as $card)
                    <div class="text-center {{ $card['cardCls'] }} border-2 rounded-xl px-4 py-5 hover:shadow-md hover:scale-[1.02] transition-all cursor-default group">
                        <span aria-hidden="true" class="material-symbols-outlined {{ $card['iconCls'] }} text-[28px] group-hover:scale-110 transition-transform inline-block">{{ $card['icon'] }}</span>
                        <p class="text-3xl font-extrabold {{ $card['valueCls'] }} my-1">{{ $card['value'] }}</p>
                        <p class="text-xs font-bold {{ $card['labelCls'] }} uppercase">{{ $card['label'] }}</p>
                        <p class="text-xs {{ $card['descCls'] }} mt-2 border-t pt-2">
                            {{ $card['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Atividade Recente --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light flex items-center justify-between">
            <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">trending_up</span> Atividade do Sistema
            </h2>
            <span class="text-sm text-gray-500 font-bold">Últimos {{ $periodDays }} dias</span>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-primary/5 rounded-xl border-2 border-primary/10">
                    <p class="text-4xl font-extrabold text-primary">{{ $recentLogs }}</p>
                    <p class="text-sm font-bold text-gray-600 mt-1">Ações registradas</p>
                    <a href="{{ route('admin.audit-logs.index') }}"
                        class="text-xs text-primary hover:underline mt-1 inline-block">Ver logs →</a>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-xl border-2 border-green-100">
                    <p class="text-4xl font-extrabold text-green-600">{{ $recentUsers }}</p>
                    <p class="text-sm font-bold text-gray-600 mt-1">Novos usuários</p>
                    <p class="text-xs text-gray-400 mt-1">No período selecionado</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-xl border-2 border-blue-100">
                    <p class="text-4xl font-extrabold text-blue-600">{{ $totalOrgs }}</p>
                    <p class="text-sm font-bold text-gray-600 mt-1">Instituições</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $totalInstitutionAdmins }} admin(s) de instituição</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
