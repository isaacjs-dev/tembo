<x-app-layout>
    <x-slot name="header">
        <div>
            <nav class="mb-2 flex items-center gap-2 text-sm font-bold text-gray-400">
                <a class="transition-colors hover:text-primary" href="{{ route('institution.dashboard') }}">Dashboard</a>
                <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                <span class="text-primary">Logs</span>
            </nav>
            <h1 class="text-3xl font-extrabold tracking-tight text-duo-heading dark:text-white">Logs da instituição</h1>
            <p class="mt-1 text-sm text-gray-400">Eventos operacionais registrados somente para a sua instituição.</p>
        </div>
    </x-slot>

    <form method="GET" action="{{ route('institution.logs') }}"
        class="mb-6 grid gap-3 rounded-2xl border-2 border-duo-border bg-white p-4 md:grid-cols-5">
        <input name="search" value="{{ request('search') }}" maxlength="100" placeholder="Buscar evento ou mensagem"
            class="rounded-xl border-gray-300 md:col-span-2">
        <select name="severity" class="input-field !py-2">
            <option value="">Todas as severidades</option>
            @foreach (['info' => 'Informação', 'warning' => 'Alerta', 'error' => 'Erro', 'critical' => 'Crítico'] as $value => $label)
                <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="input-field !py-2" aria-label="Data inicial">
        <div class="flex gap-2">
            <input type="date" name="to" value="{{ request('to') }}" class="input-field min-w-0 flex-1 !py-2" aria-label="Data final">
            <button class="rounded-xl bg-primary px-4 font-bold text-white" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border-2 border-duo-border bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <caption class="sr-only">Eventos registrados para a instituição</caption>
                <thead class="border-b-2 border-duo-border bg-gray-50 text-xs uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-4">Data</th>
                        <th class="px-5 py-4">Severidade</th>
                        <th class="px-5 py-4">Usuário</th>
                        <th class="px-5 py-4">Evento</th>
                        <th class="px-5 py-4">Entidade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-bold uppercase text-gray-600">
                                    {{ $log->severity }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-gray-700">
                                {{ $log->actor?->name ?? 'Sistema' }}
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-bold text-gray-700">{{ $log->event_code }}</p>
                                @if ($log->message)
                                    <p class="mt-1 max-w-xl text-xs text-gray-500">{{ $log->message }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-500">
                                @if ($log->entity_type)
                                    {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">
                                Nenhum evento encontrado para os filtros informados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t-2 border-duo-border bg-gray-50 p-4">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
