<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Lixeira Global</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Lixeira Global</h1>
                <p class="text-sm text-gray-400 mt-1">Gerencie itens da administração global (SaaS) excluídos recentemente. Restaure ou exclua permanentemente.</p>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div
            class="mb-6 p-4 bg-green-50 border-2 border-green-200 rounded-xl text-green-700 font-medium text-sm flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    {{-- Usuários na Lixeira --}}
    <div class="mb-8">
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-primary">group</span>
            Usuários Excluídos
        </h2>

        @if ($trashedUsers->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center flex flex-col items-center justify-center">
                <span aria-hidden="true" class="material-symbols-outlined text-4xl text-gray-300 mb-2">sentiment_very_satisfied</span>
                <p class="text-gray-400 font-medium">Nenhum Usuário na lixeira. Tudo limpo por aqui.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedUsers as $u)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">
                                {{ $u->name }} <span class="text-xs text-gray-400 ml-1">({{ $u->email }})</span>
                            </p>
                            <p class="text-xs text-gray-400">Excluído em {{ $u->deleted_at->format('d/m/Y H:i') }} | Perfil: {{ $u->type }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('admin.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="user">
                                <input type="hidden" name="model_id" value="{{ $u->id }}">
                                <button type="submit" class="text-primary font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('admin.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('Excluir permanentemente este usuário? Esta ação não pode ser desfeita e pode quebrar integridade legada.')">
                                @csrf
                                <input type="hidden" name="model_type" value="user">
                                <input type="hidden" name="model_id" value="{{ $u->id }}">
                                <button type="submit" class="text-red-500 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedUsers->links() }}</div>
        @endif
    </div>

    {{-- Planos na Lixeira --}}
    <div>
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-primary">diamond</span>
            Planos/Pacotes Excluídos
        </h2>

        @if ($trashedPlans->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center flex flex-col items-center justify-center">
                <span aria-hidden="true" class="material-symbols-outlined text-4xl text-gray-300 mb-2">sentiment_very_satisfied</span>
                <p class="text-gray-400 font-medium">Nenhum Plano na lixeira. Tudo limpo por aqui.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedPlans as $p)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">{{ $p->name }} <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md text-[10px] ml-2">{{ $p->target_audience }}</span></p>
                            <p class="text-xs text-gray-400">Excluído em {{ $p->deleted_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('admin.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="plan">
                                <input type="hidden" name="model_id" value="{{ $p->id }}">
                                <button type="submit" class="text-primary font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('admin.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('ATENÇÃO: Excluir um plano permanentemente pode quebrar assinaturas legadas. Excluir mesmo assim?')">
                                @csrf
                                <input type="hidden" name="model_type" value="plan">
                                <input type="hidden" name="model_id" value="{{ $p->id }}">
                                <button type="submit" class="text-red-500 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedPlans->links() }}</div>
        @endif
    </div>
</x-app-layout>
