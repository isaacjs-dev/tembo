<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Logs de Auditoria</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Logs de Auditoria</h1>
                <p class="text-gray-400 mt-1">Histórico completo de ações no sistema.</p>
            </div>
        </div>
    </x-slot>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-2xl border-2 border-duo-border p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
            <div>
                <label for="audit-action" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Ação</label>
                <select id="audit-action" name="action"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
                    <option value="">Todas</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>{{ ucfirst($act) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="audit-entity" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Entidade</label>
                <input id="audit-entity" type="text" name="model_type" value="{{ request('model_type') }}" placeholder="Ex: Plan"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
            </div>
            <div>
                <label for="audit-organization" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Instituição ID</label>
                <input id="audit-organization" type="number" min="1" name="organization_id" value="{{ request('organization_id') }}"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
            </div>
            <div>
                <label for="audit-origin" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Origem</label>
                <select id="audit-origin" name="origin"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
                    <option value="">Todas</option>
                    @foreach (['web' => 'Web', 'api' => 'API/Mobile', 'console' => 'Console', 'system' => 'Sistema'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('origin') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="audit-date-from" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">De</label>
                <input id="audit-date-from" type="date" name="date_from" value="{{ request('date_from') }}"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
            </div>
            <div>
                <label for="audit-date-to" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Até</label>
                <input id="audit-date-to" type="date" name="date_to" value="{{ request('date_to') }}"
                    class="w-full px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white font-extrabold rounded-xl text-sm duo-button-primary">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">search</span> Filtrar
                </button>
                <a href="{{ route('admin.audit-logs.index') }}"
                    class="px-3 py-2 text-gray-400 hover:text-red-500 text-sm font-bold">Limpar</a>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-12">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <caption class="sr-only">Registros de auditoria da plataforma</caption>
                <thead class="bg-background-light border-b-2 border-duo-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Data/Hora</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Usuário</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Contexto</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Ação</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Entidade</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">IP</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Detalhes</th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-duo-border" x-data="{ openLog: null }">
                    @forelse($logs as $log)
                        @php $hasAuditDetails = $log->payload || $log->context_json || $log->before_json || $log->after_json; @endphp
                        <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                            @click="openLog = openLog === {{ $log->id }} ? null : {{ $log->id }}">
                            <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-900">{{ $log->user?->name ?? 'Sistema' }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500">
                                <span class="font-bold uppercase">{{ $log->origin }}</span>
                                <span class="block">{{ $log->organization?->name ?? 'Plataforma' }}</span>
                                <span class="block font-mono text-[10px]">{{ $log->request_id ?? 'histórico' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $actionColors = ['created' => 'green', 'updated' => 'blue', 'deleted' => 'red', 'login' => 'purple', 'logout' => 'gray'];
                                    $ac = $actionColors[$log->action] ?? 'gray';
                                @endphp
                                <span
                                    class="px-3 py-1 bg-{{ $ac }}-100 text-{{ $ac }}-600 rounded-full text-xs font-extrabold uppercase border border-{{ $ac }}-200">
                                    {{ $log->action_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 font-medium">{{ $log->model_label }}</td>
                            <td class="px-6 py-4 text-sm text-gray-400">{{ $log->model_id ?? '—' }}</td>
                            <td class="px-6 py-4 text-xs text-gray-400 font-mono">{{ $log->ip_address ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($hasAuditDetails)
                                    <span aria-hidden="true" class="material-symbols-outlined text-gray-400 text-[18px]"
                                        :class="openLog === {{ $log->id }} ? 'rotate-180' : ''">expand_more</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        {{-- Expandable payload --}}
                        @if($hasAuditDetails)
                            <tr x-show="openLog === {{ $log->id }}" x-transition class="bg-gray-50">
                                <td colspan="8" class="px-6 py-4">
                                    @php
                                        $auditBefore = $log->before_json ?? data_get($log->payload, 'old');
                                        $auditAfter = $log->after_json ?? data_get($log->payload, 'new');
                                    @endphp
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        @if($auditBefore)
                                            <div>
                                                <p class="text-xs font-bold text-red-400 uppercase mb-2">Anterior</p>
                                                <pre
                                                    class="bg-red-50 border border-red-200 rounded-xl p-3 text-xs text-red-700 overflow-auto max-h-48">{{ json_encode($auditBefore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if($auditAfter)
                                            <div>
                                                <p class="text-xs font-bold text-green-500 uppercase mb-2">Novo</p>
                                                <pre
                                                    class="bg-green-50 border border-green-200 rounded-xl p-3 text-xs text-green-700 overflow-auto max-h-48">{{ json_encode($auditAfter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if($log->context_json)
                                            <div>
                                                <p class="text-xs font-bold text-blue-500 uppercase mb-2">Contexto</p>
                                                <pre
                                                    class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-700 overflow-auto max-h-48">{{ json_encode($log->context_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-[48px] text-gray-300 mb-3">history</span>
                                <h2 class="text-xl font-bold text-gray-800">Nenhum log encontrado.</h2>
                                <p class="text-gray-500 mt-2">As ações realizadas no sistema serão registradas aqui.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="bg-white border-t-2 border-duo-border px-6 py-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
