<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">Leitura OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Web Scan</span>
                </nav>
                <h1 class="page-title">Digitalizar Gabarito</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">Capture o gabarito com a câmera ou envie uma foto do
                    dispositivo.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('institution.omr.debug') }}" class="btn-secondary btn-sm flex items-center gap-1" title="Diagnóstico visual da leitura">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">bug_report</span> Debug de leitura
                </a>
                <a href="{{ route('institution.omr.index') }}" class="btn-secondary btn-sm">Voltar</a>
            </div>
        </div>
    </x-slot>

    <div x-data="webScan()" class="grid grid-cols-1 lg:grid-cols-5 gap-8 mb-12">
        {{-- Overlay enquanto o motor OMR lê o gabarito --}}
        <div x-show="processing" style="display:none"
            class="fixed inset-0 z-50 bg-white/85 backdrop-blur-sm flex flex-col items-center justify-center"
            role="status" aria-live="polite">
            <span aria-hidden="true" class="loading-spinner mb-4"></span>
            <h2 class="text-xl font-black text-duo-heading">Lendo o gabarito...</h2>
            <p class="text-gray-500 mt-1 text-sm">Detectando QR Code e bolhas no seu navegador.</p>
        </div>

        {{-- Camera / Preview Area (3 cols) --}}
        <div class="lg:col-span-3 space-y-4">
            <div class="card overflow-hidden relative" style="min-height: 400px;">
                {{-- Camera Feed --}}
                <div x-show="mode === 'camera'" class="relative group" :class="flash ? 'brightness-150 transition-all duration-75' : ''">
                    <video x-ref="video" autoplay playsinline aria-label="Visualização ao vivo da câmera para digitalizar o gabarito"
                        class="w-full rounded-xl bg-black"
                        style="max-height: 500px; object-fit: cover;"></video>

                    {{-- Laser Scanner Animation --}}
                    <div class="laser-line" x-show="cameraReady"></div>

                    {{-- Precision Corner Guides --}}
                    <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="relative w-[85%] h-[85%] border border-white/20 rounded-lg">
                            <!-- Top Left -->
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary rounded-tl-lg"></div>
                            <!-- Top Right -->
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary rounded-tr-lg"></div>
                            <!-- Bottom Left -->
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary rounded-bl-lg"></div>
                            <!-- Bottom Right -->
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary rounded-br-lg"></div>
                            
                            {{-- Center hint --}}
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-12 h-px bg-white/20"></div>
                                <div class="h-12 w-px bg-white/20 absolute"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Scanning Status Badge --}}
                    <div x-show="cameraReady" class="absolute top-4 left-4 z-30">
                        <div class="bg-primary/90 text-white text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded flex items-center gap-2 shadow-lg">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                            </span>
                            Sistema Ativo
                        </div>
                    </div>

                    {{-- Camera status --}}
                    <div x-show="!cameraReady"
                        class="absolute inset-0 flex items-center justify-center bg-gray-900/80 rounded-xl z-40">
                        <div class="text-center text-white">
                            <span aria-hidden="true" class="material-symbols-outlined text-5xl mb-3 animate-pulse block text-primary">videocam</span>
                            <p class="font-bold">Iniciando câmera...</p>
                            <p class="text-sm text-gray-300 mt-1">Permita o acesso à câmera do navegador.</p>
                        </div>
                    </div>
                </div>

                <style>
                    @keyframes laser-scan {
                        0% { top: 10%; opacity: 0; }
                        20% { opacity: 1; }
                        80% { opacity: 1; }
                        100% { top: 90%; opacity: 0; }
                    }
                    .laser-line {
                        position: absolute;
                        left: 10%;
                        right: 10%;
                        height: 3px;
                        background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, #3b82f6, transparent);
                        box-shadow: 0 0 20px rgba(59, 130, 246, 0.8), 0 0 10px rgba(59, 130, 246, 1);
                        z-index: 20;
                        animation: laser-scan 4s linear infinite;
                        pointer-events: none;
                    }
                </style>

                {{-- Captured Photo Preview --}}
                <div x-show="mode === 'preview'" class="relative">
                    <img x-ref="preview" alt="Pré-visualização da foto capturada do gabarito"
                        class="w-full rounded-xl" style="max-height: 500px; object-fit: contain;" />

                    {{-- Retake overlay button --}}
                    <div class="absolute top-4 right-4">
                        <button @click="retake()" type="button"
                            class="btn-secondary btn-sm flex items-center gap-1 shadow-lg">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">replay</span> Nova Foto
                        </button>
                    </div>
                </div>

                {{-- Upload mode --}}
                <div x-show="mode === 'upload'" class="flex items-center justify-center p-12">
                    <label class="flex flex-col items-center gap-4 cursor-pointer group">
                        <div
                            class="p-6 bg-primary/5 rounded-2xl border-2 border-dashed border-primary/30 group-hover:border-primary group-hover:bg-primary/10 transition-all">
                            <span aria-hidden="true" class="material-symbols-outlined text-5xl text-primary">upload_file</span>
                        </div>
                        <div class="text-center">
                            <p class="font-bold text-duo-heading">Clique para selecionar uma foto</p>
                            <p class="text-sm text-gray-500 mt-1">JPG, PNG ou WebP • Máx. 10MB</p>
                        </div>
                        <input type="file" accept="image/*" class="hidden" @change="handleFileUpload($event)" />
                    </label>
                </div>

                {{-- Error state --}}
                <div x-show="mode === 'error'" class="flex items-center justify-center p-12">
                    <div class="text-center">
                        <span aria-hidden="true" class="material-symbols-outlined text-5xl text-red-400 mb-3 block">videocam_off</span>
                        <p class="font-bold text-duo-heading">Câmera não disponível</p>
                        <p class="text-sm text-gray-500 mt-1" x-text="errorMessage"></p>
                        <button @click="switchToUpload()" class="btn-primary btn-sm mt-4">
                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">upload_file</span> Enviar Foto
                        </button>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3">
                <template x-if="mode === 'camera' && cameraReady">
                    <button @click="capture()" type="button"
                        class="btn-primary flex-1 py-4 text-base flex items-center justify-center gap-2">
                        <span aria-hidden="true" class="material-symbols-outlined text-[24px]">photo_camera</span>
                        Capturar Foto
                    </button>
                </template>

                {{-- Toggle Camera / Upload --}}
                <button @click="mode === 'camera' || mode === 'error' ? switchToUpload() : startCamera()" type="button"
                    class="btn-secondary btn-sm flex items-center gap-1" x-show="mode !== 'preview'">
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]"
                        x-text="mode === 'upload' ? 'videocam' : 'upload_file'"></span>
                    <span x-text="mode === 'upload' ? 'Usar Câmera' : 'Enviar Foto'"></span>
                </button>
            </div>

            {{-- Hidden canvas for capture --}}
            <canvas x-ref="canvas" class="hidden"></canvas>
        </div>

        {{-- Form Panel (2 cols) --}}
        <div class="lg:col-span-2">
            <form x-ref="form" action="{{ route('institution.omr.store') }}" method="POST" enctype="multipart/form-data"
                @submit="processAndSubmit" class="card p-6 space-y-6 sticky top-4">
                @csrf
                {{-- Preenchidos via JS: motor OMR (omr_payload) e dados do QR (copy_id, layout_meta) --}}
                <input type="hidden" name="omr_payload" x-ref="payloadInput" value="">
                <input type="hidden" name="copy_id" x-ref="copyInput" value="">
                <input type="hidden" name="layout_meta" x-ref="layoutInput" value="">

                <h2
                    class="text-lg font-extrabold text-duo-heading flex items-center gap-2 border-b-2 border-duo-border pb-3">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">assignment</span> Dados do Gabarito
                </h2>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <div class="flex items-center gap-2 text-sm">
                                <span aria-hidden="true" class="material-symbols-outlined text-[16px]">error</span>
                                {{ $error }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-2">
                    <label for="webscan-exam" class="input-label flex items-center justify-between">
                        <span>Avaliação *</span>
                        <span x-show="qrDetected" style="display:none"
                            class="text-[10px] text-green-600 font-bold bg-green-100 px-2 py-0.5 rounded">✔ Detectada pelo QR</span>
                    </label>
                    <select id="webscan-exam" name="exam_id" x-ref="examSelect" required class="input-field"
                        :class="qrDetected ? 'border-green-500 bg-green-50' : ''">
                        <option value="">Selecione a avaliação</option>
                        @foreach($exams as $exam)
                            <option value="{{ $exam->id }}">{{ $exam->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="webscan-student" class="input-label">Aluno (opcional)</label>
                    <select id="webscan-student" name="student_id" class="input-field">
                        <option value="">Identificar depois</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Hidden file input (populated by JS) --}}
                <input x-ref="fileInput" type="file" name="image" accept="image/*" class="hidden" />

                {{-- Status indicator --}}
                <div class="p-4 rounded-xl border-2 transition-colors" role="status" aria-live="polite"
                    :class="capturedFile ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-duo-border'">
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-[24px]"
                            :class="capturedFile ? 'text-green-500' : 'text-gray-400'"
                            x-text="capturedFile ? 'check_circle' : 'image'"></span>
                        <div>
                            <p class="font-bold text-sm" :class="capturedFile ? 'text-green-700' : 'text-gray-500'"
                                x-text="capturedFile ? 'Foto capturada!' : 'Nenhuma foto capturada'"></p>
                            <p class="text-xs text-gray-400" x-show="capturedFile" x-text="capturedFileName"></p>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="!capturedFile || processing"
                    class="btn-primary w-full py-4 flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span aria-hidden="true" class="material-symbols-outlined" :class="processing ? 'animate-spin' : ''"
                        x-text="processing ? 'progress_activity' : 'send'">send</span>
                    <span x-text="processing ? 'Lendo gabarito...' : 'Ler e Enviar para Conferência'">Enviar para Conferência</span>
                </button>

                <p class="text-xs text-gray-400 text-center">
                    O sistema lê o QR e as bolhas automaticamente; depois você confere antes de gerar as notas.
                </p>
            </form>
        </div>
    </div>

    @push('scripts')
        {{-- window.OmrBrowserEngine é exposto pelo app.js (carregado no layout via @vite) --}}
        <script>
            function webScan() {
                return {
                    mode: 'camera', // 'camera', 'preview', 'upload', 'error'
                    cameraReady: false,
                    flash: false,
                    capturedFile: null,
                    capturedFileName: '',
                    errorMessage: '',
                    stream: null,
                    qrDetected: false,
                    processing: false,

                    init() {
                        this.startCamera();
                    },

                    async startCamera() {
                        this.mode = 'camera';
                        this.cameraReady = false;

                        try {
                            this.stream = await navigator.mediaDevices.getUserMedia({
                                video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }
                            });
                            this.$refs.video.srcObject = this.stream;
                            this.$refs.video.onloadedmetadata = () => {
                                this.cameraReady = true;
                            };
                        } catch (err) {
                            console.error('Camera error:', err);
                            this.errorMessage = err.name === 'NotAllowedError'
                                ? 'Acesso à câmera negado. Verifique as permissões do navegador.'
                                : 'Não foi possível acessar a câmera. Use o upload de foto.';
                            this.mode = 'error';
                        }
                    },

                    capture() {
                        const video = this.$refs.video;
                        const canvas = this.$refs.canvas;

                        this.flash = true;
                        setTimeout(() => this.flash = false, 150);

                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext('2d').drawImage(video, 0, 0);

                        // Show preview
                        this.$refs.preview.src = canvas.toDataURL('image/jpeg', 0.92);
                        this.mode = 'preview';

                        // Stop camera
                        this.stopCamera();

                        // Convert to file and attach to form
                        canvas.toBlob((blob) => {
                            const timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
                            const file = new File([blob], `scan_${timestamp}.jpg`, { type: 'image/jpeg' });
                            this.attachFile(file);
                        }, 'image/jpeg', 0.92);
                    },

                    retake() {
                        this.capturedFile = null;
                        this.capturedFileName = '';
                        this.qrDetected = false;
                        if (this.$refs.payloadInput) this.$refs.payloadInput.value = '';
                        if (this.$refs.copyInput) this.$refs.copyInput.value = '';
                        if (this.$refs.layoutInput) this.$refs.layoutInput.value = '';
                        this.clearFileInput();
                        this.startCamera();
                    },

                    switchToUpload() {
                        this.stopCamera();
                        this.mode = 'upload';
                    },

                    handleFileUpload(event) {
                        const file = event.target.files[0];
                        if (!file) return;

                        // Show preview
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.$refs.preview.src = e.target.result;
                            this.mode = 'preview';
                        };
                        reader.readAsDataURL(file);

                        this.attachFile(file);
                    },

                    attachFile(file) {
                        // Create a DataTransfer to set the file input value
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        this.$refs.fileInput.files = dt.files;

                        this.capturedFile = file;
                        this.capturedFileName = `${file.name} (${(file.size / 1024).toFixed(0)} KB)`;

                        // Tenta ler o QR Code da folha para auto-selecionar a avaliação
                        this.scanQr(file);
                    },

                    // Constrói ImageData a partir de um File (usado por QR e pelo motor OMR)
                    async fileToImageData(file) {
                        const url = URL.createObjectURL(file);
                        try {
                            const img = new Image();
                            img.src = url;
                            await new Promise((resolve, reject) => {
                                img.onload = resolve;
                                img.onerror = () => reject(new Error('Falha ao carregar a imagem.'));
                            });
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            canvas.width = img.naturalWidth;
                            canvas.height = img.naturalHeight;
                            ctx.drawImage(img, 0, 0);
                            return ctx.getImageData(0, 0, canvas.width, canvas.height);
                        } finally {
                            URL.revokeObjectURL(url);
                        }
                    },

                    // Lê o QR Code do gabarito e seleciona a avaliação + copy_id + layout
                    async scanQr(file) {
                        if (!file || !window.jsQR) return;
                        try {
                            const imageData = await this.fileToImageData(file);
                            const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                            if (!code) return;

                            const data = JSON.parse(code.data);
                            if (!data.e) return;

                            const select = this.$refs.examSelect;
                            const option = Array.from(select.options).find(opt => opt.value == data.e);
                            if (option) {
                                select.value = String(data.e);
                                this.qrDetected = true;
                            }
                            this.$refs.copyInput.value = data.c || '';

                            const layoutMeta = {};
                            if (data.g) layoutMeta.g = data.g;
                            if (data.cols) layoutMeta.cols = data.cols;
                            if (data.rpp) layoutMeta.rpp = data.rpp;
                            if (Object.keys(layoutMeta).length > 0) {
                                this.$refs.layoutInput.value = JSON.stringify(layoutMeta);
                            }
                        } catch (err) {
                            console.warn('Leitura do QR falhou:', err);
                        }
                    },

                    // Roda o motor OMR no navegador e injeta o resultado antes de enviar o form
                    async processAndSubmit(e) {
                        e.preventDefault();
                        if (!this.capturedFile || this.processing) return;

                        if (!window.OmrBrowserEngine) {
                            // Motor indisponível: envia a foto crua para conferência manual
                            this.$refs.payloadInput.value = '';
                            e.target.submit();
                            return;
                        }

                        this.processing = true;
                        let engine = null;
                        try {
                            const imageData = await this.fileToImageData(this.capturedFile);
                            engine = new window.OmrBrowserEngine({
                                opencvUrl: @js(asset('vendor/opencv/opencv-4.8.0.js')),
                                opencvFallbackUrls: [@js(url('vendor/opencv/opencv-4.8.0.js'))],
                                debug: false
                            });
                            const result = await engine.processImage(imageData);
                            console.log('[OMR] resultado da leitura:', result);

                            // Diagnóstico: se nada foi detectado, mostra QR/marcadores/preenchimento
                            const answers = result.answers || [];
                            const detected = answers.filter(a => a.selected !== null && a.selected !== undefined).length;
                            const q = result.quality || {};
                            const corners = (q.corners_found !== undefined) ? q.corners_found : '?';
                            const qrOk = result.qrData ? 'sim' : 'não';

                            if (detected === 0) {
                                this.processing = false;
                                const sample = answers.slice(0, 4).map(a => {
                                    const vals = Object.values(a.scores || {});
                                    const mx = vals.length ? Math.max.apply(null, vals) : 0;
                                    return 'Q' + a.q + ': ' + a.status + ' (máx ' + mx.toFixed(2) + ')';
                                }).join('\n');
                                const diag =
                                    'Diagnóstico da leitura automática:\n' +
                                    '• QR Code lido: ' + qrOk + '\n' +
                                    '• Marcadores encontrados: ' + corners + '/4\n' +
                                    '• Respostas detectadas: 0 de ' + answers.length + '\n' +
                                    (sample ? '\nPreenchimento detectado (amostra 0–1):\n' + sample + '\n' : '') +
                                    '\nDica: foto reta e bem iluminada, com os 4 quadrados pretos e o QR nítidos, sem sombra/reflexo.';
                                if (!confirm(diag + '\n\nEnviar mesmo assim para conferência manual?')) {
                                    return; // permite tirar outra foto
                                }
                            }

                            this.$refs.payloadInput.value = JSON.stringify(result);
                            e.target.submit();
                        } catch (err) {
                            console.error('Erro no processamento OMR:', err);
                            this.processing = false;
                            const proceed = confirm(
                                'Não foi possível ler as bolhas automaticamente:\n' + (err.message || err) +
                                '\n\nGaranta que os 4 marcadores de canto e o QR Code estejam visíveis.\n\n' +
                                'Deseja enviar mesmo assim para conferência manual?'
                            );
                            if (proceed) {
                                this.$refs.payloadInput.value = '';
                                e.target.submit();
                            }
                        } finally {
                            // OpenCV reserves a sizable worker heap. Each submission processes one
                            // photo, so free it immediately to keep the scanner responsive.
                            engine?.terminate();
                        }
                    },

                    clearFileInput() {
                        const dt = new DataTransfer();
                        this.$refs.fileInput.files = dt.files;
                    },

                    stopCamera() {
                        if (this.stream) {
                            this.stream.getTracks().forEach(track => track.stop());
                            this.stream = null;
                        }
                        this.cameraReady = false;
                    },

                    destroy() {
                        this.stopCamera();
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>
