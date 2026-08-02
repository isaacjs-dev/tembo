<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">Leitura OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.templates.index') }}">Templates</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">{{ $template ? 'Editar' : 'Novo' }} Template</span>
                </nav>
                <h1 class="page-title">{{ $template ? 'Editar Template' : 'Novo Template' }} de Cartão-Resposta</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">
                    Defina a geometria do cartão. O preview à direita mostra exatamente como o cartão será impresso.
                    @if($template) <span class="font-bold">Versão atual: {{ $template->current_version }}</span> @endif
                </p>
            </div>
            <a href="{{ route('institution.omr.templates.index') }}" class="btn-secondary btn-sm">Voltar</a>
        </div>
    </x-slot>

    @php
        $lc = $template?->layout_config ?? [];
        $hc = $template?->header_config ?? [];
        // valor com fallback: old() > layout_config > default
        $g = function ($name, $key, $default) use ($lc) { return old($name, $lc[$key] ?? $default); };
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            @foreach ($errors->all() as $error)
                <div class="flex items-center gap-2 text-sm"><span aria-hidden="true" class="material-symbols-outlined text-[16px]">error</span>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @if (session('status'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl font-bold">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
        {{-- ── Form (esquerda) ── --}}
        <div class="lg:col-span-5 space-y-4">
            {{-- Auto-detecção a partir de imagem (OpenCV.js + QR) --}}
            <div class="card p-4 border-2 border-dashed border-secondary/40 bg-secondary/5">
                <div class="flex items-start gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary text-[28px]">document_scanner</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-extrabold text-duo-heading text-sm">Auto-detectar a partir de imagem</p>
                        <p class="text-xs text-gray-500 mb-2">Envie a foto/scan de um cartão (com os 4 marcadores pretos e, se houver, o QR Code). O sistema preenche a geometria automaticamente — você ajusta depois.</p>
                        <input type="file" id="tpl-detect-file" accept=".pdf,image/*"
                            class="text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-secondary/15 file:text-secondary file:font-bold file:cursor-pointer">
                        <p id="tpl-detect-status" class="text-xs font-bold mt-2 text-gray-600"></p>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data"
                action="{{ $template ? route('institution.omr.templates.update', $template->id) : route('institution.omr.templates.store') }}"
                class="card p-6 space-y-5">
                @csrf
                @if($template) @method('PUT') @endif

                <div>
                    <label class="input-label">Nome do template *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $template?->name) }}" required class="input-field" placeholder="Ex.: Cartão 60Q — 3 colunas">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="input-label">Visibilidade</label>
                        <select name="visibility_scope" class="input-field">
                            <option value="org_public" {{ old('visibility_scope', $template?->visibility_scope) === 'org_public' ? 'selected' : '' }}>Toda a instituição</option>
                            <option value="private" {{ old('visibility_scope', $template?->visibility_scope) === 'private' ? 'selected' : '' }}>Apenas eu</option>
                        </select>
                    </div>
                    <div>
                        <label class="input-label">Título do cabeçalho</label>
                        <input type="text" name="header_title" value="{{ old('header_title', $hc['title'] ?? 'CARTÃO RESPOSTA') }}" class="input-field">
                    </div>
                </div>

                <div class="border-t-2 border-duo-border pt-4">
                    <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">Capacidade</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div><label class="input-label">Máx. questões</label><input type="number" name="max_questions" id="max_questions" value="{{ $g('max_questions','max_questions',40) }}" min="1" max="300" required class="input-field"></div>
                        <div><label class="input-label">Máx. colunas</label><input type="number" name="max_columns" id="max_columns" value="{{ old('max_columns', $template?->max_columns ?? ($lc['columns'] ?? 2)) }}" min="1" max="6" required class="input-field"></div>
                        <div><label class="input-label">Alternativas</label><input type="number" name="max_options" id="max_options" value="{{ $g('max_options','max_options',5) }}" min="2" max="6" required class="input-field"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <div><label class="input-label">Colunas (layout)</label><input type="number" name="columns" id="columns" value="{{ $g('columns','columns',2) }}" min="1" max="6" required class="input-field"></div>
                        <div><label class="input-label">Linhas por coluna</label><input type="number" name="rows_per_column" id="rows_per_column" value="{{ $g('rows_per_column','rows_per_column',20) }}" min="1" max="60" required class="input-field"></div>
                    </div>
                </div>

                <div class="border-t-2 border-duo-border pt-4">
                    <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-3">Geometria (mm)</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div><label class="input-label">Bolha (Ø)</label><input type="number" step="0.1" name="bubble_diameter_mm" id="bubble_diameter_mm" value="{{ $g('bubble_diameter_mm','bubble_diameter_mm',5.5) }}" required class="input-field"></div>
                        <div><label class="input-label">Marcador</label><input type="number" step="0.1" name="fiducial_size_mm" id="fiducial_size_mm" value="{{ old('fiducial_size_mm', $lc['frame_fiducial_mm'] ?? 8) }}" required class="input-field"></div>
                        <div><label class="input-label">Espaço entre alt.</label><input type="number" step="0.1" name="option_gap_mm" id="option_gap_mm" value="{{ $g('option_gap_mm','option_gap_mm',2) }}" class="input-field"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        <div><label class="input-label">Frame X</label><input type="number" step="0.1" name="frame_left_mm" id="frame_left_mm" value="{{ $g('frame_left_mm','frame_left_mm',12) }}" required class="input-field"></div>
                        <div><label class="input-label">Frame Y</label><input type="number" step="0.1" name="frame_top_mm" id="frame_top_mm" value="{{ $g('frame_top_mm','frame_top_mm',56) }}" required class="input-field"></div>
                        <div><label class="input-label">Frame largura</label><input type="number" step="0.1" name="frame_width_mm" id="frame_width_mm" value="{{ $g('frame_width_mm','frame_width_mm',186) }}" required class="input-field"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        <div><label class="input-label">Altura linha</label><input type="number" step="0.1" name="row_spacing_mm" id="row_spacing_mm" value="{{ $g('row_spacing_mm','row_spacing_mm',9) }}" required class="input-field"></div>
                        <div><label class="input-label">Recuo nº</label><input type="number" step="0.1" name="cell_indent_mm" id="cell_indent_mm" value="{{ $g('cell_indent_mm','cell_indent_mm',14) }}" required class="input-field"></div>
                        <div><label class="input-label">Pad. topo</label><input type="number" step="0.1" name="grid_pad_top_mm" id="grid_pad_top_mm" value="{{ $g('grid_pad_top_mm','grid_pad_top_mm',8) }}" class="input-field"></div>
                    </div>
                    <input type="hidden" name="margins_mm" value="{{ $lc['margins_mm'] ?? 12 }}">
                </div>

                <div class="border-t-2 border-duo-border pt-4">
                    <label class="input-label">Logotipo (opcional, PNG/JPG)</label>
                    <input type="file" name="logo" accept="image/*" class="input-field file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                    @if($template?->logo_path)
                        <p class="text-xs text-gray-500 mt-1">Logo atual será mantido se nenhum novo for enviado.</p>
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">save</span>
                        {{ $template ? 'Salvar (nova versão)' : 'Criar template' }}
                    </button>
                    @if($template && !$template->is_default && !$template->is_system)
                        <button type="submit" form="delete-template-form" class="btn-secondary btn-sm text-red-600 border-red-200"
                            onclick="return confirm('Excluir este template?')">Excluir</button>
                    @endif
                </div>
            </form>

            @if($template && !$template->is_default && !$template->is_system)
                <form id="delete-template-form" method="POST" action="{{ route('institution.omr.templates.destroy', $template->id) }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endif
        </div>

        {{-- ── Preview Konva (direita) ── --}}
        <div class="lg:col-span-7">
            <div class="card p-4 sticky top-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-extrabold text-duo-heading flex items-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-secondary">preview</span> Pré-visualização (A4)
                    </h3>
                    <span class="text-[11px] text-gray-400 font-bold">Arraste os marcadores ◼ para ajustar o frame</span>
                </div>
                <div id="tpl-canvas-wrap" class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-100 mx-auto" style="max-width: 520px;">
                    <div id="tpl-canvas"></div>
                </div>
                <p class="text-xs text-gray-400 mt-2 text-center" id="tpl-capacity-hint"></p>
            </div>
        </div>
    </div>

    @push('scripts')
        {{-- window.OmrEngine (preprocess/findCornerMarkers) vem do app.js via @vite --}}
        <script>
            (async function () {
                const Konva = await window.loadKonva();
                const A4_W = 210, A4_H = 297; // mm
                const wrap = document.getElementById('tpl-canvas-wrap');
                if (!wrap) return;

                const W = Math.min(wrap.clientWidth || 500, 520);
                const SCALE = W / A4_W;          // px por mm
                const H = Math.round(A4_H * SCALE);
                const mm = (v) => v * SCALE;

                const stage = new Konva.Stage({ container: 'tpl-canvas', width: W, height: H });
                const layer = new Konva.Layer();
                stage.add(layer);

                const num = (id, def) => {
                    const el = document.getElementById(id);
                    const v = el ? parseFloat(el.value) : NaN;
                    return isNaN(v) ? def : v;
                };
                const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = Math.round(v * 10) / 10; };

                function render() {
                    layer.destroyChildren();

                    // Página A4
                    layer.add(new Konva.Rect({ x: 0, y: 0, width: W, height: H, fill: '#ffffff', stroke: '#e5e7eb' }));

                    // Cabeçalho: título + QR (top-right)
                    layer.add(new Konva.Text({
                        x: 0, y: mm(8), width: W, align: 'center',
                        text: (document.getElementById('name') && document.getElementById('name').value) ? 'CARTÃO RESPOSTA' : 'CARTÃO RESPOSTA',
                        fontStyle: 'bold', fontSize: Math.max(10, mm(4)), fill: '#111',
                    }));
                    const qrSize = mm(20);
                    layer.add(new Konva.Rect({ x: W - mm(12) - qrSize, y: mm(12), width: qrSize, height: qrSize, stroke: '#9ca3af', dash: [4, 3] }));
                    layer.add(new Konva.Text({ x: W - mm(12) - qrSize, y: mm(12) + qrSize / 2 - 6, width: qrSize, align: 'center', text: 'QR', fontSize: 10, fill: '#9ca3af' }));
                    // Caixa de cabeçalho (institucional)
                    layer.add(new Konva.Rect({ x: mm(12), y: mm(13), width: W - mm(24) - qrSize - mm(2), height: mm(32), stroke: '#9ca3af' }));

                    // Parâmetros da grade (espelho de buildPageGeometry)
                    const fid = num('fiducial_size_mm', 8);
                    const cols = Math.max(1, Math.round(num('columns', 2)));
                    const rows = Math.max(1, Math.round(num('rows_per_column', 20)));
                    const opts = Math.max(2, Math.round(num('max_options', 5)));
                    const bub = num('bubble_diameter_mm', 5.5);
                    const frameX = num('frame_left_mm', 12);
                    const frameY = num('frame_top_mm', 56);
                    const frameW = num('frame_width_mm', 186);
                    const rowSp = num('row_spacing_mm', 9);
                    const startXIn = num('cell_indent_mm', 14);
                    const startYIn = num('grid_pad_top_mm', 8);
                    const optSp = bub + num('option_gap_mm', 2);
                    const colSp = frameW / cols;
                    const frameH = startYIn + rows * rowSp + 4;
                    const maxQ = Math.min(Math.round(num('max_questions', 40)), cols * rows);

                    // Bolhas
                    for (let q = 0; q < maxQ; q++) {
                        const col = Math.floor(q / rows);
                        const row = q % rows;
                        if (col >= cols) break;
                        const cellTop = frameY + startYIn + row * rowSp;
                        const bx0 = frameX + col * colSp + startXIn;
                        // número da questão
                        layer.add(new Konva.Text({ x: mm(frameX + col * colSp + 1), y: mm(cellTop), text: String(q + 1).padStart(2, '0'), fontSize: Math.max(6, mm(2.6)), fontStyle: 'bold', fill: '#111' }));
                        for (let o = 0; o < opts; o++) {
                            layer.add(new Konva.Circle({
                                x: mm(bx0 + o * optSp + bub / 2), y: mm(cellTop + bub / 2),
                                radius: mm(bub / 2), stroke: '#111', strokeWidth: 1,
                            }));
                        }
                    }

                    // Marcadores fiduciais (centros nos cantos do frame); TL e BR arrastáveis
                    const corners = [
                        { id: 'tl', x: frameX, y: frameY, drag: true },
                        { id: 'tr', x: frameX + frameW, y: frameY, drag: false },
                        { id: 'br', x: frameX + frameW, y: frameY + frameH, drag: true },
                        { id: 'bl', x: frameX, y: frameY + frameH, drag: false },
                    ];
                    corners.forEach((c) => {
                        const sq = new Konva.Rect({
                            x: mm(c.x) - mm(fid) / 2, y: mm(c.y) - mm(fid) / 2,
                            width: mm(fid), height: mm(fid), fill: '#111',
                            draggable: c.drag, name: c.id,
                        });
                        if (c.drag) {
                            sq.on('mouseover', () => { document.body.style.cursor = 'move'; });
                            sq.on('mouseout', () => { document.body.style.cursor = 'default'; });
                            sq.on('dragmove', () => {
                                const cx = (sq.x() + mm(fid) / 2) / SCALE;
                                const cy = (sq.y() + mm(fid) / 2) / SCALE;
                                if (c.id === 'tl') {
                                    setVal('frame_left_mm', Math.max(5, Math.min(120, cx)));
                                    setVal('frame_top_mm', Math.max(20, Math.min(160, cy)));
                                } else if (c.id === 'br') {
                                    const nx = Math.max(80, Math.min(200, cx - frameX));
                                    setVal('frame_width_mm', nx);
                                }
                                render();
                            });
                        }
                        layer.add(sq);
                    });

                    layer.draw();

                    const hint = document.getElementById('tpl-capacity-hint');
                    if (hint) hint.textContent = `Capacidade: ${cols} coluna(s) × ${rows} linha(s) = ${cols * rows} posições · ${opts} alternativas`;
                }

                // Re-render ao alterar qualquer campo
                ['max_questions','max_columns','max_options','columns','rows_per_column','bubble_diameter_mm','fiducial_size_mm','option_gap_mm','frame_left_mm','frame_top_mm','frame_width_mm','row_spacing_mm','cell_indent_mm','grid_pad_top_mm']
                    .forEach((id) => { const el = document.getElementById(id); if (el) el.addEventListener('input', render); });

                // ── Auto-detecção (OpenCV.js para marcadores + jsQR para a geometria) ──
                const detectStatus = (msg) => { const el = document.getElementById('tpl-detect-status'); if (el) el.textContent = msg; };
                const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

                let cvPromise = null;
                function ensureOpenCv() {
                    if (typeof cv !== 'undefined' && cv.Mat) return Promise.resolve();
                    if (cvPromise) return cvPromise;
                    cvPromise = new Promise((resolve, reject) => {
                        window.Module = { onRuntimeInitialized: () => resolve() };
                        const s = document.createElement('script');
                        s.src = @js(asset('vendor/opencv/opencv-4.8.0.js'));
                        s.async = true;
                        s.onerror = () => reject(new Error('Falha ao carregar OpenCV.js'));
                        document.head.appendChild(s);
                    });
                    return cvPromise;
                }
                async function fileToImageData(file) {
                    const url = URL.createObjectURL(file);
                    try {
                        const img = new Image(); img.src = url;
                        await new Promise((res, rej) => { img.onload = res; img.onerror = () => rej(new Error('imagem inválida')); });
                        const c = document.createElement('canvas');
                        c.width = img.naturalWidth; c.height = img.naturalHeight;
                        const ctx = c.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        return ctx.getImageData(0, 0, c.width, c.height);
                    } finally { URL.revokeObjectURL(url); }
                }
                // Decodifica a geometria embarcada no QR (g/rpp/cols) para os campos em mm.
                function applyQrGeometry(d, fw, fh) {
                    if (d.rpp) setVal('rows_per_column', parseInt(d.rpp));
                    if (d.cols) setVal('columns', parseInt(d.cols));
                    if (Array.isArray(d.g) && d.g.length >= 6) {
                        const g = d.g; // [startXf,startYf,colSpf,rowSpf,bubf,optSpf] (frações do frame ×10000)
                        setVal('cell_indent_mm', g[0] / 10000 * fw);
                        setVal('grid_pad_top_mm', g[1] / 10000 * fh);
                        setVal('row_spacing_mm', g[3] / 10000 * fh);
                        setVal('bubble_diameter_mm', g[4] / 10000 * fw);
                        setVal('option_gap_mm', Math.max(0, (g[5] - g[4]) / 10000 * fw));
                        if (!d.cols && g[2] > 0) setVal('columns', Math.max(1, Math.round(10000 / g[2])));
                    }
                }
                // Suporte a PDF: renderiza a 1ª página com pdf.js → ImageData.
                function ensurePdfJs() {
                    return window.loadPdfJs();
                }
                async function pdfToImageData(file) {
                    await ensurePdfJs();
                    const buf = await file.arrayBuffer();
                    const pdf = await window.pdfjsLib.getDocument({ data: buf }).promise;
                    const page = await pdf.getPage(1);
                    const viewport = page.getViewport({ scale: 2.0 });
                    const c = document.createElement('canvas');
                    c.width = viewport.width; c.height = viewport.height;
                    const ctx = c.getContext('2d');
                    await page.render({ canvasContext: ctx, viewport }).promise;
                    return ctx.getImageData(0, 0, c.width, c.height);
                }
                async function autoDetect(file) {
                    if (!window.OmrEngine) { detectStatus('Motor OMR indisponível (recarregue a página).'); return; }
                    try {
                        detectStatus('Carregando OpenCV.js (pode levar alguns segundos)…');
                        await ensureOpenCv();
                        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
                        detectStatus(isPdf ? 'Renderizando PDF…' : 'Processando imagem…');
                        const imageData = isPdf ? await pdfToImageData(file) : await fileToImageData(file);
                        const mat = cv.matFromImageData(imageData);
                        const engine = new window.OmrEngine(false);
                        const pre = engine.preprocess(mat);
                        const corners = engine.findCornerMarkers(pre.thresh); // [TL,TR,BR,BL] em px
                        mat.delete(); pre.gray.delete(); pre.thresh.delete();
                        if (!corners || corners.length < 4) {
                            detectStatus('⚠ Não encontrei os 4 marcadores. Use uma foto reta/nítida ou ajuste manualmente.');
                            return;
                        }
                        const iw = imageData.width, ih = imageData.height;
                        const tl = corners[0], tr = corners[1], bl = corners[3];
                        const fw = clamp((tr.x - tl.x) / iw * A4_W, 80, 200);
                        const fh = (bl.y - tl.y) / ih * A4_H;
                        setVal('frame_left_mm', clamp(tl.x / iw * A4_W, 5, 120));
                        setVal('frame_top_mm', clamp(tl.y / ih * A4_H, 20, 160));
                        setVal('frame_width_mm', fw);

                        let qrMsg = 'sem QR (defina a grade manualmente)';
                        if (window.jsQR) {
                            const code = jsQR(imageData.data, iw, ih, { inversionAttempts: 'attemptBoth' });
                            if (code) {
                                try { applyQrGeometry(JSON.parse(code.data), fw, fh); qrMsg = 'QR lido — grade pré-preenchida'; }
                                catch (e) { qrMsg = 'QR presente mas ilegível'; }
                            }
                        }
                        detectStatus('✓ 4 marcadores detectados. ' + qrMsg + '. Confira o preview e ajuste.');
                        render();
                    } catch (err) {
                        console.error(err);
                        detectStatus('Erro na detecção: ' + (err.message || err));
                    }
                }
                const detectFile = document.getElementById('tpl-detect-file');
                if (detectFile) detectFile.addEventListener('change', (e) => { if (e.target.files[0]) autoDetect(e.target.files[0]); });

                render();
            })();
        </script>
    @endpush
</x-app-layout>
