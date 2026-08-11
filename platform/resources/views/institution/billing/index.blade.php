<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Plano & Cobrança</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Plano & Cobrança</h1>
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

    @if ($errors->any())
        <div
            class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
            @foreach ($errors->all() as $error)
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Plano Atual --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border-2 border-duo-border p-8">
            <h2 class="text-lg font-extrabold text-gray-700 mb-6 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">credit_card</span>
                Plano Atual
            </h2>

            @if ($activeSubscription)
                <div class="flex items-center gap-4 mb-6">
                    <div class="size-14 rounded-2xl bg-primary/10 flex items-center justify-center">
                        <span aria-hidden="true" class="material-symbols-outlined text-3xl text-primary">workspace_premium</span>
                    </div>
                    <div>
                        <h3 class="text-2xl font-extrabold text-gray-800">{{ $activeSubscription->plan->name }}</h3>
                        <span
                            class="inline-flex px-3 py-1 bg-green-50 text-green-700 rounded-full text-xs font-bold uppercase">
                            {{ $activeSubscription->status }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-background-light rounded-xl">
                        <p class="text-xs text-gray-400 font-bold uppercase">Professores</p>
                        <p class="text-2xl font-extrabold text-gray-800">{{ $usage['teachers'] }} <span
                                class="text-sm text-gray-400">/ {{ $limits['max_teachers'] ?? '∞' }}</span></p>
                    </div>
                    <div class="p-4 bg-background-light rounded-xl">
                        <p class="text-xs text-gray-400 font-bold uppercase">Alunos</p>
                        <p class="text-2xl font-extrabold text-gray-800">{{ $usage['students'] }} <span
                                class="text-sm text-gray-400">/ {{ $limits['max_students'] ?? '∞' }}</span></p>
                    </div>
                    <div class="p-4 bg-background-light rounded-xl">
                        <p class="text-xs text-gray-400 font-bold uppercase">Turmas</p>
                        <p class="text-2xl font-extrabold text-gray-800">{{ $usage['classes'] }} <span
                                class="text-sm text-gray-400">/ {{ $limits['max_classes'] ?? '∞' }}</span></p>
                    </div>
                </div>

                @if ($activeSubscription->starts_at)
                    <p class="text-sm text-gray-400">
                        <strong>Ativo desde:</strong> {{ $activeSubscription->starts_at->format('d/m/Y') }}
                    </p>
                @endif
                @if ($activeSubscription->expires_at)
                    <p class="text-sm text-orange-500 font-bold mt-1">
                        Expira em: {{ $activeSubscription->expires_at->format('d/m/Y') }}
                    </p>
                @endif
            @else
                <div class="text-center p-8">
                    <span aria-hidden="true" class="material-symbols-outlined text-6xl text-gray-300 mb-4">credit_card_off</span>
                    <p class="text-gray-500 font-medium">Nenhum plano ativo.</p>
                </div>
            @endif
        </div>

        {{-- Ações --}}
        <div class="space-y-4">
            <div class="bg-white rounded-2xl border-2 border-duo-border p-6">
                <h3 class="text-sm font-extrabold text-gray-700 mb-3">Trocar Plano</h3>
                <form action="{{ route('institution.billing.changePlan') }}" method="POST" class="space-y-3">
                    @csrf
                    <select name="plan_id"
                        class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary font-medium">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" {{ $activeSubscription?->plan_id == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} - R$ {{ number_format($plan->price, 2, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="w-full duo-button-primary px-4 py-3 rounded-xl font-extrabold text-sm uppercase">
                        Alterar Plano
                    </button>
                </form>
            </div>

            @if ($activeSubscription && $activeSubscription->status !== 'canceled')
                <div class="bg-white rounded-2xl border-2 border-red-100 p-6" x-data="{ showCancel: false }">
                    <h3 class="text-sm font-extrabold text-red-600 mb-2">Cancelar Plano</h3>
                    <p class="text-xs text-gray-400 mb-3">O cancelamento concede 30 dias de carencia.</p>
                    <button @click="showCancel = !showCancel" class="text-red-500 font-bold text-sm hover:underline">
                        Desejo cancelar...
                    </button>
                    <form x-show="showCancel" x-cloak action="{{ route('institution.billing.cancelPlan') }}" method="POST"
                        class="mt-3 space-y-2">
                        @csrf
                        <label class="text-xs text-gray-500 font-medium">
                            Digite o nome da instituicao para confirmar:
                        </label>
                        <input type="text" name="confirmation"
                            class="block w-full px-4 py-3 bg-red-50 border-2 border-red-200 rounded-xl text-red-700 focus:outline-none focus:border-red-400 font-medium text-sm"
                            placeholder="{{ $organization->name }}">
                        <button type="submit"
                            class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl font-extrabold text-sm uppercase transition-colors">
                            Confirmar Cancelamento
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
