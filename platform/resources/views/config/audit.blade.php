<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('admin.config.index') }}">Configuração OMR</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Auditoria</span>
                </nav>
                <h1 class="page-title">Timeline de Auditoria</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">Histórico de todas as alterações nas regras de configuração.</p>
            </div>
            <div>
                <a href="{{ route('admin.config.index') }}" class="btn-secondary flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto pb-12">
        {{-- Timeline --}}
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>

            @forelse($logs as $log)
                @php
                    $iconMap = [
                        'created' => ['icon' => 'add_circle', 'bg' => 'bg-green-100', 'text' => 'text-green-600', 'border' => 'border-green-200'],
                        'updated' => ['icon' => 'edit', 'bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
                        'deactivated' => ['icon' => 'block', 'bg' => 'bg-red-100', 'text' => 'text-red-500', 'border' => 'border-red-200'],
                        'deleted' => ['icon' => 'delete', 'bg' => 'bg-gray-200', 'text' => 'text-gray-500', 'border' => 'border-gray-300'],
                    ];
                    $style = $iconMap[$log->action] ?? $iconMap['updated'];

                    $actionLabels = [
                        'created' => 'Regra criada',
                        'updated' => 'Regra atualizada',
                        'deactivated' => 'Regra desativada',
                        'deleted' => 'Regra excluída',
                    ];
                @endphp

                <div class="relative flex gap-4 mb-6">
                    {{-- Timeline dot --}}
                    <div class="relative z-10 flex-shrink-0 w-12 h-12 rounded-full border-2 {{ $style['bg'] }} {{ $style['border'] }} flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px] {{ $style['text'] }}">{{ $style['icon'] }}</span>
                    </div>

                    {{-- Content card --}}
                    <div class="card flex-1 p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">
                                    {{ $actionLabels[$log->action] ?? $log->action }}
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gray-100 text-gray-600">
                                        {{ $log->config_key === 'answer_sheet_type' ? 'Gabarito' : 'Modo Leitura' }}
                                    </span>
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full
                                        {{ $log->scope_type === 'user' ? 'bg-blue-100 text-blue-700' :
                                           ($log->scope_type === 'role' ? 'bg-orange-100 text-orange-700' :
                                           ($log->scope_type === 'permission' ? 'bg-accent/15 text-secondary-dark' :
                                           ($log->scope_type === 'user_type' ? 'bg-pink-100 text-pink-700' :
                                           'bg-gray-100 text-gray-600'))) }}">
                                        {{ ucfirst($log->scope_type ?? 'global') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</p>
                                <p class="text-[10px] text-gray-300">{{ $log->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        {{-- Changes detail --}}
                        @if($log->action === 'updated' && $log->old_values && $log->new_values)
                            <div class="mt-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <p class="text-gray-400 font-bold uppercase tracking-wide text-[10px] mb-1">Anterior</p>
                                        @php $old = is_string($log->old_values) ? json_decode($log->old_values, true) : $log->old_values; @endphp
                                        @if(is_array($old))
                                            @foreach($old as $key => $val)
                                                <p class="text-red-600 font-mono">{{ $key }}: {{ is_bool($val) ? ($val ? 'true' : 'false') : $val }}</p>
                                            @endforeach
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-gray-400 font-bold uppercase tracking-wide text-[10px] mb-1">Novo</p>
                                        @php $new = is_string($log->new_values) ? json_decode($log->new_values, true) : $log->new_values; @endphp
                                        @if(is_array($new))
                                            @foreach($new as $key => $val)
                                                <p class="text-green-600 font-mono">{{ $key }}: {{ is_bool($val) ? ($val ? 'true' : 'false') : $val }}</p>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif($log->action === 'created' && $log->new_values)
                            <div class="mt-3 p-3 rounded-lg bg-green-50 border border-green-100">
                                <p class="text-xs text-gray-600">
                                    @php $snapshot = is_string($log->new_values) ? json_decode($log->new_values, true) : $log->new_values; @endphp
                                    @if(is_array($snapshot))
                                        Valor: <span class="font-bold text-green-700">{{ $snapshot['config_value'] ?? '-' }}</span>
                                        @if(isset($snapshot['scope_id']))
                                            · Escopo: <span class="font-bold">{{ $snapshot['scope_id'] }}</span>
                                        @endif
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Change reason --}}
                        @if($log->change_reason)
                            <div class="mt-2 text-xs text-gray-500 italic flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px] text-gray-400">chat_bubble</span>
                                "{{ $log->change_reason }}"
                            </div>
                        @endif

                        {{-- User who made the change --}}
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px] text-gray-400">person</span>
                            <p class="text-[11px] text-gray-400">
                                {{ $log->changedBy->name ?? 'Sistema' }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-16 text-gray-400">
                    <span class="material-symbols-outlined text-5xl mb-3">history</span>
                    <p class="text-sm font-bold">Nenhum registro de auditoria</p>
                    <p class="text-xs mt-1">Alterações nas configurações aparecerão aqui.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
