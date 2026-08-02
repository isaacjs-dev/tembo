<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Painel</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Administração Global</span>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Logs</span>
                </nav>
                <h1 class="text-3xl font-extrabold text-duo-heading dark:text-white tracking-tight">Logs e Auditoria</h1>
            </div>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border shadow-sm overflow-hidden mb-8">
        <div class="p-6 border-b-2 border-duo-border bg-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="text-lg font-extrabold text-duo-heading flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-accent">history</span> Histórico de Ações
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <caption class="sr-only">Eventos técnicos da plataforma</caption>
                <thead>
                    <tr class="bg-gray-50 border-b-2 border-duo-border">
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest">Data / Hora</th>
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest">Instituição</th>
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest">Usuário</th>
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest">Ação / Evento</th>
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest">Entidade</th>
                        <th class="py-4 px-6 font-extrabold text-xs text-gray-400 uppercase tracking-widest text-center">Detalhes</th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="py-4 px-6">
                                <span class="font-bold text-gray-800 block">{{ $log->created_at->format('d/m/Y') }}</span>
                                <span class="text-xs font-bold text-gray-400">{{ $log->created_at->format('H:i:s') }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-bold text-gray-700 block">{{ $log->organization->name ?? 'Global' }}</span>
                                <span class="text-xs font-bold text-gray-400 uppercase">ID: {{ $log->organization_id ?? '-' }}</span>
                            </td>
                            <td class="py-4 px-6">
                                @if($log->actor)
                                    <span class="font-bold text-gray-700 block">{{ $log->actor->name }}</span>
                                    <span class="text-xs font-bold text-gray-400 uppercase">{{ $log->actor->type }}</span>
                                @else
                                    <span class="font-bold text-gray-400 italic">Sistema</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-2">
                                    @php
                                        $icon = 'info';
                                        $color = 'text-blue-500 bg-blue-50';
                                        if ($log->severity === 'warning') {
                                            $icon = 'warning';
                                            $color = 'text-yellow-600 bg-yellow-50';
                                        } elseif ($log->severity === 'error') {
                                            $icon = 'error';
                                            $color = 'text-red-500 bg-red-50';
                                        }
                                    @endphp
                                    <span aria-hidden="true" class="material-symbols-outlined {{ $color }} rounded-lg p-1 text-[16px]">{{ $icon }}</span>
                                    <span class="font-bold text-gray-600">{{ $log->event_code }}</span>
                                </div>
                                <span class="text-xs text-gray-500 mt-1 block">{{ $log->message }}</span>
                            </td>
                            <td class="py-4 px-6">
                                @if($log->entity_type)
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold font-mono">{{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</span>
                                @else
                                    <span class="text-gray-400 text-xs italic">-</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if(!empty($log->context_json) || !empty($log->before_json) || !empty($log->after_json))
                                    <button onclick="document.getElementById('modal-{{ $log->id }}').classList.remove('hidden')" class="text-primary hover:text-primary-dark transition-colors" title="Ver JSON">
                                        <span aria-hidden="true" class="material-symbols-outlined">data_object</span>
                                    </button>

                                    <!-- Modal (Hidden by default) -->
                                    <div id="modal-{{ $log->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                                        <div class="bg-white rounded-2xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto text-left relative m-4 shadow-xl border-4 border-duo-border">
                                            <button onclick="document.getElementById('modal-{{ $log->id }}').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
                                                <span aria-hidden="true" class="material-symbols-outlined">close</span>
                                            </button>
                                            <h3 class="text-xl font-extrabold text-duo-heading mb-4">Detalhes do Log #{{ $log->id }}</h3>
                                            
                                            <div class="space-y-4">
                                                @if(!empty($log->context_json))
                                                    <div>
                                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Context</h4>
                                                        <pre class="bg-gray-100 p-4 rounded-xl text-xs font-mono text-gray-800 overflow-x-auto border-2 border-gray-200">{{ json_encode($log->context_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                                @if(!empty($log->before_json))
                                                    <div>
                                                        <h4 class="text-xs font-bold text-red-400 uppercase tracking-widest mb-2">Before</h4>
                                                        <pre class="bg-red-50 p-4 rounded-xl text-xs font-mono text-red-800 overflow-x-auto border-2 border-red-100">{{ json_encode($log->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                                @if(!empty($log->after_json))
                                                    <div>
                                                        <h4 class="text-xs font-bold text-green-400 uppercase tracking-widest mb-2">After</h4>
                                                        <pre class="bg-green-50 p-4 rounded-xl text-xs font-mono text-green-800 overflow-x-auto border-2 border-green-100">{{ json_encode($log->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-300 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 px-6 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <span aria-hidden="true" class="material-symbols-outlined text-4xl mb-4 text-gray-300">history_toggle_off</span>
                                    <h3 class="text-lg font-extrabold text-gray-500 mb-2">Nenhum evento registrado</h3>
                                    <p class="text-sm font-bold">As ações importantes do sistema aparecerão aqui.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($logs->hasPages())
            <div class="p-4 border-t-2 border-duo-border bg-gray-50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Modals Background Click to Close Script -->
    <script>
        document.addEventListener('click', function(event) {
            if (event.target.id && event.target.id.startsWith('modal-')) {
                event.target.classList.add('hidden');
            }
        });
    </script>
</x-app-layout>
