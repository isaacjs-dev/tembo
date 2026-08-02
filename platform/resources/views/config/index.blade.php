<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Configuração OMR</span>
                </nav>
                <h1 class="page-title">Configuração de Leitura OMR</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">Gerencie tipos de gabarito, modos de leitura e regras de configuração do scanner.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.config.audit') }}" class="btn-secondary flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">history</span>
                    Auditoria
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto space-y-8 pb-12" x-data="configPanel()">

        {{-- Status messages --}}
        @if (session('status'))
            <div class="alert alert-success" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        {{-- ── Section: Active Configuration ── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Answer Sheet Types --}}
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary">description</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Tipos de Gabarito</h2>
                        <p class="text-xs text-gray-500">Modelos disponíveis para cartão-resposta</p>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach($answerSheetTypes as $type)
                    <div class="flex items-center justify-between p-3 rounded-lg border {{ $type->is_default ? 'border-primary/30 bg-primary/5' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md flex items-center justify-center {{ $type->is_default ? 'bg-primary/15' : 'bg-gray-200' }}">
                                <span class="material-symbols-outlined text-sm {{ $type->is_default ? 'text-primary' : 'text-gray-500' }}">
                                    {{ $type->slug === 'essential' ? 'description' : 'grid_on' }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ $type->name }}</p>
                                <p class="text-xs text-gray-500">{{ $type->description }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($type->is_default)
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide rounded-full bg-primary/10 text-primary">Padrão</span>
                            @endif
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-gray-200 text-gray-600">v{{ $type->version }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Scan Modes --}}
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-accent">qr_code_scanner</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Modos de Leitura</h2>
                        <p class="text-xs text-gray-500">Estratégias de aquisição de dados</p>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach($scanModes as $mode)
                    <div class="flex items-center justify-between p-3 rounded-lg border {{ $mode->is_default ? 'border-accent/30 bg-accent/5' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md flex items-center justify-center {{ $mode->is_default ? 'bg-accent/15' : 'bg-gray-200' }}">
                                <span class="material-symbols-outlined text-sm {{ $mode->is_default ? 'text-accent' : 'text-gray-500' }}">
                                    {{ $mode->slug === 'hybrid' ? 'sync_alt' : ($mode->slug === 'preloaded' ? 'cloud_download' : 'qr_code_2') }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ $mode->name }}</p>
                                <p class="text-xs text-gray-500">{{ $mode->description }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if($mode->offline_capable)
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-green-100 text-green-700" title="Funciona offline">Offline</span>
                            @endif
                            @if($mode->is_default)
                                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide rounded-full bg-accent/10 text-accent">Padrão</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Section: Configuration Rules ── --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-amber-600">tune</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Regras de Configuração</h2>
                        <p class="text-xs text-gray-500">Hierarquia: Usuário → Permissão → Papel → Tipo → Global</p>
                    </div>
                </div>
                <button @click="showCreateModal = true" class="btn-primary flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Nova Regra
                </button>
            </div>

            {{-- Rules table --}}
            @forelse($groupedRules as $configKey => $keyRules)
            <div class="mb-6">
                <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">
                        {{ $configKey === 'answer_sheet_type' ? 'description' : 'qr_code_scanner' }}
                    </span>
                    {{ $configKey === 'answer_sheet_type' ? 'Tipo de Gabarito' : 'Modo de Leitura' }}
                </h3>
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Escopo</th>
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Valor</th>
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Prioridade</th>
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Vigência</th>
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Status</th>
                                <th class="px-4 py-3 font-bold text-gray-600 text-xs uppercase tracking-wide">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($keyRules as $rule)
                            <tr class="{{ !$rule->is_active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full
                                            {{ $rule->scope_type === 'user' ? 'bg-blue-100 text-blue-700' :
                                               ($rule->scope_type === 'permission' ? 'bg-accent/15 text-secondary-dark' :
                                               ($rule->scope_type === 'role' ? 'bg-orange-100 text-orange-700' :
                                               ($rule->scope_type === 'user_type' ? 'bg-pink-100 text-pink-700' :
                                               'bg-gray-200 text-gray-600'))) }}">
                                            {{ ucfirst($rule->scope_type) }}
                                        </span>
                                        @if($rule->scope_id)
                                            <span class="text-xs text-gray-500">#{{ $rule->scope_id }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-bold text-gray-800">{{ $rule->config_value }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-xs font-bold text-gray-600">
                                        {{ $rule->priority }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    @if($rule->effective_from)
                                        {{ $rule->effective_from->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                    @if($rule->effective_until)
                                        → {{ $rule->effective_until->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full
                                        {{ $rule->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $rule->is_active ? 'bg-green-500' : 'bg-red-400' }}"></span>
                                        {{ $rule->is_active ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @if($rule->is_active)
                                        <form action="{{ route('admin.config.rules.destroy', $rule->id) }}" method="POST"
                                            onsubmit="return confirm('Desativar esta regra?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-md hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors" title="Desativar">
                                                <span class="material-symbols-outlined text-[18px]">block</span>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="text-center py-12 text-gray-400">
                <span class="material-symbols-outlined text-5xl mb-3">tune</span>
                <p class="text-sm font-bold">Nenhuma regra configurada</p>
                <p class="text-xs mt-1">O sistema usará os padrões: Essential + Híbrido</p>
            </div>
            @endforelse
        </div>

        {{-- ── Section: Precedence Visualizer ── --}}
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600">account_tree</span>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Visualizador de Precedência</h2>
                    <p class="text-xs text-gray-500">Veja como a configuração é resolvida para cada nível</p>
                </div>
            </div>

            <div class="flex gap-2 items-end overflow-x-auto pb-4">
                @php
                    $levels = [
                        ['label' => 'Usuário', 'icon' => 'person', 'priority' => 1, 'color' => 'blue'],
                        ['label' => 'Permissão', 'icon' => 'vpn_key', 'priority' => 2, 'color' => 'purple'],
                        ['label' => 'Papel', 'icon' => 'badge', 'priority' => 3, 'color' => 'orange'],
                        ['label' => 'Tipo', 'icon' => 'group', 'priority' => 4, 'color' => 'pink'],
                        ['label' => 'Global', 'icon' => 'public', 'priority' => 5, 'color' => 'gray'],
                    ];
                @endphp
                @foreach($levels as $i => $level)
                    @php
                        $hasRules = $rules->where('scope_type', match($level['label']) {
                            'Usuário' => 'user',
                            'Permissão' => 'permission',
                            'Papel' => 'role',
                            'Tipo' => 'user_type',
                            'Global' => 'global',
                        })->where('is_active', true)->count();
                    @endphp
                    <div class="flex flex-col items-center gap-2 min-w-[100px]">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-xl border-2 flex items-center justify-center transition-all
                                {{ $hasRules > 0
                                    ? 'border-'.$level['color'].'-400 bg-'.$level['color'].'-50 shadow-sm'
                                    : 'border-gray-200 bg-gray-50' }}">
                                <span class="material-symbols-outlined
                                    {{ $hasRules > 0 ? 'text-'.$level['color'].'-500' : 'text-gray-400' }}">
                                    {{ $level['icon'] }}
                                </span>
                            </div>
                            @if($hasRules > 0)
                                <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-{{ $level['color'] }}-500 text-white text-[10px] font-bold flex items-center justify-center">
                                    {{ $hasRules }}
                                </span>
                            @endif
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-bold text-gray-700">{{ $level['label'] }}</p>
                            <p class="text-[10px] text-gray-400">P{{ $level['priority'] }}</p>
                        </div>
                    </div>
                    @if($i < count($levels) - 1)
                        <div class="flex items-center mb-8">
                            <span class="material-symbols-outlined text-gray-300 text-[20px]">chevron_right</span>
                        </div>
                    @endif
                @endforeach
            </div>

            <p class="text-xs text-gray-400 mt-2 text-center">
                A regra com menor prioridade (P1) vence. Se não houver regra, avança para o próximo nível.
            </p>
        </div>

        {{-- ── Create Rule Modal ── --}}
        <div x-show="showCreateModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.away="showCreateModal = false" class="card p-6 w-full max-w-lg">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Nova Regra de Configuração</h3>

                <form action="{{ route('admin.config.rules.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Chave</label>
                            <select name="config_key" class="input-field w-full" required>
                                <option value="answer_sheet_type">Tipo de Gabarito</option>
                                <option value="scan_mode">Modo de Leitura</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Valor</label>
                            <select name="config_value" class="input-field w-full" required>
                                <optgroup label="Gabarito">
                                    @foreach($answerSheetTypes as $type)
                                        <option value="{{ $type->slug }}">{{ $type->name }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Modo de Leitura">
                                    @foreach($scanModes as $mode)
                                        <option value="{{ $mode->slug }}">{{ $mode->name }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Tipo de Escopo</label>
                            <select name="scope_type" x-model="newScopeType" class="input-field w-full" required>
                                <option value="global">Global</option>
                                <option value="user_type">Tipo de Usuário</option>
                                <option value="role">Papel/Cargo</option>
                                <option value="user">Usuário</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">ID do Escopo</label>
                            <template x-if="newScopeType === 'user_type'">
                                <select name="scope_id" class="input-field w-full">
                                    <option value="admin">Admin</option>
                                    <option value="teacher">Professor</option>
                                    <option value="student">Aluno</option>
                                </select>
                            </template>
                            <template x-if="newScopeType === 'role'">
                                <select name="scope_id" class="input-field w-full">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </template>
                            <template x-if="newScopeType === 'user'">
                                <input type="text" name="scope_id" placeholder="ID do usuário" class="input-field w-full">
                            </template>
                            <template x-if="newScopeType === 'global'">
                                <input type="text" name="scope_id" value="" disabled class="input-field w-full bg-gray-100 cursor-not-allowed" placeholder="N/A">
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Vigência início</label>
                            <input type="date" name="effective_from" class="input-field w-full">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Vigência fim</label>
                            <input type="date" name="effective_until" class="input-field w-full">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Motivo da alteração</label>
                        <textarea name="change_reason" rows="2" class="input-field w-full" placeholder="Opcional..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showCreateModal = false" class="btn-secondary">Cancelar</button>
                        <button type="submit" class="btn-primary">Criar Regra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function configPanel() {
        return {
            showCreateModal: false,
            newScopeType: 'global',
        };
    }
    </script>
</x-app-layout>
