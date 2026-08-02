<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Planos</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Gestão de Planos</h1>
                <p class="text-gray-400 mt-1">Configure e gerencie modelos de assinatura, preços e limites.</p>
            </div>
            <a href="{{ route('admin.plans.create') }}"
                class="flex items-center gap-2 px-6 py-4 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all uppercase tracking-wider text-sm whitespace-nowrap duo-button-primary">
                <span aria-hidden="true" class="material-symbols-outlined">add_circle</span>
                Criar novo plano
            </a>
        </div>
    </x-slot>

    @if(session('status'))
        <div role="status" aria-live="polite"
            class="flex items-center gap-3 bg-green-50 border-2 border-green-200 text-green-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm">
            <span aria-hidden="true" class="material-symbols-outlined text-green-500">check_circle</span>
            {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert"
            class="flex items-center gap-3 bg-red-50 border-2 border-red-200 text-red-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm">
            <span aria-hidden="true" class="material-symbols-outlined text-red-500">error</span>
            {{ session('error') }}
        </div>
    @endif

    {{-- Table Card --}}
    <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-12">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <caption class="sr-only">Planos de assinatura cadastrados</caption>
                <thead class="bg-background-light border-b-2 border-duo-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">#</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Plano</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Público</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Preço</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Promoção</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">
                            Visível</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">
                            Popular</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Tier
                        </th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y-2 divide-duo-border">
                    @forelse($plans as $plan)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-400 font-medium">{{ $plan->sort_order }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="size-10 rounded-xl border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">workspace_premium</span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-gray-900">{{ $plan->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $plan->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $audienceMap = [
                                        'teacher' => ['label' => 'Professor', 'color' => 'blue'],
                                        'institution' => ['label' => 'Instituição', 'color' => 'purple'],
                                        'both' => ['label' => 'Ambos', 'color' => 'gray'],
                                    ];
                                    $aud = $audienceMap[$plan->target_audience] ?? ['label' => $plan->target_audience, 'color' => 'gray'];
                                @endphp
                                <span
                                    class="px-3 py-1 bg-{{ $aud['color'] }}-100 text-{{ $aud['color'] }}-600 rounded-full text-xs font-extrabold uppercase tracking-tight border border-{{ $aud['color'] }}-200">
                                    {{ $aud['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-900">R$
                                    {{ number_format($plan->original_price, 2, ',', '.') }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($plan->promotional_price && $plan->promo_ends_at?->isFuture())
                                    <div>
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-extrabold border border-green-200">
                                            R$ {{ number_format($plan->promotional_price, 2, ',', '.') }}
                                        </span>
                                        <div class="text-[10px] text-gray-400 mt-1">até
                                            {{ $plan->promo_ends_at->format('d/m/Y') }}</div>
                                    </div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($plan->is_visible)
                                    <span
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-extrabold border border-green-200">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">visibility</span> Sim
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-xs font-extrabold border border-gray-200">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">visibility_off</span> Não
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($plan->is_most_popular)
                                    <span
                                        class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-extrabold border border-yellow-200">⭐
                                        Popular</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center justify-center size-8 bg-gray-100 rounded-lg text-xs font-bold text-gray-600">{{ $plan->tier_level }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.plans.edit', $plan) }}"
                                        class="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-all"
                                        title="Editar">
                                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                    <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Deseja excluir este plano?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                            title="Excluir">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-[48px] text-gray-300 mb-3">inbox</span>
                                <h3 class="text-xl font-bold text-gray-800">Nenhum plano cadastrado.</h3>
                                <p class="text-gray-500 mt-2">Clique em "Criar novo plano" para adicionar o primeiro.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
