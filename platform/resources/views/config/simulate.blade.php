<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('admin.config.index') }}">Configuração OMR</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Simulação</span>
                </nav>
                <h1 class="page-title">Simulação de Configuração</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">
                    Configuração efetiva para <strong>{{ $targetUser->name }}</strong> (ID: {{ $targetUser->id }})
                </p>
            </div>
            <div>
                <a href="{{ route('admin.config.index') }}" class="btn-secondary flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Voltar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto pb-12 space-y-6">

        {{-- User context card --}}
        <div class="card p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">person</span>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-800">{{ $targetUser->name }}</p>
                <p class="text-xs text-gray-500">{{ $targetUser->email }}</p>
            </div>
        </div>

        {{-- Resolution results --}}
        @foreach($results as $configKey => $trace)
            @php
                $keyLabel = $configKey === 'answer_sheet_type' ? 'Tipo de Gabarito' : 'Modo de Leitura';
                $keyIcon = $configKey === 'answer_sheet_type' ? 'description' : 'qr_code_scanner';
            @endphp

            <div class="card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary">{{ $keyIcon }}</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">{{ $keyLabel }}</h2>
                        <p class="text-xs text-gray-500">{{ $configKey }}</p>
                    </div>
                    <div class="ml-auto">
                        <span class="px-3 py-1.5 text-sm font-bold rounded-lg bg-primary/10 text-primary">
                            {{ $trace['resolved_value'] ?? $trace['value'] ?? 'fallback' }}
                        </span>
                    </div>
                </div>

                {{-- Resolution chain --}}
                @if(isset($trace['trace']) && is_array($trace['trace']))
                    <div class="space-y-2 pl-4 border-l-2 border-gray-200">
                        @foreach($trace['trace'] as $level)
                            @php
                                $isMatch = $level['matched'] ?? false;
                            @endphp
                            <div class="relative pl-4">
                                <div class="absolute -left-[9px] top-2 w-4 h-4 rounded-full border-2
                                    {{ $isMatch ? 'bg-primary border-primary' : 'bg-white border-gray-300' }}"></div>
                                <div class="p-3 rounded-lg {{ $isMatch ? 'bg-primary/5 border border-primary/20' : 'bg-gray-50 border border-gray-100' }}">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold {{ $isMatch ? 'text-primary' : 'text-gray-500' }}">
                                                {{ ucfirst($level['scope_type'] ?? 'unknown') }}
                                            </span>
                                            @if(isset($level['scope_id']))
                                                <span class="text-[10px] text-gray-400">#{{ $level['scope_id'] }}</span>
                                            @endif
                                            @if(isset($level['priority']))
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-200 text-gray-500">
                                                    P{{ $level['priority'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div>
                                            @if($isMatch)
                                                <span class="flex items-center gap-1 text-xs font-bold text-primary">
                                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                                    {{ $level['value'] ?? '-' }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400 italic">
                                                    {{ $level['reason'] ?? 'Nenhuma regra' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
