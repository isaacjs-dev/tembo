<x-app-layout>
    <x-slot name="header">
        <div class="page-header !mb-0 !pb-0">
            <div class="flex flex-col gap-1">
                <nav class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-duo-text/60">
                    <a href="{{ route('institution.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[14px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}" class="hover:text-primary transition-colors">OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[14px]">chevron_right</span>
                    <span class="text-primary/80">Conferência Manual</span>
                </nav>
                <h1 class="text-3xl font-black tracking-tight text-duo-heading flex items-center gap-3">
                    Conferência do Gabarito
                    <span class="text-duo-text/20 font-light text-2xl">|</span>
                    <span class="text-xl font-medium text-duo-text/80 truncate max-w-md">{{ $scan->exam->title ?? 'N/A' }}</span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                @if(in_array($scan->status, ['pending', 'review', 'synced']))
                    <form action="{{ route('institution.omr.reject', $scan->id) }}" method="POST"
                        onsubmit="return confirm('Tem certeza que deseja rejeitar este scan?');">
                        @csrf
                        <button class="flex items-center gap-2 px-4 py-2 rounded-xl border-2 border-red-100 text-red-600 hover:bg-red-50 hover:border-red-200 transition-all font-bold text-sm uppercase tracking-wider">
                            <span aria-hidden="true" class="material-symbols-outlined text-[20px]">delete_sweep</span> Rejeitar Scan
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
        <!-- Image Preview Section (Lado Esquerdo) -->
        <div class="lg:col-span-7 space-y-6">
            <div class="card !p-0 overflow-hidden border-none shadow-2xl shadow-primary/5 bg-white/80 backdrop-blur-xl">
                <div class="p-6 border-b border-duo-border/50 flex items-center justify-between bg-gradient-to-r from-background-light to-transparent">
                    <h3 class="font-black text-xl text-duo-heading flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary">
                            <span aria-hidden="true" class="material-symbols-outlined text-[24px]">image</span>
                        </div>
                        Imagem Digitalizada
                    </h3>
                    
                    <div class="flex items-center gap-2">
                        @if($scan->status === 'pending' || $scan->status === 'processing')
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-[11px] font-black uppercase tracking-widest border border-amber-200 animate-pulse">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                Processando
                            </div>
                        @elseif($scan->quality_json && ($scan->quality_json['needs_review'] ?? false))
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-50 text-red-700 text-[11px] font-black uppercase tracking-widest border border-red-100">
                                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">warning</span>
                                Revisão Necessária
                            </div>
                        @else
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-black uppercase tracking-widest border border-emerald-100">
                                <span aria-hidden="true" class="material-symbols-outlined text-[14px]">verified</span>
                                Scan Confiável
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Qualidade do scan com visual de Dashboard --}}
                @if($scan->quality_json)
                    <div class="p-4 grid grid-cols-4 gap-4 bg-duo-border/10 border-b border-duo-border/50">
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-duo-text/50">Alinhamento</span>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-duo-border/50 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary rounded-full" style="width: {{ (($scan->quality_json['corners_found'] ?? 0) / 4) * 100 }}%"></div>
                                </div>
                                <span class="text-xs font-black text-duo-heading">{{ $scan->quality_json['corners_found'] ?? '?' }}/4</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1 text-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-duo-text/50">Confiança</span>
                            <span class="text-xs font-black text-emerald-600">{{ $scan->quality_json['ok_count'] ?? 0 }} OK</span>
                        </div>
                        <div class="flex flex-col gap-1 text-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-duo-text/50">Incertas</span>
                            <span class="text-xs font-black {{ ($scan->quality_json['uncertain_count'] ?? 0) > 0 ? 'text-amber-500' : 'text-duo-text/30' }}">
                                {{ $scan->quality_json['uncertain_count'] ?? 0 }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1 text-center">
                            <span class="text-[9px] font-black uppercase tracking-widest text-duo-text/50">Duplas</span>
                            <span class="text-xs font-black {{ ($scan->quality_json['double_count'] ?? 0) > 0 ? 'text-red-500' : 'text-duo-text/30' }}">
                                {{ $scan->quality_json['double_count'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                @endif

            {{-- Toggle: original vs warped vs overlay --}}
            <div class="flex flex-col gap-3 mb-3" x-data="{ view: '{{ $scan->warped_path ? "warped" : "original" }}' }">
                <div class="flex flex-wrap gap-2">
                    <button @click="view='original'"
                        :class="view==='original' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'"
                        type="button">Original</button>
                    @if($scan->warped_path)
                        <button @click="view='warped'"
                            :class="view==='warped' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'"
                            type="button">Warped</button>
                    @endif
                    @if($scan->debug_path)
                        <button @click="view='overlay'"
                            :class="view==='overlay' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'" type="button">Overlay
                            ROIs</button>
                        <button @click="view='corners'"
                            :class="view==='corners' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'" type="button">Cantos
                            (Debug)</button>
                    @endif

                    {{-- Ajuste avançado: recurso EXCEPCIONAL (foto torta/distorcida). Fora do fluxo normal. --}}
                    <button @click="view = (view==='adjust' ? 'original' : 'adjust')"
                        :class="view==='adjust' ? 'text-secondary-dark underline' : 'text-gray-400 hover:text-accent'"
                        class="ml-auto text-[11px] font-bold inline-flex items-center gap-1"
                        type="button" title="Use somente se a leitura automática falhar (foto fora do padrão)">
                        <span aria-hidden="true" class="material-symbols-outlined text-[14px]">tune</span>
                        Ajuste avançado
                    </button>
                    {{-- OpenCV.js carregado sob demanda — só é necessário para o Ajuste avançado. --}}
                    <script async src="{{ asset('vendor/opencv/opencv-4.8.0.js') }}"
                        onload="window.cvLoaded = true"></script>
                </div>

                <div class="w-full">
                    {{-- Original --}}
                    <div x-show="view==='original'"
                        class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50 mt-2">
                        @if($scan->pages && $scan->pages->count() > 0)
                            @foreach($scan->pages as $page)
                                <div class="mb-2">
                                    <p class="text-xs font-bold text-gray-500 p-1">Folha
                                        {{ $page->page_index }}/{{ $page->total_pages }}
                                    </p>
                                    <img src="{{ route('institution.omr.pages.image', ['scan' => $scan, 'page' => $page]) }}" alt="Página"
                                        class="w-full object-contain max-h-[600px]" loading="lazy">
                                </div>
                            @endforeach
                        @else
                            <img src="{{ route('institution.omr.image', ['scan' => $scan, 'variant' => 'original']) }}" alt="Gabarito" id="original-img"
                                class="w-full object-contain max-h-[700px]" crossorigin="anonymous">
                        @endif
                    </div>

                    {{-- Warped --}}
                    @if($scan->warped_path)
                        <div x-show="view==='warped'"
                            x-data="warpedViewer()"
                            @wheel.prevent="onWheel"
                            @pointerdown="onPointerDown"
                            @pointermove="onPointerMove"
                            @pointerup="onPointerUp"
                            @pointerleave="onPointerUp"
                            class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50 mt-2 relative h-[700px] cursor-grab active:cursor-grabbing w-full"
                            id="warped-container">
                            <div :style="`transform: translate(${x}px, ${y}px) scale(${scale}); transform-origin: 0 0;`" class="absolute top-0 left-0 transition-transform duration-75">
                                <img src="{{ Storage::url($scan->warped_path) }}" alt="Warped"
                                    class="max-w-none origin-top-left" id="warped-img" @load="initViewer">
                                <canvas id="overlay-canvas" class="absolute top-0 left-0"
                                    style="width:100%;height:100%"></canvas>
                            </div>
                            
                            <!-- Controles de Zoom -->
                            <div class="absolute bottom-4 right-4 bg-white/90 p-2 rounded-lg shadow-lg flex items-center gap-2 border border-gray-200">
                                <button type="button" @click="zoomOut" class="p-1 hover:bg-gray-200 rounded text-gray-700 flex items-center justify-center" title="Reduzir">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">zoom_out</span>
                                </button>
                                <button type="button" @click="resetZoom" class="px-2 py-1 text-xs font-bold text-gray-600 hover:bg-gray-200 rounded min-w-[50px] text-center" title="Ajustar à Tela">
                                    <span x-text="Math.round(scale * 100) + '%'"></span>
                                </button>
                                <button type="button" @click="zoomIn" class="p-1 hover:bg-gray-200 rounded text-gray-700 flex items-center justify-center" title="Ampliar">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[20px]">zoom_in</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Overlay ROIs (imagem estática gerada pela engine) --}}
                    @if($scan->debug_path)
                        <div x-show="view==='overlay'"
                            class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50 mt-2">
                            <img src="{{ Storage::url($scan->debug_path . '/06_overlay_rois.png') }}" alt="Overlay"
                                class="w-full object-contain max-h-[700px]">
                        </div>

                        <div x-show="view==='corners'"
                            class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50 mt-2">
                            <img src="{{ Storage::url($scan->debug_path . '/04_corners.png') }}" alt="Cantos"
                                class="w-full object-contain max-h-[700px]">
                        </div>
                    @endif

                    {{-- Tela de Ajuste Manual de Cantos (Drag & Drop) --}}
                    <div x-show="view==='adjust'" x-data="cornerAdjuster('{{ route('institution.omr.image', ['scan' => $scan, 'variant' => 'original']) }}')"
                        class="border-2 border-accent/40 rounded-xl bg-accent-light mt-2 relative p-2"
                        style="display: none;">
                        <div
                            class="mb-3 text-sm text-secondary-dark bg-white p-3 rounded-lg border border-accent/25 shadow-sm flex items-start gap-2">
                            <span aria-hidden="true" class="material-symbols-outlined">info</span>
                            <div>
                                <p><strong>Ajuste de Homografia:</strong> Arraste os 4 círculos vermelhos e os posicione
                                    exatamente no centro dos quadrados pretos nos 4 cantos da folha original.</p>
                            </div>
                        </div>

                        <div class="relative w-full overflow-hidden rounded-lg bg-gray-100 flex justify-center"
                            id="adjust-container">
                            <canvas x-ref="adjustCanvas" @pointerdown="onPointerDown" @pointermove="onPointerMove"
                                @pointerup="onPointerUp" @pointerleave="onPointerUp"
                                class="max-h-[600px] touch-none cursor-crosshair">
                            </canvas>
                        </div>

                        <!-- POST submission is removed; processing happens completely localized now -->
                        <div class="mt-4 flex justify-between items-center bg-white p-3 border rounded-lg">
                            <span class="text-sm font-bold text-gray-500" x-show="!isSubmitting">Ajuste os 4 cantos e
                                clique em ler.</span>
                            <span class="text-sm font-bold text-accent" x-show="isSubmitting">Processando Matriz
                                OpenCV local...</span>
                            <div class="flex gap-2">
                                <button type="button" @click="resetCorners"
                                    class="btn-secondary btn-sm">Resetar</button>
                                <button type="button" @click="processLocalHomography"
                                    class="btn-primary btn-sm bg-secondary-dark border-none" :disabled="isSubmitting">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">transform</span>
                                    <span>Ler Gabarito Local</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-1">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">person</span>
                    Enviado por: <strong>{{ $scan->uploader->name ?? '?' }}</strong>
                </div>
                <div class="flex items-center gap-1">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">schedule</span>
                    {{ $scan->created_at->format('d/m/Y H:i') }}
                </div>
                @if($scan->omrTemplate)
                    <div class="flex items-center gap-1" title="Template usado na leitura (FR-13)">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">grid_on</span>
                        Template: <strong>{{ $scan->omrTemplate->name }}</strong>
                        @if($scan->layout_version) <span class="text-gray-400">v{{ $scan->layout_version }}</span> @endif
                    </div>
                @endif
                @if($scan->score !== null)
                    <div class="flex items-center gap-1 font-bold text-primary">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">grade</span>
                        Nota: {{ $scan->score }}/{{ $scan->total_points }}
                    </div>
                @endif
            </div>

            {{-- Debug links --}}
            @if($scan->debug_path)
                <details class="mt-3 text-xs">
                    <summary class="cursor-pointer text-gray-400 hover:text-gray-600">Debug outputs</summary>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach(['01_original.png', '02_gray.png', '03_thresh.png', '04_corners.png', '05_warped.png', '06_overlay_rois.png'] as $dbgFile)
                            <a href="{{ Storage::url($scan->debug_path . '/' . $dbgFile) }}" target="_blank"
                                class="p-1 border rounded text-center hover:bg-gray-50">{{ $dbgFile }}</a>
                        @endforeach
                        <a href="{{ Storage::url($scan->debug_path . '/result.json') }}" target="_blank"
                            class="p-1 border rounded text-center hover:bg-gray-50 col-span-3">result.json</a>
                    </div>
                </details>
            @endif
        </div>

        @push('scripts')
            <script>
                // Export variables for Alpine and OmrEngine
                @if($scan->omrTemplate)
                    window.omrTemplateDimensions = {
                        width: {{ $scan->omrTemplate->width_px ?? 2480 }},
                        height: {{ $scan->omrTemplate->height_px ?? 3508 }}
                                                                                    };
                    @php
                        $templateData = [
                            'template_id' => $scan->omrTemplate->id,
                            'width' => $scan->omrTemplate->width_px ?? 2480,
                            'height' => $scan->omrTemplate->height_px ?? 3508,
                            'corner_points' => $scan->omrTemplate->corner_points_json,
                            'questions' => $scan->omrTemplate->questions->map(function ($q) {
                                return [
                                    'id' => $q->id,
                                    'type' => $q->type,
                                    'question_number' => $q->question_number,
                                    'option_labels_json' => $q->option_labels_json,
                                    'rois_json' => $q->rois_json
                                ];
                            })->values()->toArray(),
                            'layout_meta' => $scan->layout_meta ?? null
                        ];
                    @endphp
                    window.omrTemplateData = @json($templateData);
                @endif

                    function normalizedReviewTemplate() {
                        const template = window.omrTemplateData || { questions: [] };
                        const geometry = window.omrGeometry?.parse(template.layout_meta);
                        if (geometry) {
                            template.questions = window.omrGeometry.questions(template.questions || [], geometry);
                        }

                        return template;
                    }

                    function initOverlayCanvas() {
                        const img = document.getElementById('warped-img');
                        const canvas = document.getElementById('overlay-canvas');
                        if (!img || !canvas) return;

                        const ctx = canvas.getContext('2d');
                        canvas.width = img.naturalWidth;
                        canvas.height = img.naturalHeight;

                        const detectedAnswers = @json($scan->detected_answers ?? []);
                        const qualityData = @json($scan->quality_json ?? []);

                        let W = canvas.width;
                        let H = canvas.height;

                        let startX = W * 0.0868;
                        let startY = H * 0.1190;
                        let colSpacing = W * 0.2390;
                        let rowSpacing = H * 0.0538;
                        let bubbleSize = W * 0.0212;
                        let optionSpacing = W * 0.0285;
                        let rPC = 15;

                        // Support dynamic QR code overrides based on original PDF layout
                        const reviewTemplate = normalizedReviewTemplate();
                        const layoutMeta = reviewTemplate.layout_meta;
                        const signedGeometry = window.omrGeometry?.parse(layoutMeta);
                        if (signedGeometry) {
                            const resolved = window.omrGeometry.resolve(signedGeometry, W, H);
                            startX = resolved.startX;
                            startY = resolved.startY;
                            colSpacing = resolved.columnSpacing;
                            rowSpacing = resolved.rowSpacing;
                            bubbleSize = resolved.bubbleSize;
                            optionSpacing = resolved.optionSpacing;
                            rPC = resolved.rowsPerColumn;
                        }
                        const templateQuestions = reviewTemplate.questions || [];

                        window.omrBoxes = [];

                        // Desenhar ROIs das bolhas dinamicamente
                        templateQuestions
                            .filter((q) => !signedGeometry
                                || (q.question_number >= signedGeometry.qs && q.question_number <= signedGeometry.qe))
                            .forEach((q) => {
                            let options = q.option_labels_json || [];
                            let num = q.question_number;

                            const position = signedGeometry
                                ? window.omrGeometry.position(num, signedGeometry.qs, rPC)
                                : { column: Math.floor((num - 1) / rPC), row: (num - 1) % rPC };
                            let col = position.column;
                            let row = position.row;

                            let bX = startX + (col * colSpacing);
                            let bY = startY + (row * rowSpacing);

                            options.forEach((label, i) => {
                                let x = Math.round(bX + (i * optionSpacing));
                                let y = Math.round(bY);
                                let w = Math.round(bubbleSize);
                                let h = w;

                                // Guardar o box para click-to-edit
                                window.omrBoxes.push({
                                    qId: q.id,
                                    qType: q.type,
                                    optIndex: i,
                                    optLabel: label,
                                    x: x, y: y, w: w, h: h
                                });

                                // Verificar se esta opção está selecionada no formulário lateral
                                const selectEl = document.querySelector(`select[name="answers[${q.id}]"]`);
                                let isSelected = false;

                                if (selectEl && selectEl.value !== "") {
                                    if (q.type === 'true_false') {
                                        if (label === 'V' || label === 'T' || label === '1' || label === 'true') {
                                            isSelected = (selectEl.value === '1');
                                        } else if (label === 'F' || label === '0' || label === 'false') {
                                            isSelected = (selectEl.value === '0');
                                        }
                                    } else {
                                        isSelected = (selectEl.value === String(i));
                                    }
                                } else {
                                    // Fallback caso o select ainda não tenha sido montado (improvável)
                                    const detected = detectedAnswers[q.id];
                                    if (detected !== undefined && detected !== null) {
                                        const letterIdx = typeof detected === 'number' ? String.fromCharCode(65 + detected) : null;
                                        isSelected = (letterIdx === label) || (detected === label) || (Array.isArray(detected) && detected.includes(label));
                                    }
                                }

                                ctx.strokeStyle = isSelected ? 'rgba(0, 200, 0, 0.9)' : 'rgba(50, 50, 50, 0.4)';
                                ctx.lineWidth = isSelected ? 4 : 2;
                                ctx.strokeRect(x, y, w, h);

                                if (isSelected) {
                                    ctx.fillStyle = 'rgba(0, 200, 0, 0.3)';
                                    ctx.fillRect(x, y, w, h);
                                }
                            });
                        });
                    }

                // Make elements trigger redraw on change
                document.addEventListener('DOMContentLoaded', () => {
                    const selects = document.querySelectorAll('select[name^="answers["]');
                    selects.forEach(sel => {
                        sel.addEventListener('change', () => {
                            if (document.getElementById('overlay-canvas')) {
                                initOverlayCanvas();
                            }
                        });
                    });
                });

                // Alpine component for Zoom, Pan, Click-to-Edit
                const mountWarpedViewer = () => {
                    Alpine.data('warpedViewer', () => ({
                        scale: 1,
                        x: 0,
                        y: 0,
                        isDragging: false,
                        startX: 0,
                        startY: 0,
                        moved: false,

                        initViewer() {
                            const img = document.getElementById('warped-img');
                            const container = document.getElementById('warped-container');
                            if (img && container) {
                                // Calculate scale to fit width initially
                                const scaleToFit = container.clientWidth / img.naturalWidth;
                                this.scale = Math.min(scaleToFit, 1);
                                
                                // Center vertically if it fits
                                const scaledHeight = img.naturalHeight * this.scale;
                                if (scaledHeight < container.clientHeight) {
                                    this.y = (container.clientHeight - scaledHeight) / 2;
                                } else {
                                    this.y = 0;
                                }
                                this.x = (container.clientWidth - (img.naturalWidth * this.scale)) / 2;
                            }
                            initOverlayCanvas();
                        },

                        zoomIn() {
                            this.doZoom(0.2, this.$el.clientWidth / 2, this.$el.clientHeight / 2);
                        },

                        zoomOut() {
                            this.doZoom(-0.2, this.$el.clientWidth / 2, this.$el.clientHeight / 2);
                        },

                        resetZoom() {
                            this.initViewer();
                        },

                        onWheel(e) {
                            const rect = this.$el.getBoundingClientRect();
                            const mouseX = e.clientX - rect.left;
                            const mouseY = e.clientY - rect.top;
                            const zoomFactor = e.deltaY < 0 ? 0.1 : -0.1;
                            this.doZoom(zoomFactor, mouseX, mouseY);
                        },

                        doZoom(factor, centerX, centerY) {
                            const oldScale = this.scale;
                            this.scale = Math.max(0.1, Math.min(this.scale + factor, 5));
                            const ratio = this.scale / oldScale;
                            this.x = centerX - (centerX - this.x) * ratio;
                            this.y = centerY - (centerY - this.y) * ratio;
                        },

                        onPointerDown(e) {
                            this.isDragging = true;
                            this.moved = false;
                            this.startX = e.clientX - this.x;
                            this.startY = e.clientY - this.y;
                            this.$el.setPointerCapture(e.pointerId);
                        },

                        onPointerMove(e) {
                            if (!this.isDragging) return;
                            this.moved = true;
                            this.x = e.clientX - this.startX;
                            this.y = e.clientY - this.startY;
                        },

                        onPointerUp(e) {
                            if (!this.isDragging) return;
                            this.isDragging = false;
                            this.$el.releasePointerCapture(e.pointerId);
                            
                            // If mouse didn't move significantly, it's a click
                            if (!this.moved || Math.abs(e.clientX - this.startX - this.x) < 5) {
                                this.handleClick(e);
                            }
                        },

                        handleClick(e) {
                            const img = document.getElementById('warped-img');
                            if (!img || !window.omrBoxes) return;

                            const rect = img.getBoundingClientRect();
                            
                            // Since img is CSS transformed (scaled & translated via wrapper),
                            // getBoundingClientRect gives us the exact on-screen bounds.
                            // The image's natural coordinate system goes from 0 to img.naturalWidth.
                            // So we map the click:
                            const scaleX = img.naturalWidth / rect.width;
                            const scaleY = img.naturalHeight / rect.height;
                            
                            const clickX = (e.clientX - rect.left) * scaleX;
                            const clickY = (e.clientY - rect.top) * scaleY;

                            // Find intersecting bubble
                            const hit = window.omrBoxes.find(b => 
                                clickX >= b.x && clickX <= (b.x + b.w) &&
                                clickY >= b.y && clickY <= (b.y + b.h)
                            );

                            if (hit) {
                                this.toggleAnswer(hit);
                            }
                        },

                        toggleAnswer(hit) {
                            const select = document.querySelector(`select[name="answers[${hit.qId}]"]`);
                            if (!select) return;

                            let newValue = String(hit.optIndex);
                            if (hit.qType === 'true_false') {
                                if (hit.optLabel === 'V' || hit.optLabel === 'T' || hit.optLabel === '1' || hit.optLabel === 'true') {
                                    newValue = '1';
                                } else if (hit.optLabel === 'F' || hit.optLabel === '0' || hit.optLabel === 'false') {
                                    newValue = '0';
                                }
                            }

                            // Toggle
                            if (select.value === newValue) {
                                select.value = "";
                            } else {
                                select.value = newValue;
                            }

                            // Manually dispatch change event so Alpine/initOverlayCanvas catches it
                            select.dispatchEvent(new Event('change'));
                            
                            // Highlight the select to give visual feedback on the left panel
                            select.closest('tr').classList.add('bg-accent/15', 'transition-colors', 'duration-300');
                            setTimeout(() => {
                                select.closest('tr').classList.remove('bg-accent/15');
                            }, 500);
                        }
                    }));
                };

                // Alpine.js component for manual corner adjustments
                const mountCornerAdjuster = () => {
                    Alpine.data('cornerAdjuster', (imagePath) => ({
                        img: null,
                        ctx: null,
                        scale: 1,
                        draggingIndex: -1,
                        isSubmitting: false,
                        corners: [
                            { x: 50, y: 50, label: 'TL' },
                            { x: 950, y: 50, label: 'TR' },
                            { x: 950, y: 1350, label: 'BR' },
                            { x: 50, y: 1350, label: 'BL' }
                        ],
                        originalCorners: null,

                        init() {
                            const canvas = this.$refs.adjustCanvas;
                            this.ctx = canvas.getContext('2d');
                            this.img = new Image();
                            this.img.src = imagePath;
                            this.img.onload = () => {
                                this.setupCorners();
                                this.draw();
                            };

                            // Monitor resize to recalculate cursor interactions
                            window.addEventListener('resize', () => {
                                if (this.$el.style.display !== 'none') this.draw();
                            });
                        },

                        setupCorners() {
                            const w = this.img.naturalWidth;
                            const h = this.img.naturalHeight;
                            // Default estimative: 5% padding
                            const padX = w * 0.05;
                            const padY = h * 0.05;

                            this.corners = [
                                { x: padX, y: padY, label: 'TL' },
                                { x: w - padX, y: padY, label: 'TR' },
                                { x: w - padX, y: h - padY, label: 'BR' },
                                { x: padX, y: h - padY, label: 'BL' }
                            ];
                            this.originalCorners = JSON.parse(JSON.stringify(this.corners));
                        },

                        resetCorners() {
                            if (this.originalCorners) {
                                this.corners = JSON.parse(JSON.stringify(this.originalCorners));
                                this.draw();
                            }
                        },

                        draw() {
                            if (!this.img || !this.img.complete) return;
                            const canvas = this.$refs.adjustCanvas;
                            const containerId = 'adjust-container';

                            canvas.width = this.img.naturalWidth;
                            canvas.height = this.img.naturalHeight;

                            // Compute CSS scale factor for cursor math
                            const rect = canvas.getBoundingClientRect();
                            this.scale = canvas.width / rect.width;

                            this.ctx.clearRect(0, 0, canvas.width, canvas.height);
                            this.ctx.drawImage(this.img, 0, 0);

                            // Draw connecting lines
                            this.ctx.beginPath();
                            this.ctx.moveTo(this.corners[0].x, this.corners[0].y);
                            this.ctx.lineTo(this.corners[1].x, this.corners[1].y);
                            this.ctx.lineTo(this.corners[2].x, this.corners[2].y);
                            this.ctx.lineTo(this.corners[3].x, this.corners[3].y);
                            this.ctx.closePath();
                            this.ctx.strokeStyle = 'rgba(147, 51, 234, 0.4)'; // Purple
                            this.ctx.lineWidth = 4 * this.scale;
                            this.ctx.stroke();

                            // Draw corner handles
                            const radius = 25 * this.scale;
                            this.corners.forEach((c, idx) => {
                                this.ctx.beginPath();
                                this.ctx.arc(c.x, c.y, radius, 0, Math.PI * 2);
                                this.ctx.fillStyle = this.draggingIndex === idx ? 'rgba(255, 0, 0, 0.6)' : 'rgba(147, 51, 234, 0.6)';
                                this.ctx.fill();
                                this.ctx.strokeStyle = 'white';
                                this.ctx.lineWidth = 3 * this.scale;
                                this.ctx.stroke();

                                // Label
                                this.ctx.fillStyle = 'white';
                                this.ctx.font = `bold ${16 * this.scale}px sans-serif`;
                                this.ctx.textAlign = 'center';
                                this.ctx.textBaseline = 'middle';
                                this.ctx.fillText(c.label, c.x, c.y);
                            });
                        },

                        getMousePos(evt) {
                            const canvas = this.$refs.adjustCanvas;
                            const rect = canvas.getBoundingClientRect();
                            return {
                                x: (evt.clientX - rect.left) * this.scale,
                                y: (evt.clientY - rect.top) * this.scale
                            };
                        },

                        onPointerDown(evt) {
                            evt.preventDefault();
                            this.$refs.adjustCanvas.setPointerCapture(evt.pointerId);
                            const pos = this.getMousePos(evt);
                            const hitRadius = 40 * this.scale;

                            for (let i = 0; i < this.corners.length; i++) {
                                const dx = pos.x - this.corners[i].x;
                                const dy = pos.y - this.corners[i].y;
                                if (dx * dx + dy * dy <= hitRadius * hitRadius) {
                                    this.draggingIndex = i;
                                    this.draw();
                                    return;
                                }
                            }
                        },

                        onPointerMove(evt) {
                            if (this.draggingIndex >= 0) {
                                evt.preventDefault();
                                const pos = this.getMousePos(evt);
                                // clamp
                                const canvas = this.$refs.adjustCanvas;
                                this.corners[this.draggingIndex].x = Math.max(0, Math.min(pos.x, canvas.width));
                                this.corners[this.draggingIndex].y = Math.max(0, Math.min(pos.y, canvas.height));
                                this.draw();
                            }
                        },

                        onPointerUp(evt) {
                            if (this.draggingIndex >= 0) {
                                this.$refs.adjustCanvas.releasePointerCapture(evt.pointerId);
                                this.draggingIndex = -1;
                                this.draw();
                            }
                        },

                        async processLocalHomography() {
                            if (typeof cv === 'undefined' || typeof cv.Mat === 'undefined' || !window.OmrEngine) {
                                alert("OpenCV.js ou OmrEngine ainda estão carregando, por favor aguarde um momento.");
                                return;
                            }

                            this.isSubmitting = true;

                            try {
                                const engine = new window.OmrEngine(false); // Debug mode off

                                // Transformar this.img num canvas pra extrair o Mat
                                const hiddenCanvas = document.createElement('canvas');
                                hiddenCanvas.width = this.img.naturalWidth;
                                hiddenCanvas.height = this.img.naturalHeight;
                                const hCtx = hiddenCanvas.getContext('2d');
                                hCtx.drawImage(this.img, 0, 0);

                                let srcMat = cv.matFromImageData(hCtx.getImageData(0, 0, hiddenCanvas.width, hiddenCanvas.height));

                                // Obter template dimensions. (Fallback hardcoded para A4 @ 300DPI se falhar json)
                                const tWidth = window.omrTemplateDimensions?.width || 2480;
                                const tHeight = window.omrTemplateDimensions?.height || 3508;

                                // Realizar Warp Perspective usando TS-Core com os cantos arrastados pelo usuário
                                let warped = engine.warp(srcMat, this.corners, tWidth, tHeight, window.omrTemplateData?.corner_points);
                                srcMat.delete();

                                // Criar canvas temporário para extrair a imagem warped em Base64
                                const outCanvas = document.createElement('canvas');
                                cv.imshow(outCanvas, warped);
                                const warpedDataUrl = outCanvas.toDataURL('image/jpeg', 0.8);

                                let results = engine.readBubbles(warped, normalizedReviewTemplate(), null);
                                let quality = engine.assessQuality(4, results);

                                warped.delete();

                                // Criar formData para POST Ajax atualizando via Ajax no Laravel
                                const formData = new FormData();
                                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                                formData.append('answers_json', JSON.stringify(results));
                                formData.append('quality_json', JSON.stringify(quality));
                                formData.append('warped_image', warpedDataUrl);

                                const response = await fetch(`{{ route('institution.omr.updateLocal', $scan->id ?? 0) }}`, {
                                    method: 'POST',
                                    body: formData
                                });

                                if (response.ok) {
                                    window.location.reload(); // Atualiza a página com o Overlay gerado do DB
                                } else {
                                    alert("Falha ao salvar a avaliação local.");
                                }

                            } catch (err) {
                                console.error("OpenCV Local Error:", err);
                                alert("Houve um erro no processamento visual longo. Tente novamente ou veja o console.");
                            } finally {
                                this.isSubmitting = false;
                            }
                        }
                    }));
                };

                // Handle Alpine Registration Race Condition
                if (window.Alpine) {
                    mountCornerAdjuster();
                    mountWarpedViewer();
                } else {
                    document.addEventListener('alpine:init', () => {
                        mountCornerAdjuster();
                        mountWarpedViewer();
                    });
                }

                // Handle Canvas Init Race Condition
                document.addEventListener('DOMContentLoaded', () => {
                    const img = document.getElementById('warped-img');
                    if (img) {
                        if (img.complete) {
                            initOverlayCanvas();
                        } else {
                            img.addEventListener('load', initOverlayCanvas);
                        }
                    }
                });
            </script>

            @if($scan->status === 'pending')
                <script>
                    // Auto processor for new scans
                    document.addEventListener('DOMContentLoaded', () => {
                        const img = document.getElementById('original-img');
                        if (!img) return;

                        const checkInterval = setInterval(() => {
                            if (typeof cv !== 'undefined' && typeof cv.Mat !== 'undefined' && window.OmrEngine) {
                                clearInterval(checkInterval);
                                if (img.complete) {
                                    autoProcessScan();
                                } else {
                                    img.addEventListener('load', autoProcessScan);
                                }
                            }
                        }, 500);

                        async function autoProcessScan() {
                            const statusBadge = document.querySelector('.bg-yellow-100');
                            if (statusBadge) statusBadge.innerText = "Processando com OpenCV.js...";

                            try {
                                const hiddenCanvas = document.createElement('canvas');
                                hiddenCanvas.width = img.naturalWidth;
                                hiddenCanvas.height = img.naturalHeight;
                                const hCtx = hiddenCanvas.getContext('2d');
                                hCtx.drawImage(img, 0, 0);

                                const engine = new window.OmrEngine(false); // Debug Off
                                let srcMat = cv.matFromImageData(hCtx.getImageData(0, 0, hiddenCanvas.width, hiddenCanvas.height));

                                // 1. Preprocess
                                const { thresh } = engine.preprocess(srcMat);

                                // 2. Find Corners automatically
                                const corners = engine.findCornerMarkers(thresh);
                                if (corners.length < 4) {
                                    alert("A detecção automática falhou. Não foi possível encontrar os 4 marcadores nos cantos da imagem. Por favor, utilize a 'Calibração Local' manualmente.");
                                    // Stop trying to process, wait for manual user intervention
                                    if (statusBadge) statusBadge.innerText = "Falha no Enquadramento Automático";
                                    statusBadge.className = "badge bg-red-100 text-red-800 ml-2";
                                    srcMat.delete(); thresh.delete();
                                    return;
                                }

                                // 3. Warp
                                const tWidth = window.omrTemplateDimensions?.width || 2480;
                                const tHeight = window.omrTemplateDimensions?.height || 3508;
                                let warped = engine.warp(srcMat, corners, tWidth, tHeight, window.omrTemplateData?.corner_points);
                                srcMat.delete();
                                thresh.delete();

                                // Criar Base64 da imagem alinhada
                                const outCanvasAuto = document.createElement('canvas');
                                cv.imshow(outCanvasAuto, warped);
                                const warpedDataUrlAuto = outCanvasAuto.toDataURL('image/jpeg', 0.8);

                                // 4. Read Bubbles
                                let results = engine.readBubbles(warped, normalizedReviewTemplate(), null);
                                let quality = engine.assessQuality(4, results);
                                warped.delete();

                                // 5. Submit to backend
                                const formData = new FormData();
                                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                                formData.append('answers_json', JSON.stringify(results));
                                formData.append('quality_json', JSON.stringify(quality));
                                formData.append('warped_image', warpedDataUrlAuto);

                                const response = await fetch(`{{ route('institution.omr.updateLocal', $scan->id ?? 0) }}`, {
                                    method: 'POST',
                                    body: formData
                                });

                                if (response.ok) {
                                    window.location.reload();
                                } else {
                                    alert("Falha de rede ao salvar as notas corrigidas.");
                                    if (statusBadge) statusBadge.innerText = "Erro ao Salvar";
                                }

                            } catch (e) {
                                console.error("Auto processing failed", e);
                                alert("Ocorreu um erro ao processar. Utilize a forma manual.");
                            }
                        }
                    });
                </script>
            @endif
        @endpush

        </div> {{-- Fim do col-span-7 --}}

        <!-- Respostas (Lado Direito) -->
        <div class="lg:col-span-5 space-y-6">
            <div class="card !p-6 border-none shadow-2xl shadow-primary/5 bg-white/80 backdrop-blur-xl sticky top-24">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-black text-xl text-duo-heading flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                            <span aria-hidden="true" class="material-symbols-outlined text-[24px]">assignment_turned_in</span>
                        </div>
                        Conferência de Notas
                    </h3>
                    @if($scan->status === 'confirmed')
                        <span class="px-3 py-1.5 rounded-full bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-500/20">Confirmado</span>
                    @endif
                </div>

                <form action="{{ route('institution.omr.confirm', $scan->id) }}" method="POST" class="space-y-6">
                    @csrf

                    <div class="bg-primary/5 p-4 rounded-2xl border border-primary/10 mb-6">
                        <label class="text-[11px] font-black uppercase tracking-widest text-primary/60 mb-2 block">Identificação do Aluno</label>
                        <div class="relative">
                            <select name="student_id" class="input-field text-sm font-bold" required>
                                <option value="">Selecione o aluno...</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" {{ $scan->student_id == $student->id ? 'selected' : '' }}>
                                        {{ $student->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-duo-text/50">
                                <span aria-hidden="true" class="material-symbols-outlined">expand_more</span>
                            </div>
                        </div>
                    </div>

                    @php
                        $hasDetected = collect($scan->detected_answers ?? [])->filter(fn($v) => $v !== null && $v !== '')->count() > 0;
                    @endphp
                    @if(!$hasDetected && $scan->status !== 'confirmed')
                        <div class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold mb-2">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">info</span>
                            <span>Nenhuma resposta foi lida automaticamente nesta folha. Preencha abaixo manualmente, ou use o <strong>Ajuste avançado</strong> (ao lado da imagem) se a foto estiver torta/distorcida.</span>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-widest text-duo-text/50">Progresso da Revisão</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] bg-primary/10 text-primary px-2 py-0.5 rounded font-bold">Atalhos Ativos</span>
                                    <span class="text-[9px] text-gray-400 font-medium">Setas + [A,B,C,D]</span>
                                </div>
                            </div>
                            @php
                                $totalQs = $scan->exam->questions->count();
                                $detectedCount = collect($scan->confirmed_answers ?? $scan->detected_answers ?? [])->filter(fn($v) => $v !== null && $v !== '')->count();
                                $pct = $totalQs > 0 ? ($detectedCount / $totalQs) * 100 : 0;
                            @endphp
                            <div class="text-right">
                                <div class="flex items-center gap-2 justify-end mb-1">
                                    <span class="text-lg font-black text-primary leading-none">{{ $detectedCount }}</span>
                                    <span class="text-[10px] font-bold text-gray-400">/ {{ $totalQs }}</span>
                                </div>
                                <div class="w-24 h-1.5 bg-duo-border rounded-full overflow-hidden">
                                    <div class="h-full bg-primary shadow-[0_0_8px_rgba(var(--color-primary),0.4)] transition-all duration-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </div>

                        @php
                            $curAnswers = $scan->confirmed_answers ?? $scan->detected_answers ?? [];
                            $valErrors = $scan->quality_json['validation_errors'] ?? [];
                            $initAcertos = 0; $initErros = 0; $hasGabarito = false;
                            foreach ($scan->exam->questions as $gq) {
                                if (isset($valErrors[$gq->id])) { continue; } // divergência: não conta
                                $cv = $correctAnswers[$gq->id] ?? null;
                                if ($cv === null) { continue; }
                                $hasGabarito = true;
                                $a = $curAnswers[$gq->id] ?? null;
                                if ($a === null || $a === '') { continue; }
                                if ((int) $a === (int) $cv) { $initAcertos++; } else { $initErros++; }
                            }
                        @endphp
                        @if(!empty($valErrors))
                            <div class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-800 text-xs font-bold mb-2">
                                <span aria-hidden="true" class="material-symbols-outlined text-[18px] flex-shrink-0">gpp_maybe</span>
                                <span><strong>{{ count($valErrors) }} questão(ões) com divergência de gabarito.</strong> O gabarito gravado no QR impresso não confere com o oficial atual (ex.: resposta alterada após a impressão). A correção automática dessas questões foi <strong>bloqueada</strong> — verifique e ajuste manualmente antes de confirmar.</span>
                            </div>
                        @endif
                        @if($hasGabarito)
                            <div id="omr-correctness-summary" class="flex flex-wrap items-center gap-2 text-xs font-black mb-1">
                                <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-green-50 border border-green-200 text-green-700">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">check_circle</span>
                                    <span data-acertos>{{ $initAcertos }}</span>&nbsp;acertos
                                </span>
                                <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-50 border border-red-200 text-red-700">
                                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">cancel</span>
                                    <span data-erros>{{ $initErros }}</span>&nbsp;erros
                                </span>
                                <span class="text-[10px] font-medium text-gray-400">vs. gabarito</span>
                            </div>
                        @endif

                        @if($scan->exam && $scan->exam->questions->count() > 0)
                            @php
                                // ORDENAÇÃO FÍSICA: Usar o questions_map da cópia se disponível
                                $questionsMap = $scan->copy->questions_map ?? null;
                                if ($questionsMap) {
                                    $orderedQuestions = $scan->exam->questions->sortBy(function($q) use ($questionsMap) {
                                        return array_search($q->id, $questionsMap);
                                    });
                                } else {
                                    $orderedQuestions = $scan->exam->questions->sortBy('pivot.order');
                                }
                            @endphp
                            <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                                @foreach($orderedQuestions as $index => $question)
                                    @php
                                        $currentAnswer = ($scan->confirmed_answers ?? $scan->detected_answers ?? [])[$question->id] ?? null;
                                        $conf = $scan->quality_json['confidences'][$question->id] ?? 1.0;
                                        $isLowConfidence = $conf < 0.6;
                                        // O index físico real é a posição na folha (1-based)
                                        $physicalIndex = $questionsMap ? array_search($question->id, $questionsMap) + 1 : $index + 1;
                                        // Conferência em ESPAÇO VISUAL: o valor enviado é a POSIÇÃO da bolha
                                        // (0=A, 1=B, ...), igual ao que o motor lê. O grade() faz o reverse-map
                                        // visual->original via options_map. Uniforme p/ MC e V/F.
                                        $optCount = $question->type === 'true_false'
                                            ? 2
                                            : count($question->content['options'] ?? []);
                                        // Descarta leitura fora do range de opções (ex.: V/F lido como C
                                        // numa folha antiga) — mostra em branco em vez de valor fantasma.
                                        $currentVisual = (is_numeric($currentAnswer) && (int) $currentAnswer >= 0 && (int) $currentAnswer < $optCount)
                                            ? (int) $currentAnswer
                                            : null;
                                        $correctVisual = $correctAnswers[$question->id] ?? null; // gabarito (índice visual) ou null
                                        $selInit = $currentVisual === null ? '' : (string) $currentVisual;
                                        // Erro de validação: o QR impresso traz um gabarito que DIVERGE do oficial
                                        // atual (ex.: gabarito alterado após a impressão) → bloqueia auto-correção.
                                        $validationErr = $scan->quality_json['validation_errors'][$question->id] ?? null;
                                    @endphp
                                    {{-- Cores ao vivo: verde=acerto, vermelho=erro. Âmbar/⚠ = divergência de gabarito (bloqueada). --}}
                                    <div class="group flex items-center gap-3 p-3 rounded-xl border-2 transition-all"
                                        x-data="{ correct: @js($correctVisual), sel: @js($selInit), blocked: @js((bool) $validationErr) }"
                                        :class="blocked ? 'border-amber-400 bg-amber-50' : (correct !== null && sel !== '' ? (Number(sel) === correct ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50') : '{{ $isLowConfidence ? 'border-amber-100 bg-amber-50/30' : 'border-duo-border/50 hover:border-primary/30' }}')">
                                        <div class="w-8 h-8 shrink-0 rounded-lg text-white flex items-center justify-center font-black text-xs shadow-lg shadow-black/5"
                                            :class="blocked ? 'bg-amber-500' : (correct !== null && sel !== '' ? (Number(sel) === correct ? 'bg-green-500' : 'bg-red-500') : '{{ $isLowConfidence ? 'bg-amber-500' : 'bg-duo-heading' }}')">
                                            {{ $physicalIndex }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <p class="text-[11px] font-bold text-duo-heading truncate" title="{{ $question->content['statement'] ?? '' }}">
                                                {{ \Illuminate\Support\Str::limit($question->content['statement'] ?? 'Questão', 40) }}
                                            </p>
                                            @if($validationErr)
                                                <span class="text-[9px] font-black uppercase text-amber-700 tracking-tighter flex items-center gap-0.5" title="O gabarito do QR impresso diverge do oficial. Analise antes de confirmar.">
                                                    <span aria-hidden="true" class="material-symbols-outlined text-[12px]">warning</span>
                                                    Divergência de gabarito — verifique
                                                </span>
                                            @elseif($isLowConfidence)
                                                <span class="text-[9px] font-black uppercase text-amber-600 tracking-tighter">Baixa Confiança ({{ round($conf * 100) }}%)</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if($optCount > 0)
                                                {{-- Gabarito ao lado quando a resposta diverge (e não está bloqueada) --}}
                                                <span x-show="!blocked && correct !== null && sel !== '' && Number(sel) !== correct" x-cloak
                                                    class="text-[10px] font-black text-green-600 whitespace-nowrap" title="Resposta correta (gabarito)">
                                                    ✓<span x-text="String.fromCharCode(65 + correct)"></span>
                                                </span>
                                                <select name="answers[{{ $question->id }}]" x-model="sel" data-correct="{{ $correctVisual ?? '' }}" data-blocked="{{ $validationErr ? '1' : '' }}"
                                                    class="w-14 bg-white border-2 rounded-lg py-1 px-1 text-center font-black text-sm focus:ring-0 transition-all cursor-pointer"
                                                    :class="blocked ? 'border-amber-400 text-amber-700' : (correct !== null && sel !== '' ? (Number(sel) === correct ? 'border-green-400 text-green-700' : 'border-red-400 text-red-700') : 'border-duo-border text-duo-heading')">
                                                    <option value="">—</option>
                                                    @for($v = 0; $v < $optCount; $v++)
                                                        <option value="{{ $v }}" {{ $currentVisual === $v ? 'selected' : '' }}>{{ chr(65 + $v) }}</option>
                                                    @endfor
                                                </select>
                                            @else
                                                <span class="text-[10px] text-gray-400 font-bold">Dissertativa</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center bg-duo-border/10 rounded-2xl border-2 border-dashed border-duo-border">
                                <span aria-hidden="true" class="material-symbols-outlined text-duo-text/30 text-4xl mb-2">find_in_page</span>
                                <p class="text-xs font-bold text-duo-text/50">Nenhuma questão encontrada.</p>
                            </div>
                        @endif
                    </div>

                    @if(!in_array($scan->status, ['rejected']))
                        <div class="sticky bottom-0 pt-4 mt-4 border-t border-duo-border/50 bg-white/95 backdrop-blur z-10">
                            <button type="submit" class="w-full py-4 rounded-2xl bg-primary text-white font-black text-sm uppercase tracking-widest hover:bg-primary-dark hover:scale-[1.02] active:scale-95 transition-all shadow-xl shadow-primary/20 flex items-center justify-center gap-3">
                                <span aria-hidden="true" class="material-symbols-outlined text-[24px]">task_alt</span>
                                {{ $scan->status === 'confirmed' ? 'Atualizar Nota' : 'Confirmar e Gerar Nota' }}
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('keydown', (e) => {
            // Only if we're not in an input/select that's not our answer selects
            if (e.target.tagName === 'INPUT' || (e.target.tagName === 'SELECT' && !e.target.name.startsWith('answers['))) {
                return;
            }

            const activeElement = document.activeElement;
            const isAnswerSelect = activeElement && activeElement.name && activeElement.name.startsWith('answers[');

            // 1. Navigation (Up/Down)
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                const allSelects = Array.from(document.querySelectorAll('select[name^="answers["]'));
                const currentIndex = allSelects.indexOf(activeElement);
                
                if (currentIndex !== -1) {
                    e.preventDefault();
                    const nextIndex = e.key === 'ArrowDown' ? currentIndex + 1 : currentIndex - 1;
                    if (allSelects[nextIndex]) {
                        allSelects[nextIndex].focus();
                        allSelects[nextIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (allSelects.length > 0) {
                    allSelects[0].focus();
                }
            }

            // 2. Fast Answer (A, B, C, D, E or 1, 2, 3, 4, 5)
            if (isAnswerSelect) {
                const key = e.key.toUpperCase();
                let valToSet = null;

                if (['A', '1'].includes(key)) valToSet = "0";
                else if (['B', '2'].includes(key)) valToSet = "1";
                else if (['C', '3'].includes(key)) valToSet = "2";
                else if (['D', '4'].includes(key)) valToSet = "3";
                else if (['E', '5'].includes(key)) valToSet = "4";
                else if (key === 'V' || key === 'T') valToSet = "1";
                else if (key === 'F') valToSet = "0";
                else if (e.key === 'Backspace' || e.key === 'Delete') valToSet = "";

                if (valToSet !== null) {
                    e.preventDefault();
                    activeElement.value = valToSet;
                    activeElement.dispatchEvent(new Event('change'));
                    
                    // Auto-advance to next
                    const allSelects = Array.from(document.querySelectorAll('select[name^="answers["]'));
                    const currentIndex = allSelects.indexOf(activeElement);
                    if (allSelects[currentIndex + 1]) {
                        allSelects[currentIndex + 1].focus();
                        allSelects[currentIndex + 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });
    </script>
    <script>
        // Atualiza o contador de acertos/erros ao mudar qualquer resposta (dropdown ou teclado).
        (function () {
            function recount() {
                let acertos = 0, erros = 0;
                document.querySelectorAll('select[name^="answers["]').forEach(function (s) {
                    if (s.getAttribute('data-blocked') === '1') return; // divergência: não conta
                    var c = s.getAttribute('data-correct');
                    if (c === '' || c === null) return;
                    if (s.value === '') return;
                    if (parseInt(s.value, 10) === parseInt(c, 10)) acertos++; else erros++;
                });
                var box = document.getElementById('omr-correctness-summary');
                if (!box) return;
                var a = box.querySelector('[data-acertos]'); if (a) a.textContent = acertos;
                var e = box.querySelector('[data-erros]'); if (e) e.textContent = erros;
            }
            document.addEventListener('DOMContentLoaded', recount);
            document.addEventListener('change', function (ev) {
                if (ev.target && ev.target.matches && ev.target.matches('select[name^="answers["]')) recount();
            });
        })();
    </script>
    @endpush
</x-app-layout>
