<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Convites Recebidos</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Convites Recebidos
                </h1>
                <p class="text-gray-400 mt-1">Aceite ou recuse convites de instituições.</p>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div
            class="flex items-center gap-3 bg-green-50 border-2 border-green-200 text-green-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm">
            <span aria-hidden="true" class="material-symbols-outlined text-green-500">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    @if($invites->isEmpty())
        <div class="bg-white rounded-2xl border-2 border-duo-border p-12 text-center mb-12">
            <span aria-hidden="true" class="material-symbols-outlined text-[48px] text-gray-300 mb-3">mark_email_read</span>
            <h3 class="text-xl font-bold text-gray-800">Nenhum convite pendente.</h3>
            <p class="text-gray-500 mt-2">Quando alguém convidar você para uma instituição, o convite aparecerá aqui.</p>
        </div>
    @else
        <div class="space-y-4 mb-12">
            @foreach($invites as $invite)
                <div
                    class="bg-white rounded-2xl border-2 border-duo-border p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="size-12 rounded-xl bg-primary/10 border-2 border-primary flex items-center justify-center">
                            <span aria-hidden="true" class="material-symbols-outlined text-primary text-[24px]">apartment</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-gray-900">{{ $invite->organization?->name ?? 'Convite Direto' }}
                            </h4>
                            <p class="text-sm text-gray-500">
                                Enviado por <strong>{{ $invite->inviter->name }}</strong>
                                · Perfil: <span class="font-bold text-primary">{{ ucfirst($invite->target_role) }}</span>
                            </p>
                            <p class="text-xs text-gray-400 mt-1">Expira em {{ $invite->expires_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <form action="{{ route('institution.invites.decline.token', $invite->token) }}" method="POST"
                            class="inline">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-2 px-5 py-3 bg-white border-2 border-duo-border text-gray-600 font-extrabold rounded-xl transition-all text-sm hover:border-red-300 hover:text-red-500">
                                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">close</span> Recusar
                            </button>
                        </form>
                        <form action="{{ route('institution.invites.accept.token', $invite->token) }}" method="POST"
                            class="inline">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-2 px-5 py-3 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all text-sm duo-button-primary">
                                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">check</span> Aceitar
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>