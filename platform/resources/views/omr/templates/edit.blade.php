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
                    <span class="current">{{ $template->name }}</span>
                </nav>
                <h1 class="page-title">{{ $template->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('institution.omr.templates.export', $template->id) }}" target="_blank"
                    class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">download</span>
                    Exportar JSON
                </a>
                <form action="{{ route('institution.omr.templates.destroy', $template->id) }}" method="POST"
                    onsubmit="return confirm('Excluir este template? Scans vinculados perderão referência.')">
                    @csrf @method('DELETE')
                    <button class="btn-danger btn-sm text-xs uppercase tracking-wider">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">delete</span>
                        Excluir
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-12">

        {{-- Coluna Esquerda: Configurações --}}
        <div class="space-y-6">

            {{-- Info geral + thresholds --}}
            <div class="card p-6">
                <h3 class="font-extrabold text-lg text-duo-heading mb-4 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary">tune</span>
                    Configurações do Template
                </h3>
                <form action="{{ route('institution.omr.templates.update', $template->id) }}" method="POST"
                    class="space-y-4">
                    @csrf @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="input-label">Nome *</label>
                            <input type="text" name="name" class="input-field" required
                                value="{{ old('name', $template->name) }}">
                        </div>
                        <div>
                            <label class="input-label">Largura warped (px)</label>
                            <input type="number" name="width" class="input-field" required min="100" max="5000"
                                value="{{ old('width', $template->width) }}">
                        </div>
                        <div>
                            <label class="input-label">Altura warped (px)</label>
                            <input type="number" name="height" class="input-field" required min="100" max="7000"
                                value="{{ old('height', $template->height) }}">
                        </div>
                        <div>
                            <label class="input-label">Total de Questões</label>
                            <input type="number" name="total_questions" class="input-field" required min="1" max="200"
                                value="{{ old('total_questions', $template->total_questions) }}">
                        </div>
                        <div>
                            <label class="input-label">Total de Páginas</label>
                            <input type="number" name="total_pages" class="input-field" min="1" max="10"
                                value="{{ old('total_pages', $template->total_pages) }}">
                        </div>
                        <div>
                            <label class="input-label">Colunas</label>
                            <input type="number" name="columns" class="input-field" min="1" max="10"
                                value="{{ old('columns', $template->columns) }}">
                        </div>
                        <div>
                            <label class="input-label">Linhas por Coluna</label>
                            <input type="number" name="rows_per_column" class="input-field" min="1" max="50"
                                value="{{ old('rows_per_column', $template->rows_per_column) }}">
                        </div>
                        <div>
                            <label class="input-label">Máx. Alternativas</label>
                            <input type="number" name="max_options" class="input-field" min="2" max="10"
                                value="{{ old('max_options', $template->max_options) }}">
                        </div>
                    </div>

                    <p class="text-sm font-bold text-gray-600 mt-2">Thresholds de Leitura (0.0–1.0)</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Marcado (≥)</label>
                            <input type="number" step="0.01" name="threshold_mark" class="input-field !py-1.5" min="0"
                                max="1" value="{{ old('threshold_mark', $template->thresholds_json['mark'] ?? 0.5) }}">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Em Branco (≤)</label>
                            <input type="number" step="0.01" name="threshold_blank" class="input-field !py-1.5" min="0"
                                max="1"
                                value="{{ old('threshold_blank', $template->thresholds_json['blank'] ?? 0.15) }}">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Incerto Mín.</label>
                            <input type="number" step="0.01" name="threshold_uncertain_low" class="input-field !py-1.5"
                                min="0" max="1"
                                value="{{ old('threshold_uncertain_low', $template->thresholds_json['uncertain_low'] ?? 0.15) }}">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Incerto Máx.</label>
                            <input type="number" step="0.01" name="threshold_uncertain_high" class="input-field !py-1.5"
                                min="0" max="1"
                                value="{{ old('threshold_uncertain_high', $template->thresholds_json['uncertain_high'] ?? 0.5) }}">
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit" class="btn-primary btn-sm text-xs uppercase tracking-wider">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">save</span>
                            Salvar Configurações
                        </button>
                    </div>
                </form>
            </div>

            {{-- Corner Points --}}
            <div class="card p-6">
                <h3 class="font-extrabold text-lg text-duo-heading mb-4 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary">crop_free</span>
                    Pontos de Canto (Fiduciais)
                </h3>
                <form action="{{ route('institution.omr.templates.update', $template->id) }}" method="POST"
                    class="space-y-4">
                    @csrf @method('PUT')
                    {{-- Reutilizar campos obrigatórios do update --}}
                    <input type="hidden" name="name" value="{{ $template->name }}">
                    <input type="hidden" name="width" value="{{ $template->width }}">
                    <input type="hidden" name="height" value="{{ $template->height }}">
                    <input type="hidden" name="total_questions" value="{{ $template->total_questions }}">
                    <input type="hidden" name="threshold_mark" value="{{ $template->thresholds_json['mark'] ?? 0.5 }}">
                    <input type="hidden" name="threshold_blank"
                        value="{{ $template->thresholds_json['blank'] ?? 0.15 }}">
                    <input type="hidden" name="threshold_uncertain_low"
                        value="{{ $template->thresholds_json['uncertain_low'] ?? 0.15 }}">
                    <input type="hidden" name="threshold_uncertain_high"
                        value="{{ $template->thresholds_json['uncertain_high'] ?? 0.5 }}">

                    @php
                        $corners = $template->corner_points_json ?? [];
                        $cornerDefs = ['tl' => ['label' => 'TL — Superior Esq.', 'key' => 'TL'], 'tr' => ['label' => 'TR — Superior Dir.', 'key' => 'TR'], 'bl' => ['label' => 'BL — Inferior Esq.', 'key' => 'BL'], 'br' => ['label' => 'BR — Inferior Dir.', 'key' => 'BR']];
                    @endphp
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($cornerDefs as $id => $def)
                            @php $pt = $corners[$def['key']] ?? [0, 0]; @endphp
                            <div class="p-3 bg-gray-50 rounded-xl border border-duo-border">
                                <p class="text-xs font-bold text-gray-600 mb-2">{{ $def['label'] }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-400">X</label>
                                        <input type="number" step="0.1" name="corner_{{ $id }}_x"
                                            class="input-field !py-1.5 text-sm" value="{{ $pt[0] }}" required>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-400">Y</label>
                                        <input type="number" step="0.1" name="corner_{{ $id }}_y"
                                            class="input-field !py-1.5 text-sm" value="{{ $pt[1] }}" required>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-secondary btn-sm text-xs uppercase tracking-wider">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">save</span>
                            Salvar Cantos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Coluna Direita: ROIs e questões --}}
        <div class="space-y-6">

            {{-- Gerador de ROIs --}}
            <div class="card p-6">
                <h3 class="font-extrabold text-lg text-duo-heading mb-1 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary">grid_on</span>
                    Gerar ROIs Automaticamente
                </h3>
                <p class="text-sm text-gray-400 mb-4">Preencha as medidas do grid para gerar as regiões de interesse de
                    todas as questões de uma vez.</p>
                <form action="{{ route('institution.omr.templates.generateRois', $template->id) }}" method="POST"
                    class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600">Início X (px) *</label>
                            <input type="number" step="0.1" name="grid_start_x" class="input-field !py-1.5 text-sm"
                                value="{{ old('grid_start_x', 370) }}" required>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Início Y (px) *</label>
                            <input type="number" step="0.1" name="grid_start_y" class="input-field !py-1.5 text-sm"
                                value="{{ old('grid_start_y', 333) }}" required>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Espaço entre Colunas (px) *</label>
                            <input type="number" step="0.1" name="col_spacing" class="input-field !py-1.5 text-sm"
                                value="{{ old('col_spacing', 514) }}" required>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Espaço entre Linhas (px) *</label>
                            <input type="number" step="0.1" name="row_spacing" class="input-field !py-1.5 text-sm"
                                value="{{ old('row_spacing', 116) }}" required>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Largura da Bolha (px) *</label>
                            <input type="number" step="0.1" name="bubble_w" class="input-field !py-1.5 text-sm"
                                value="{{ old('bubble_w', 50) }}" required>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600">Altura da Bolha (px) *</label>
                            <input type="number" step="0.1" name="bubble_h" class="input-field !py-1.5 text-sm"
                                value="{{ old('bubble_h', 50) }}" required>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-gray-600">Espaço entre Opções (px) *</label>
                            <input type="number" step="0.1" name="option_spacing" class="input-field !py-1.5 text-sm"
                                value="{{ old('option_spacing', 71) }}" required>
                        </div>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700">
                        <strong>⚠ Atenção:</strong> Esta ação irá substituir todas as
                        {{ $template->questions_count ?? 0 }} questões/ROIs existentes.
                    </div>
                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-primary btn-sm text-xs uppercase tracking-wider"
                            onclick="return confirm('Gerar ROIs para este template? As questões existentes serão substituídas.')">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">auto_fix_high</span>
                            Gerar {{ $template->total_questions }} ROIs
                        </button>
                    </div>
                </form>
            </div>

            {{-- Lista de questões/ROIs --}}
            <div class="card p-6">
                <h3 class="font-extrabold text-lg text-duo-heading mb-4 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary">numbers</span>
                    Questões e ROIs
                    <span
                        class="badge bg-gray-100 text-gray-600 ml-1">{{ $template->questions->count() }}/{{ $template->total_questions }}</span>
                </h3>

                @if($template->questions->isEmpty())
                    <div class="text-center py-8">
                        <span aria-hidden="true" class="material-symbols-outlined text-4xl text-gray-300 block mb-2">grid_off</span>
                        <p class="text-sm text-gray-400">Nenhuma questão configurada. Use o gerador acima.</p>
                    </div>
                @else
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="text-xs">
                            <caption class="sr-only">Regiões de leitura configuradas no template OMR</caption>
                            <thead>
                                <tr>
                                    <th class="py-2">Q#</th>
                                    <th>Opções</th>
                                    <th>ROIs (A)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($template->questions->sortBy('question_number') as $q)
                                    <tr>
                                        <td class="font-bold text-primary">Q{{ $q->question_number }}</td>
                                        <td>{{ implode(', ', $q->option_labels_json ?? []) }}</td>
                                        <td class="font-mono text-gray-400">
                                            @php $roiA = $q->rois_json['A'] ?? null; @endphp
                                            @if($roiA)
                                                x={{ $roiA['x'] }}, y={{ $roiA['y'] }}, w={{ $roiA['w'] }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
