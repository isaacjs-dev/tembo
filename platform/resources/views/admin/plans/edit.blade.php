<x-app-layout>

    @php
        $getLimit = fn($key) => $plan->planLimits->firstWhere('resource_key', $key)?->limit_value;
        $getFeature = fn($key) => $plan->planFeatures->firstWhere('feature_key', $key)?->enabled ?? false;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="{{ route('admin.plans.index') }}">Planos</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar: {{ $plan->name }}</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Plano</h1>
                <p class="text-gray-400 mt-1">Altere as configurações, preços e limites deste plano.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.plans.index') }}"
                    class="flex items-center gap-2 px-6 py-4 bg-white border-2 border-duo-border text-gray-600 font-extrabold rounded-xl transition-all uppercase tracking-wider text-sm whitespace-nowrap hover:border-gray-400">
                    Cancelar
                </a>
                <button type="submit" form="plan-form"
                    class="flex items-center gap-2 px-6 py-4 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all uppercase tracking-wider text-sm whitespace-nowrap duo-button-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">save</span>
                    Salvar Alterações
                </button>
            </div>
        </div>
    </x-slot>

    <form id="plan-form" action="{{ route('admin.plans.update', $plan) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Card: Informações Básicas --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">info</span> Informações Básicas
                </h2>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nome do Plano
                            *</label>
                        <input type="text" name="name" value="{{ old('name', $plan->name) }}" required
                            aria-label="Nome do plano"
                            placeholder="Ex: Premium Gold"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                        @error('name') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Público-Alvo
                            *</label>
                        <select name="target_audience" required
                            aria-label="Público-alvo"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                            <option value="both" {{ old('target_audience', $plan->target_audience) == 'both' ? 'selected' : '' }}>Professor e Instituição</option>
                            <option value="teacher" {{ old('target_audience', $plan->target_audience) == 'teacher' ? 'selected' : '' }}>Apenas Professor</option>
                            <option value="institution" {{ old('target_audience', $plan->target_audience) == 'institution' ? 'selected' : '' }}>Apenas Instituição</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Preço
                            Original (R$) *</label>
                        <input type="number" name="original_price"
                            aria-label="Preço original em reais"
                            value="{{ old('original_price', $plan->original_price) }}" step="0.01" min="0" required
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Ordenação</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order) }}"
                            aria-label="Ordenação"
                            min="0"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nível/Tier</label>
                        <input type="number" name="tier_level" value="{{ old('tier_level', $plan->tier_level) }}"
                            aria-label="Nível ou tier"
                            min="0"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                        <p class="text-[11px] text-gray-400 mt-1">Usado para comparação de planos (0 = menor)</p>
                    </div>
                </div>
                {{-- Configurações de Status --}}
                <div class="bg-background-light border-2 border-duo-border rounded-xl p-5">
                    <h3 class="text-sm font-extrabold text-gray-600 mb-3">Configurações de Status</h3>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between cursor-pointer">
                            <div>
                                <span class="text-sm font-bold text-gray-700">Visível na Home</span>
                                <p class="text-xs text-gray-400">Exibir este plano para novos assinantes</p>
                            </div>
                            <input type="hidden" name="is_visible" value="0">
                            <input type="checkbox" name="is_visible" value="1" {{ old('is_visible', $plan->is_visible) ? 'checked' : '' }}
                                class="w-10 h-5 rounded-full bg-gray-200 checked:bg-primary border-0 cursor-pointer transition-colors">
                        </label>
                        <label class="flex items-center justify-between cursor-pointer">
                            <div>
                                <span class="text-sm font-bold text-gray-700">Mais Popular</span>
                                <p class="text-xs text-gray-400">Destacar como plano recomendado</p>
                            </div>
                            <input type="hidden" name="is_most_popular" value="0">
                            <input type="checkbox" name="is_most_popular" value="1" {{ old('is_most_popular', $plan->is_most_popular) ? 'checked' : '' }}
                                class="w-10 h-5 rounded-full bg-gray-200 checked:bg-primary border-0 cursor-pointer transition-colors">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card: Promoção --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">sell</span> Preço e Promoção
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Preço
                            Promocional (R$)</label>
                        <input type="number" name="promotional_price"
                            aria-label="Preço promocional em reais"
                            value="{{ old('promotional_price', $plan->promotional_price) }}" step="0.01" min="0"
                            placeholder="Deixe vazio se não houver"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Início da
                            Promoção</label>
                        <input type="datetime-local" name="promo_starts_at"
                            aria-label="Início da promoção"
                            value="{{ old('promo_starts_at', $plan->promo_starts_at?->format('Y-m-d\TH:i')) }}"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Fim da
                            Promoção</label>
                        <input type="datetime-local" name="promo_ends_at"
                            aria-label="Fim da promoção"
                            value="{{ old('promo_ends_at', $plan->promo_ends_at?->format('Y-m-d\TH:i')) }}"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                </div>
            </div>
        </div>

        {{-- Card: Limites --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">tune</span> Limites do Plano
                </h2>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-400 font-medium">Deixe em branco para ilimitado.</p>
                @php
                    $limitsConfig = [
                        'max_students' => ['label' => 'Máximo de Alunos', 'desc' => 'Total de registros permitidos', 'icon' => 'group'],
                        'max_teachers' => ['label' => 'Máximo de Professores', 'desc' => 'Total de professores vinculados', 'icon' => 'school'],
                        'max_classes' => ['label' => 'Máximo de Turmas', 'desc' => 'Turmas ativas simultâneas', 'icon' => 'class'],
                        'max_exams' => ['label' => 'Máximo de Provas', 'desc' => 'Avaliações criadas por período', 'icon' => 'quiz'],
                    ];
                @endphp
                @foreach($limitsConfig as $key => $cfg)
                    <div
                        class="flex items-center justify-between bg-background-light border-2 border-duo-border rounded-xl px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">{{ $cfg['icon'] }}</span>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-gray-700">{{ $cfg['label'] }}</div>
                                <div class="text-xs text-gray-400">{{ $cfg['desc'] }}</div>
                            </div>
                        </div>
                        <input type="number" name="limits[{{ $key }}]" value="{{ old("limits.$key", $getLimit($key)) }}"
                            aria-label="{{ $cfg['label'] }}"
                            min="0" placeholder="∞"
                            class="w-24 text-center px-3 py-2 bg-white border-2 border-duo-border rounded-xl text-sm font-bold focus:border-primary focus:ring-0 transition-colors">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Card: Features --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-8">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">verified</span> Recursos Habilitados
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @php
                        $featuresConfig = [
                            'export_pdf' => ['label' => 'Exportar PDF', 'icon' => 'picture_as_pdf'],
                            'omr' => ['label' => 'Correção OMR', 'icon' => 'document_scanner'],
                            'sharing' => ['label' => 'Compartilhamento', 'icon' => 'share'],
                            'certificates' => ['label' => 'Certificados', 'icon' => 'workspace_premium'],
                        ];
                    @endphp
                    @foreach($featuresConfig as $key => $cfg)
                        <label
                            class="flex items-center gap-3 bg-background-light border-2 border-duo-border rounded-xl px-5 py-4 cursor-pointer hover:border-primary transition-colors">
                            <input type="hidden" name="features[{{ $key }}]" value="0">
                            <input type="checkbox" name="features[{{ $key }}]" value="1" {{ old("features.$key", $getFeature($key)) ? 'checked' : '' }}
                                class="w-5 h-5 rounded-lg border-2 border-gray-300 text-primary focus:ring-primary">
                            <span aria-hidden="true" class="material-symbols-outlined text-primary text-[20px]">{{ $cfg['icon'] }}</span>
                            <span class="text-sm font-bold text-gray-700">{{ $cfg['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
