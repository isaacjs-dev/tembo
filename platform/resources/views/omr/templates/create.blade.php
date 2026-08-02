<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.templates.index') }}">Templates</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Novo Template</span>
                </nav>
                <h1 class="page-title">Novo Template OMR</h1>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <div class="card p-8 mb-12">
            <form action="{{ route('institution.omr.templates.store') }}" method="POST" class="space-y-8">
                @csrf

                {{-- Informações Gerais --}}
                <div>
                    <h3 class="font-extrabold text-duo-heading mb-4 flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">info</span>
                        Identificação
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="input-label">Nome do Template *</label>
                            <input type="text" name="name" class="input-field" required
                                placeholder="Ex: Cartão A4 60 Questões 5 Alternativas" value="{{ old('name') }}">
                            @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Papel --}}
                <div class="border-t-2 border-duo-border pt-6">
                    <h3 class="font-extrabold text-duo-heading mb-4 flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">article</span>
                        Dimensões do Papel
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label">Formato *</label>
                            <select name="paper_size" class="input-field" required>
                                <option value="A4" {{ old('paper_size') === 'A4' ? 'selected' : '' }}>A4</option>
                                <option value="A3" {{ old('paper_size') === 'A3' ? 'selected' : '' }}>A3</option>
                                <option value="Letter" {{ old('paper_size') === 'Letter' ? 'selected' : '' }}>Letter
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="input-label">Orientação *</label>
                            <select name="orientation" class="input-field" required>
                                <option value="portrait" {{ old('orientation') === 'portrait' ? 'selected' : '' }}>Retrato
                                </option>
                                <option value="landscape" {{ old('orientation') === 'landscape' ? 'selected' : '' }}>
                                    Paisagem</option>
                            </select>
                        </div>
                        <div>
                            <label class="input-label">Largura (px, warped) *</label>
                            <input type="number" name="width" class="input-field" required min="100" max="5000"
                                value="{{ old('width', 1000) }}" placeholder="1000">
                            <p class="text-xs text-gray-400 mt-1">Largura da imagem retificada em pixels.</p>
                        </div>
                        <div>
                            <label class="input-label">Altura (px, warped) *</label>
                            <input type="number" name="height" class="input-field" required min="100" max="7000"
                                value="{{ old('height', 1414) }}" placeholder="1414">
                        </div>
                    </div>
                </div>

                {{-- Grid --}}
                <div class="border-t-2 border-duo-border pt-6">
                    <h3 class="font-extrabold text-duo-heading mb-4 flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">grid_on</span>
                        Layout da Grade de Bolhas
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label">Total de Questões *</label>
                            <input type="number" name="total_questions" class="input-field" required min="1" max="200"
                                value="{{ old('total_questions', 60) }}">
                        </div>
                        <div>
                            <label class="input-label">Total de Páginas *</label>
                            <input type="number" name="total_pages" class="input-field" required min="1" max="10"
                                value="{{ old('total_pages', 1) }}">
                        </div>
                        <div>
                            <label class="input-label">Colunas *</label>
                            <input type="number" name="columns" class="input-field" required min="1" max="10"
                                value="{{ old('columns', 4) }}">
                        </div>
                        <div>
                            <label class="input-label">Linhas por Coluna *</label>
                            <input type="number" name="rows_per_column" class="input-field" required min="1" max="50"
                                value="{{ old('rows_per_column', 15) }}">
                        </div>
                        <div>
                            <label class="input-label">Máx. Alternativas por Questão *</label>
                            <input type="number" name="max_options" class="input-field" required min="2" max="10"
                                value="{{ old('max_options', 5) }}">
                        </div>
                    </div>
                </div>

                {{-- Corner Points --}}
                <div class="border-t-2 border-duo-border pt-6">
                    <h3 class="font-extrabold text-duo-heading mb-4 flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">crop_free</span>
                        Pontos de Canto (Fiduciais)
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">Posição central dos 4 marcadores pretos no <strong>espaço
                            warped</strong> (em pixels, usando as dimensões acima).</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach(['tl' => 'Superior Esquerdo (TL)', 'tr' => 'Superior Direito (TR)', 'bl' => 'Inferior Esquerdo (BL)', 'br' => 'Inferior Direito (BR)'] as $key => $label)
                            <div class="p-4 bg-gray-50 rounded-xl border border-duo-border">
                                <p class="text-xs font-bold text-gray-600 mb-3">{{ $label }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-500">X (px)</label>
                                        <input type="number" step="0.1" name="corner_{{ $key }}_x"
                                            class="input-field !py-1.5 text-sm" required
                                            value="{{ old('corner_' . $key . '_x') }}" placeholder="0">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500">Y (px)</label>
                                        <input type="number" step="0.1" name="corner_{{ $key }}_y"
                                            class="input-field !py-1.5 text-sm" required
                                            value="{{ old('corner_' . $key . '_y') }}" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Thresholds --}}
                <div class="border-t-2 border-duo-border pt-6">
                    <h3 class="font-extrabold text-duo-heading mb-4 flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">tune</span>
                        Thresholds de Leitura
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">Fração de preenchimento (0.0–1.0) usada para classificar cada
                        bolha.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="input-label">Marcado (≥ este valor) *</label>
                            <input type="number" step="0.01" name="threshold_mark" class="input-field" required min="0"
                                max="1" value="{{ old('threshold_mark', 0.5) }}">
                        </div>
                        <div>
                            <label class="input-label">Em Branco (≤ este valor) *</label>
                            <input type="number" step="0.01" name="threshold_blank" class="input-field" required min="0"
                                max="1" value="{{ old('threshold_blank', 0.15) }}">
                        </div>
                        <div>
                            <label class="input-label">Incerto — Mínimo *</label>
                            <input type="number" step="0.01" name="threshold_uncertain_low" class="input-field" required
                                min="0" max="1" value="{{ old('threshold_uncertain_low', 0.15) }}">
                        </div>
                        <div>
                            <label class="input-label">Incerto — Máximo *</label>
                            <input type="number" step="0.01" name="threshold_uncertain_high" class="input-field"
                                required min="0" max="1" value="{{ old('threshold_uncertain_high', 0.5) }}">
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">
                        <strong>Dica:</strong> Após criar, use o comando <code
                            class="bg-gray-100 px-1 rounded">php artisan omr:calibrate</code> para ajustar
                        automaticamente os thresholds com amostras reais.
                    </p>
                </div>

                <div class="pt-4 border-t-2 border-duo-border flex justify-end gap-3">
                    <a href="{{ route('institution.omr.templates.index') }}" class="btn-secondary btn-sm">Cancelar</a>
                    <button type="submit" class="btn-primary btn-sm text-xs uppercase tracking-wider">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">save</span>
                        Criar Template e Configurar ROIs
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>