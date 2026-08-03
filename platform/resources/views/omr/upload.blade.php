<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('institution.omr.index') }}">OMR</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Enviar Gabarito</span>
                </nav>
                <h1 class="page-title">Enviar Gabarito (Scan)</h1>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl" x-data="{
        preview: null,
        qrDetected: false,
        processing: false,
        file: null,
        
        scanQr(fileInput) {
            if(!fileInput) return;
            this.file = fileInput;
            this.preview = URL.createObjectURL(fileInput);
            
            const img = new Image();
            img.src = this.preview;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                ctx.drawImage(img, 0, 0);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                
                if(window.jsQR) {
                    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                    if(code) {
                        try {
                            const data = JSON.parse(code.data);
                            if(data.e) {
                                const select = document.getElementById('exam_id');
                                const option = Array.from(select.options).find(opt => opt.value == data.e);
                                if(option) {
                                    select.value = data.e;
                                    this.qrDetected = true;
                                    document.getElementById('hidden_copy_id').value = data.c || '';
                                    
                                    // Pass dynamic layout meta if provided in QR
                                    let layoutMeta = {};
                                    if (data.g) layoutMeta.g = data.g;
                                    if (data.cols) layoutMeta.cols = data.cols;
                                    if (data.rpp) layoutMeta.rpp = data.rpp;
                                    if (Object.keys(layoutMeta).length > 0) {
                                        document.getElementById('hidden_layout_meta').value = JSON.stringify(layoutMeta);
                                    }
                                }
                            }
                        } catch(e) {
                            console.warn('QR parse falhou:', e);
                        }
                    }
                }
            };
        },

        async processAndSubmit(e) {
            e.preventDefault();
            if (!this.file) return;

            this.processing = true;
            let engine = null;

            try {
                // 1. Prepare image data
                const img = new Image();
                img.src = this.preview;
                await new Promise((resolve) => { img.onload = resolve; });

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                ctx.drawImage(img, 0, 0);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                // 2. Start Engine
                engine = new window.OmrBrowserEngine({
                    opencvUrl: @js(asset('vendor/opencv/opencv-4.8.0.js')),
                    opencvFallbackUrls: [@js(url('vendor/opencv/opencv-4.8.0.js'))],
                    debug: false
                });

                // 3. Process
                const result = await engine.processImage(imageData);
                
                // 4. Inject payload into form
                document.getElementById('omr_payload').value = JSON.stringify(result);
                
                // 5. Submit form
                e.target.submit();
            } catch (err) {
                console.error('Erro no processamento OMR:', err);
                alert('Erro ao processar imagem: ' + err.message + '\n\nTente tirar uma foto melhor com os 4 marcadores visíveis.');
                this.processing = false;
            } finally {
                engine?.terminate();
            }
        }
    }">
        <div class="card p-8 mb-12 relative">
            
            <!-- Loading Overlay -->
            <div x-show="processing" class="absolute inset-0 bg-white/90 backdrop-blur-sm z-50 flex flex-col items-center justify-center rounded-2xl"
                style="display: none;" role="status" aria-live="polite">
                <span aria-hidden="true" class="loading-spinner mb-4"></span>
                <h2 class="text-xl font-bold text-duo-heading">Processando Gabarito...</h2>
                <p class="text-gray-500 mt-2">Nossa IA está lendo as bolhas no seu navegador.</p>
            </div>

            <form action="{{ route('institution.omr.store') }}" method="POST" enctype="multipart/form-data"
                class="space-y-6" @submit="processAndSubmit">
                @csrf
                <input type="hidden" name="omr_payload" id="omr_payload" value="">

                <div>
                    <label for="exam_id" class="input-label flex justify-between items-center">
                        Avaliação
                        <span x-show="qrDetected"
                            class="text-xs text-green-600 font-bold bg-green-100 px-2 py-0.5 rounded"
                            style="display: none;">✔ Detectada pelo QR Code</span>
                    </label>
                    <select name="exam_id" id="exam_id" class="input-field"
                        :class="qrDetected ? 'border-green-500 bg-green-50' : ''" required>
                        <option value="">Selecione a avaliação...</option>
                        @foreach($exams as $exam)
                            <option value="{{ $exam->id }}">{{ $exam->title }} ({{ $exam->access_code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="student_id" class="input-label">Aluno (opcional)</label>
                    <select id="student_id" name="student_id" class="input-field">
                        <option value="">Identificar depois...</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Se não selecionado, poderá ser vinculado na conferência.</p>
                </div>

                <div>
                    <label for="answer-sheet-image" class="input-label">Imagem do Gabarito</label>
                    <div class="relative">
                        <input type="hidden" name="copy_id" id="hidden_copy_id" value="">
                        <input type="hidden" name="layout_meta" id="hidden_layout_meta" value="">
                        <input id="answer-sheet-image" type="file" name="image" accept="image/*" required
                            class="input-field file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20"
                            @change="scanQr($event.target.files[0])">
                        <div x-show="preview" class="mt-4 border-2 border-duo-border rounded-xl overflow-hidden">
                            <img :src="preview" alt="Pré-visualização da imagem do gabarito selecionado"
                                class="max-h-96 w-full object-contain bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t-2 border-duo-border flex justify-end gap-3">
                    <a href="{{ route('institution.omr.index') }}" class="btn-secondary btn-sm">Cancelar</a>
                    <button type="submit" class="btn-primary btn-sm text-xs uppercase tracking-wider" :disabled="processing">
                        <span aria-hidden="true" class="material-symbols-outlined text-[18px]">upload</span>
                        Enviar e Processar
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <!-- The app.js is already included by the layout which sets window.OmrBrowserEngine -->
    @endpush
</x-app-layout>
