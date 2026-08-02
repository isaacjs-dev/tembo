<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Convites</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Convites Enviados</h1>
                <p class="text-gray-400 mt-1">Gerencie os convites para a sua instituição.</p>
            </div>
            <a href="{{ route('institution.invites.create') }}"
                class="flex items-center gap-2 px-6 py-4 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all uppercase tracking-wider text-sm whitespace-nowrap duo-button-primary">
                <span aria-hidden="true" class="material-symbols-outlined">person_add</span>
                Enviar Convite
            </a>
        </div>
    </x-slot>

    @if(session('status'))
        <div
            class="flex items-center gap-3 bg-green-50 border-2 border-green-200 text-green-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm">
            <span aria-hidden="true" class="material-symbols-outlined text-green-500">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-12">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <caption class="sr-only">Convites enviados pela instituição</caption>
                <thead class="bg-background-light border-b-2 border-duo-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Perfil</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Enviado por</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">
                            Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Expira em</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-duo-border">
                    @forelse($invites as $invite)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="size-10 rounded-xl border-2 border-blue-200 bg-blue-50 flex items-center justify-center text-blue-500 font-bold">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">mail</span>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900">{{ $invite->invitee_email }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $roles = ['admin' => ['Administrador', 'purple'], 'teacher' => ['Professor', 'blue'], 'student' => ['Aluno', 'green']];
                                    [$roleLabel, $roleColor] = $roles[$invite->target_role] ?? [$invite->target_role, 'gray'];
                                @endphp
                                <span
                                    class="px-3 py-1 bg-{{ $roleColor }}-100 text-{{ $roleColor }}-600 rounded-full text-xs font-extrabold uppercase tracking-tight border border-{{ $roleColor }}-200">
                                    {{ $roleLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 font-medium">{{ $invite->inviter->name }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $statusMap = [
                                        'pending' => ['Pendente', 'yellow'],
                                        'accepted' => ['Aceito', 'green'],
                                        'declined' => ['Recusado', 'red'],
                                        'expired' => ['Expirado', 'gray'],
                                        'canceled' => ['Cancelado', 'gray'],
                                    ];
                                    [$sLabel, $sColor] = $statusMap[$invite->status] ?? [$invite->status, 'gray'];
                                @endphp
                                <span
                                    class="px-3 py-1 bg-{{ $sColor }}-100 text-{{ $sColor }}-600 rounded-full text-xs font-extrabold uppercase border border-{{ $sColor }}-200">
                                    {{ $sLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $invite->expires_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($invite->isPending())
                                    <form action="{{ route('institution.invites.destroy', $invite) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Cancelar este convite?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                            title="Cancelar">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">cancel</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-[48px] text-gray-300 mb-3">inbox</span>
                                <h2 class="text-xl font-bold text-gray-800">Nenhum convite enviado.</h2>
                                <p class="text-gray-500 mt-2">Clique em "Enviar Convite" para convidar alguém.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invites->hasPages())
            <div class="bg-white border-t-2 border-duo-border px-6 py-4">
                {{ $invites->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
