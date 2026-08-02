<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">Leitura OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Debug de Leitura</span>
                </nav>
                <h1 class="page-title">Debug da Leitura OMR</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">
                    Envie a foto/scan de um cartão. Mostra o que o motor enxergou: marcadores, imagem corrigida,
                    ROIs e o preenchimento medido. <strong>Tire um print desta tela e me envie.</strong>
                </p>
            </div>
            <a href="{{ route('institution.omr.webscan') }}" class="btn-secondary btn-sm">Voltar ao Web Scan</a>
        </div>
    </x-slot>

    <div class="space-y-6 mb-12">
        <div class="card p-5">
            <label class="input-label">Imagem ou PDF do cartão</label>
            <input type="file" id="dbg-file" accept=".pdf,image/*"
                class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold file:cursor-pointer">
            <div id="dbg-status" class="mt-3 text-sm font-bold text-duo-heading whitespace-pre-line"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-4">
                <h3 class="font-extrabold text-duo-heading mb-1 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-secondary">photo_camera</span> Original + marcadores
                </h3>
                <p class="text-xs text-gray-500 mb-3">Os círculos verdes deveriam cair no centro dos 4 quadrados pretos.</p>
                <div class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50">
                    <canvas id="dbg-original" style="max-width:100%; height:auto; display:block;"></canvas>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="font-extrabold text-duo-heading mb-1 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">grid_on</span> Corrigida (warp) + ROIs
                </h3>
                <p class="text-xs text-gray-500 mb-3">Cada retângulo é onde o motor MEDE uma bolha. O número é o preenchimento (0=vazio, 1=cheio). Verde = lido como marcado.</p>
                <div class="border-2 border-duo-border rounded-xl overflow-hidden bg-gray-50">
                    <canvas id="dbg-warped" style="max-width:100%; height:auto; display:block;"></canvas>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="font-extrabold text-duo-heading mb-3">Leitura por questão</h3>
            <div id="dbg-table" class="text-xs font-mono grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-1"></div>
        </div>
    </div>

    @push('scripts')
        {{-- window.OmrEngine (preprocess/findCornerMarkers/warp/readBubbles) vem do app.js via @vite --}}
        <script>
            (function () {
                const setStatus = (m) => { const el = document.getElementById('dbg-status'); if (el) el.textContent = m; };

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
                function ensurePdfJs() {
                    return window.loadPdfJs();
                }
                async function fileToImageData(file) {
                    const url = URL.createObjectURL(file);
                    try {
                        const img = new Image(); img.src = url;
                        await new Promise((res, rej) => { img.onload = res; img.onerror = () => rej(new Error('imagem inválida')); });
                        const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight;
                        const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
                        return ctx.getImageData(0, 0, c.width, c.height);
                    } finally { URL.revokeObjectURL(url); }
                }
                async function pdfToImageData(file) {
                    await ensurePdfJs();
                    const buf = await file.arrayBuffer();
                    const pdf = await window.pdfjsLib.getDocument({ data: buf }).promise;
                    const page = await pdf.getPage(1);
                    const viewport = page.getViewport({ scale: 2.0 });
                    const c = document.createElement('canvas'); c.width = viewport.width; c.height = viewport.height;
                    await page.render({ canvasContext: c.getContext('2d'), viewport }).promise;
                    return c.getContext('2d').getImageData(0, 0, c.width, c.height);
                }
                function buildTemplate(qr) {
                    const t = { template_id: (qr && qr.tpl) || 'auto', layout_meta: qr || {}, questions: [] };
                    const qs = (qr && qr.qs) || 1;
                    const qe = (qr && qr.qe) || (qs + 9);
                    const total = Math.max(1, qe - qs + 1);
                    const oc = (qr && typeof qr.oc === 'string') ? qr.oc : '';
                    const ALL = ['A', 'B', 'C', 'D', 'E'];
                    for (let i = 0; i < total; i++) {
                        let nopt = 5; // legado (sem oc): 5 opções
                        if (oc && i < oc.length) { const d = parseInt(oc[i], 10); if (!isNaN(d)) nopt = d; }
                        t.questions.push({ question_number: qs + i, option_labels_json: ALL.slice(0, Math.max(0, Math.min(5, nopt))) });
                    }
                    return t;
                }

                async function runDebug(file) {
                    try {
                        setStatus('Carregando OpenCV.js (pode levar alguns segundos)…');
                        await ensureOpenCv();
                        if (!window.OmrEngine) { setStatus('Motor OMR indisponível — recarregue a página (Ctrl+F5).'); return; }

                        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
                        setStatus(isPdf ? 'Renderizando PDF…' : 'Processando imagem…');
                        const imageData = isPdf ? await pdfToImageData(file) : await fileToImageData(file);

                        let qr = null;
                        if (window.jsQR) {
                            const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
                            if (code) { try { qr = JSON.parse(code.data); } catch (e) {} }
                        }

                        const engine = new window.OmrEngine(false);
                        const mat = cv.matFromImageData(imageData);
                        cv.imshow('dbg-original', mat);
                        const origCanvas = document.getElementById('dbg-original');
                        const octx = origCanvas.getContext('2d');

                        const pre = engine.preprocess(mat);
                        const corners = engine.findCornerMarkers(pre.thresh, pre.gray);

                        const r = Math.max(10, mat.cols / 80);
                        octx.lineWidth = Math.max(3, mat.cols / 250);
                        octx.strokeStyle = '#16a34a'; octx.fillStyle = '#16a34a';
                        octx.font = 'bold ' + Math.max(18, Math.round(mat.cols / 45)) + 'px sans-serif';
                        const labels = ['TL', 'TR', 'BR', 'BL'];
                        corners.forEach((c, i) => {
                            octx.beginPath(); octx.arc(c.x, c.y, r, 0, 2 * Math.PI); octx.stroke();
                            octx.fillText(labels[i] || i, c.x + r + 4, c.y);
                        });

                        let summary = 'QR Code: ' + (qr ? 'LIDO' : 'NÃO lido');
                        if (qr) summary += '  (tpl_id=' + (qr.tpl_id ?? '-') + ', cols=' + (qr.cols ?? '-') + ', rpp=' + (qr.rpp ?? '-') + ', g=' + (qr.g ? '[' + qr.g.join(',') + ']' : 'AUSENTE') + ')';
                        summary += '\nMarcadores encontrados: ' + corners.length + '/4';

                        if (corners.length < 4) {
                            mat.delete(); pre.gray.delete(); pre.thresh.delete();
                            setStatus(summary + '\n⚠ Sem os 4 marcadores não há como corrigir a perspectiva. Veja na imagem original se os círculos verdes caíram nos quadrados pretos.');
                            return;
                        }

                        const warped = engine.warp(pre.gray, corners, 2480, 3508);
                        const template = buildTemplate(qr);
                        const answers = engine.readBubbles(warped, template, null);

                        cv.imshow('dbg-warped', warped);
                        const wctx = document.getElementById('dbg-warped').getContext('2d');
                        wctx.font = '13px monospace';
                        let det = 0, dbl = 0, blank = 0, unc = 0;
                        answers.forEach(a => {
                            if (a.status === 'DOUBLE') dbl++;
                            else if (a.status === 'UNCERTAIN') unc++;
                            else if (a.selected == null) blank++;
                            else det++;
                            Object.keys(a.boxes || {}).forEach(opt => {
                                const b = a.boxes[opt]; const sc = (a.scores && a.scores[opt]) || 0; const sel = a.selected === opt;
                                wctx.strokeStyle = sel ? '#16a34a' : (a.status === 'DOUBLE' ? '#dc2626' : '#9ca3af');
                                wctx.lineWidth = sel ? 3 : 1.5;
                                wctx.strokeRect(b.x, b.y, b.width, b.height);
                                wctx.fillStyle = sel ? '#15803d' : '#777';
                                wctx.fillText(sc.toFixed(2), b.x, b.y - 3);
                            });
                        });

                        summary += '\nResultado: ' + det + ' lidas, ' + dbl + ' duplas, ' + unc + ' incertas, ' + blank + ' em branco (de ' + answers.length + ' questões)';
                        setStatus(summary);

                        const tbl = document.getElementById('dbg-table');
                        tbl.innerHTML = '';
                        answers.forEach(a => {
                            const vals = Object.values(a.scores || {});
                            const mx = vals.length ? Math.max.apply(null, vals) : 0;
                            const color = a.selected != null ? 'text-green-700' : (a.status === 'DOUBLE' ? 'text-red-600' : 'text-gray-400');
                            const div = document.createElement('div');
                            div.className = 'p-1 rounded border border-duo-border/50 ' + color;
                            div.textContent = 'Q' + a.q + ': ' + (a.selected ?? '—') + ' (' + a.status + ', máx ' + mx.toFixed(2) + ')';
                            tbl.appendChild(div);
                        });

                        mat.delete(); pre.gray.delete(); pre.thresh.delete(); warped.delete();
                    } catch (err) {
                        console.error(err);
                        setStatus('Erro: ' + (err.message || err));
                    }
                }

                const fileEl = document.getElementById('dbg-file');
                if (fileEl) fileEl.addEventListener('change', (e) => { if (e.target.files[0]) runDebug(e.target.files[0]); });
            })();
        </script>
    @endpush
</x-app-layout>
