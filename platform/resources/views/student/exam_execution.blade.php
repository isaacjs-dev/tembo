<x-app-layout>
    @php
        $totalQuestions = $exam->questions->count();
        $initialAnswered = collect($savedAnswers)->filter(fn ($answer) => $answer !== null && $answer !== '')->count();
        $initialProgress = $totalQuestions > 0 ? round(($initialAnswered / $totalQuestions) * 100) : 0;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <p class="text-sm font-bold text-gray-600">Tentativa nº {{ $submission->attempt_number }}</p>
                <h1 class="text-2xl md:text-3xl font-extrabold text-duo-heading">{{ $exam->title }}</h1>
                <p class="mt-1 text-sm text-gray-700">
                    {{ $exam->discipline?->name ?: 'Disciplina não informada' }}
                    <span aria-hidden="true">·</span>
                    Professor {{ $exam->author?->name ?: 'não informado' }}
                    @if($exam->organization?->name)
                        <span aria-hidden="true">·</span> {{ $exam->organization->name }}
                    @endif
                </p>
            </div>

            @if($submission->deadline_at)
                <div id="examTimer" role="timer" aria-label="Tempo restante"
                    class="bg-red-50 text-red-800 px-4 py-2 rounded-xl border-2 border-red-200 font-bold flex items-center gap-2 self-start sm:self-auto">
                    <span class="material-symbols-outlined" aria-hidden="true">timer</span>
                    <span>Tempo restante: <strong id="timerValue">--:--</strong></span>
                </div>
            @else
                <div class="bg-blue-50 text-blue-900 px-4 py-2 rounded-xl border-2 border-blue-200 font-bold">
                    Sem limite de tempo
                </div>
            @endif
        </div>

        <div id="questionProgress" role="progressbar" aria-label="Progresso das respostas"
            aria-valuemin="0" aria-valuemax="{{ $totalQuestions }}" aria-valuenow="{{ $initialAnswered }}">
            <div class="flex items-center justify-between gap-4 text-sm font-bold text-gray-700 mb-2">
                <span id="progressText">{{ $initialAnswered }} de {{ $totalQuestions }} respondidas</span>
                <span id="progressPercent">{{ $initialProgress }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 border border-gray-300 overflow-hidden">
                <div id="progressFill" class="bg-primary h-full rounded-full transition-[width] duration-300"
                    style="width: {{ $initialProgress }}%"></div>
            </div>
        </div>
    </x-slot>

    <form action="{{ route('student.exam.submit', $exam) }}" method="POST" id="examForm"
        data-autosave-url="{{ route('student.exam.autosave', $exam) }}"
        data-submit-url="{{ route('student.exam.submit', $exam) }}"
        data-dashboard-url="{{ route('student.dashboard') }}"
        data-client-token="{{ $submission->client_token }}">
        @csrf
        <input type="hidden" name="client_token" value="{{ $submission->client_token }}">

        <div id="savePanel"
            class="sticky top-0 z-20 mb-5 rounded-xl border-2 border-blue-200 bg-blue-50 px-4 py-3 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <p id="saveStatus" class="font-bold text-blue-950" role="status" aria-live="polite">Respostas carregadas</p>
                <p id="saveDetail" class="text-sm text-blue-900">As alterações serão salvas automaticamente.</p>
            </div>
            <button type="button" id="saveNowButton"
                class="shrink-0 px-4 py-2 rounded-lg border-2 border-blue-700 text-blue-900 bg-white font-bold hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">
                Salvar agora
            </button>
        </div>

        <div id="timerAnnouncement" class="sr-only" aria-live="assertive"></div>

        <div class="space-y-8 mb-40" id="questionBlocks">
            @php $questionNumber = 0; @endphp
            @foreach($questionBlocks as $blockIndex => $block)
                <section data-question-block="{{ $blockIndex }}" @if($blockIndex > 0) hidden @endif
                    class="space-y-8" aria-label="Bloco {{ $blockIndex + 1 }} de {{ $questionBlocks->count() }}">
            @foreach($block as $question)
                @php $questionNumber++; @endphp
                @php $savedAnswer = $savedAnswers[$question->id] ?? null; @endphp
                <fieldset class="bg-white rounded-2xl border-2 border-duo-border p-5 md:p-8 shadow-sm"
                    id="question-{{ $question->id }}" data-question-id="{{ $question->id }}">
                    <legend class="w-full px-1">
                        <span class="flex items-start gap-3 md:gap-4">
                            <span aria-hidden="true"
                                class="shrink-0 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center font-extrabold text-gray-700 border-2 border-gray-300">
                                {{ $questionNumber }}
                            </span>
                            <span class="text-base md:text-lg font-bold text-gray-900 leading-relaxed pt-1">
                                <span class="sr-only">Questão {{ $questionNumber }}. </span>
                                {{ $question->content['statement'] }}
                            </span>
                        </span>
                    </legend>

                    <div class="mt-6 md:ml-14">
                        @if($question->type === 'multiple_choice')
                            @php
                                $optionIndexes = array_keys($question->content['options'] ?? []);
                                if (filter_var($exam->settings['shuffle_options'] ?? false, FILTER_VALIDATE_BOOL)) {
                                    usort(
                                        $optionIndexes,
                                        fn ($left, $right) => strcmp(
                                            hash('sha256', "{$submission->client_token}:{$question->id}:option:{$left}"),
                                            hash('sha256', "{$submission->client_token}:{$question->id}:option:{$right}")
                                        )
                                    );
                                }
                            @endphp
                            <div class="space-y-3">
                                @foreach($optionIndexes as $optionIndex)
                                    @php $optionText = $question->content['options'][$optionIndex]; @endphp
                                    <label class="relative flex items-center cursor-pointer">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $optionIndex }}"
                                            @checked((string) $savedAnswer === (string) $optionIndex)
                                            class="peer sr-only">
                                        <span aria-hidden="true"
                                            class="pointer-events-none absolute left-4 size-6 rounded-full border-2 border-gray-400 bg-white
                                                peer-checked:border-[7px] peer-checked:border-blue-700"></span>
                                        <span
                                            class="block w-full py-4 pl-14 pr-4 border-2 border-gray-300 rounded-xl hover:border-secondary hover:bg-blue-50 transition-all
                                                peer-checked:border-blue-700 peer-checked:bg-blue-50
                                                peer-focus-visible:outline-none peer-focus-visible:ring-4 peer-focus-visible:ring-blue-200 peer-focus-visible:border-blue-800">
                                            <span class="font-bold text-gray-800">{{ $optionText }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($question->type === 'true_false')
                            @php
                                $trueFalseIndexes = [0, 1];
                                if (filter_var($exam->settings['shuffle_options'] ?? false, FILTER_VALIDATE_BOOL)) {
                                    usort(
                                        $trueFalseIndexes,
                                        fn ($left, $right) => strcmp(
                                            hash('sha256', "{$submission->client_token}:{$question->id}:option:{$left}"),
                                            hash('sha256', "{$submission->client_token}:{$question->id}:option:{$right}")
                                        )
                                    );
                                }
                            @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($trueFalseIndexes as $optionIndex)
                                    @php $optionText = $optionIndex === 0 ? 'Verdadeiro' : 'Falso'; @endphp
                                    <label class="relative block cursor-pointer">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $optionIndex }}"
                                            @checked((string) $savedAnswer === (string) $optionIndex)
                                            class="peer sr-only">
                                        <span
                                            class="flex items-center justify-center gap-3 p-5 border-2 border-gray-300 rounded-xl font-bold text-gray-800
                                                hover:border-blue-700 hover:bg-blue-50 peer-checked:border-blue-800 peer-checked:bg-blue-50
                                                peer-focus-visible:outline-none peer-focus-visible:ring-4 peer-focus-visible:ring-blue-200">
                                            <span class="material-symbols-outlined" aria-hidden="true">
                                                {{ $optionIndex === 0 ? 'check_circle' : 'cancel' }}
                                            </span>
                                            {{ $optionText }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <label for="answer-{{ $question->id }}" class="sr-only">
                                Resposta da questão {{ $questionNumber }}
                            </label>
                            <textarea id="answer-{{ $question->id }}" name="answers[{{ $question->id }}]" rows="7"
                                maxlength="20000" placeholder="Digite sua resposta. Ela será salva automaticamente."
                                class="block w-full px-4 py-3 bg-background-light border-2 border-gray-300 rounded-xl text-gray-900
                                    focus:outline-none focus:border-blue-800 focus:ring-4 focus:ring-blue-100 transition-all font-medium">{{ $savedAnswer }}</textarea>
                            <p class="text-xs text-gray-600 mt-2">Máximo de 20.000 caracteres.</p>
                        @endif
                    </div>
                </fieldset>
            @endforeach
                </section>
            @endforeach

            @if($questionBlocks->count() > 1)
                <nav class="sticky bottom-24 z-20 flex items-center justify-between gap-3 rounded-xl border-2 border-gray-300 bg-white p-3 shadow-lg"
                    aria-label="Navegação entre blocos da avaliação">
                    <button type="button" id="previousQuestionBlock" class="btn-secondary" disabled>
                        Anterior
                    </button>
                    <span id="questionBlockStatus" class="text-sm font-extrabold text-gray-700" aria-live="polite">
                        Bloco 1 de {{ $questionBlocks->count() }}
                    </span>
                    <button type="button" id="nextQuestionBlock" class="btn-primary">
                        Próximo
                    </button>
                </nav>
            @endif
        </div>

        <div
            class="fixed bottom-0 left-0 md:left-64 right-0 bg-white border-t-2 border-duo-border px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-[0_-10px_40px_rgba(0,0,0,0.08)] z-30">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-sm font-bold text-gray-700">
                    Você pode enviar com questões pendentes após confirmar.
                </p>
                <button type="submit" id="openSubmitDialog"
                    class="bg-primary hover:bg-primary-dark text-white px-7 py-4 w-full sm:w-auto rounded-xl
                        font-extrabold text-sm uppercase tracking-wider flex items-center justify-center gap-2
                        focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-200 focus-visible:ring-offset-2">
                    <span class="material-symbols-outlined" aria-hidden="true">send</span>
                    Revisar e enviar
                </button>
            </div>
        </div>
    </form>

    <dialog id="submitDialog"
        class="w-[min(92vw,32rem)] rounded-2xl border-2 border-gray-300 p-0 shadow-2xl backdrop:bg-black/50">
        <div class="p-6">
            <h2 class="text-2xl font-extrabold text-gray-900">Finalizar avaliação?</h2>
            <p id="pendingSummary" class="text-gray-700 mt-3"></p>
            <p class="text-sm text-gray-600 mt-3">
                Depois do envio, esta tentativa não poderá ser alterada.
            </p>
            <div id="submitError" class="hidden mt-4 p-3 bg-red-50 border border-red-300 rounded-lg text-red-900 font-bold"
                role="alert"></div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 mt-6">
                <button type="button" id="cancelSubmit"
                    class="px-5 py-3 rounded-xl border-2 border-gray-400 bg-white text-gray-900 font-bold
                        focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-gray-200">
                    Continuar respondendo
                </button>
                <button type="button" id="confirmSubmit"
                    class="px-5 py-3 rounded-xl bg-primary text-white font-extrabold
                        focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-200">
                    Confirmar envio
                </button>
            </div>
        </div>
    </dialog>

    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('examForm');
                if (!form) return;

                const token = form.dataset.clientToken;
                const storageKey = `avaliation:exam:{{ $exam->id }}:${token}`;
                const csrf = form.querySelector('input[name="_token"]').value;
                const saveStatus = document.getElementById('saveStatus');
                const saveDetail = document.getElementById('saveDetail');
                const savePanel = document.getElementById('savePanel');
                const progress = document.getElementById('questionProgress');
                const progressFill = document.getElementById('progressFill');
                const progressText = document.getElementById('progressText');
                const progressPercent = document.getElementById('progressPercent');
                const questionFields = [...form.querySelectorAll('[data-question-id]')];
                const questionBlocks = [...form.querySelectorAll('[data-question-block]')];
                const previousQuestionBlock = document.getElementById('previousQuestionBlock');
                const nextQuestionBlock = document.getElementById('nextQuestionBlock');
                const questionBlockStatus = document.getElementById('questionBlockStatus');
                const dialog = document.getElementById('submitDialog');
                const pendingSummary = document.getElementById('pendingSummary');
                const submitError = document.getElementById('submitError');
                const confirmSubmit = document.getElementById('confirmSubmit');
                let saveTimer = null;
                let saving = false;
                let queued = false;
                let autoSubmitting = false;
                let draftRevision = 0;
                let currentQuestionBlock = 0;

                const showQuestionBlock = (index) => {
                    currentQuestionBlock = Math.min(Math.max(index, 0), Math.max(questionBlocks.length - 1, 0));
                    questionBlocks.forEach((block, blockIndex) => {
                        block.hidden = blockIndex !== currentQuestionBlock;
                    });
                    if (previousQuestionBlock) previousQuestionBlock.disabled = currentQuestionBlock === 0;
                    if (nextQuestionBlock) nextQuestionBlock.disabled = currentQuestionBlock === questionBlocks.length - 1;
                    if (questionBlockStatus) {
                        questionBlockStatus.textContent = `Bloco ${currentQuestionBlock + 1} de ${questionBlocks.length}`;
                    }
                };

                previousQuestionBlock?.addEventListener('click', () => {
                    showQuestionBlock(currentQuestionBlock - 1);
                    document.getElementById('questionBlocks')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                nextQuestionBlock?.addEventListener('click', () => {
                    showQuestionBlock(currentQuestionBlock + 1);
                    document.getElementById('questionBlocks')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                showQuestionBlock(0);

                const collectAnswers = () => {
                    const answers = {};
                    questionFields.forEach((field) => {
                        const id = field.dataset.questionId;
                        const checked = field.querySelector('input[type="radio"]:checked');
                        const textarea = field.querySelector('textarea');
                        answers[id] = checked ? checked.value : (textarea ? textarea.value : null);
                    });
                    return answers;
                };

                const answeredIds = (answers = collectAnswers()) => Object.entries(answers)
                    .filter(([, value]) => value !== null && String(value).trim() !== '')
                    .map(([id]) => id);

                const updateProgress = () => {
                    const answered = answeredIds().length;
                    const total = questionFields.length;
                    const percent = total > 0 ? Math.round((answered / total) * 100) : 0;
                    progress.setAttribute('aria-valuenow', String(answered));
                    progressFill.style.width = `${percent}%`;
                    progressText.textContent = `${answered} de ${total} respondidas`;
                    progressPercent.textContent = `${percent}%`;
                };

                const setSaveState = (state, detail) => {
                    const styles = {
                        saving: ['Salvando respostas…', 'border-blue-200', 'bg-blue-50', 'text-blue-950'],
                        saved: ['Respostas salvas', 'border-green-300', 'bg-green-50', 'text-green-950'],
                        offline: ['Você está offline', 'border-amber-300', 'bg-amber-50', 'text-amber-950'],
                        error: ['Não foi possível salvar', 'border-red-300', 'bg-red-50', 'text-red-950'],
                    };
                    const config = styles[state] || styles.saving;
                    savePanel.className = `sticky top-0 z-20 mb-5 rounded-xl border-2 px-4 py-3 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2 ${config.slice(1).join(' ')}`;
                    saveStatus.textContent = config[0];
                    saveDetail.textContent = detail;
                };

                const persistOutbox = () => {
                    try {
                        draftRevision += 1;
                        localStorage.setItem(storageKey, JSON.stringify({
                            token,
                            answers: collectAnswers(),
                            updatedAt: new Date().toISOString(),
                            revision: draftRevision,
                        }));
                        return draftRevision;
                    } catch (_) {
                        setSaveState('error', 'O armazenamento local não está disponível neste navegador.');
                        return null;
                    }
                };

                const discardSyncedOutbox = (revision) => {
                    if (revision === null) return;

                    try {
                        const stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
                        if (Number(stored?.revision) === revision) {
                            localStorage.removeItem(storageKey);
                        }
                    } catch (_) {
                        // A resposta já está segura no servidor; não bloqueie a interface
                        // se o navegador impedir a limpeza do armazenamento local.
                    }
                };

                const restoreOutbox = () => {
                    try {
                        const stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
                        if (!stored || stored.token !== token || !stored.answers) return false;
                        draftRevision = Number.isFinite(Number(stored.revision))
                            ? Number(stored.revision)
                            : 0;

                        Object.entries(stored.answers).forEach(([id, value]) => {
                            const field = form.querySelector(`[data-question-id="${CSS.escape(id)}"]`);
                            if (!field || value === null) return;
                            const radio = field.querySelector(`input[type="radio"][value="${CSS.escape(String(value))}"]`);
                            const textarea = field.querySelector('textarea');
                            if (radio) radio.checked = true;
                            if (textarea && String(value) !== textarea.value) textarea.value = String(value);
                        });
                        setSaveState('saving', 'Um rascunho deste dispositivo foi recuperado e será sincronizado.');
                        return true;
                    } catch (_) {
                        return false;
                    }
                };

                const requestJson = async (url, method, body) => {
                    const response = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(body),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const error = new Error(payload.message || 'A operação não pôde ser concluída.');
                        error.status = response.status;
                        error.payload = payload;
                        throw error;
                    }
                    return payload;
                };

                const saveNow = async () => {
                    const revision = persistOutbox();
                    updateProgress();

                    if (!navigator.onLine) {
                        setSaveState('offline', 'O rascunho está guardado neste dispositivo e será enviado ao reconectar.');
                        return false;
                    }
                    if (saving) {
                        queued = true;
                        return false;
                    }

                    saving = true;
                    setSaveState('saving', 'Não feche a página enquanto sincronizamos.');
                    try {
                        const payload = await requestJson(form.dataset.autosaveUrl, 'PATCH', {
                            client_token: token,
                            answers: collectAnswers(),
                        });
                        discardSyncedOutbox(revision);
                        setSaveState('saved', `Último salvamento às ${new Date(payload.saved_at).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}.`);
                        return true;
                    } catch (error) {
                        if (error.status === 409 && error.payload?.status === 'timed_out') {
                            localStorage.removeItem(storageKey);
                            window.location.reload();
                            return false;
                        }
                        setSaveState(navigator.onLine ? 'error' : 'offline',
                            navigator.onLine
                                ? 'Seu rascunho continua neste dispositivo. Tente salvar novamente.'
                                : 'O rascunho será sincronizado quando a conexão voltar.');
                        return false;
                    } finally {
                        saving = false;
                        if (queued) {
                            queued = false;
                            window.setTimeout(saveNow, 0);
                        }
                    }
                };

                const queueSave = () => {
                    persistOutbox();
                    updateProgress();
                    window.clearTimeout(saveTimer);
                    saveTimer = window.setTimeout(saveNow, 700);
                };

                const pendingQuestionNumbers = () => questionFields
                    .filter((field) => {
                        const radio = field.querySelector('input[type="radio"]:checked');
                        const textarea = field.querySelector('textarea');
                        return !radio && (!textarea || textarea.value.trim() === '');
                    })
                    .map((field) => questionFields.indexOf(field) + 1);

                const openConfirmation = () => {
                    const pending = pendingQuestionNumbers();
                    pendingSummary.textContent = pending.length
                        ? `${pending.length} questão(ões) ainda estão sem resposta: ${pending.join(', ')}. Você pode enviar mesmo assim.`
                        : 'Todas as questões têm resposta. Revise sua decisão antes de confirmar.';
                    submitError.classList.add('hidden');
                    if (typeof dialog.showModal === 'function') {
                        dialog.showModal();
                        document.getElementById('cancelSubmit').focus();
                    } else if (window.confirm(`${pendingSummary.textContent}\n\nFinalizar avaliação?`)) {
                        finalizeViaApi();
                    }
                };

                const finalizeViaApi = async () => {
                    if (autoSubmitting) return;
                    autoSubmitting = true;
                    confirmSubmit.disabled = true;
                    confirmSubmit.textContent = 'Enviando…';
                    submitError.classList.add('hidden');
                    persistOutbox();

                    try {
                        const payload = await requestJson(form.dataset.submitUrl, 'POST', {
                            client_token: token,
                            answers: collectAnswers(),
                        });
                        localStorage.removeItem(storageKey);
                        window.location.assign(payload.redirect_url || form.dataset.dashboardUrl);
                    } catch (error) {
                        autoSubmitting = false;
                        confirmSubmit.disabled = false;
                        confirmSubmit.textContent = 'Confirmar envio';
                        submitError.textContent = navigator.onLine
                            ? (error.payload?.message || 'O envio falhou. Suas respostas permanecem salvas; tente novamente.')
                            : 'Sem conexão. Suas respostas continuam neste dispositivo; reconecte e tente novamente.';
                        submitError.classList.remove('hidden');
                        setSaveState(navigator.onLine ? 'error' : 'offline', submitError.textContent);
                    }
                };

                form.addEventListener('input', queueSave);
                form.addEventListener('change', queueSave);
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    openConfirmation();
                });
                document.getElementById('saveNowButton').addEventListener('click', saveNow);
                document.getElementById('cancelSubmit').addEventListener('click', () => dialog.close());
                confirmSubmit.addEventListener('click', finalizeViaApi);
                window.addEventListener('offline', () => {
                    persistOutbox();
                    setSaveState('offline', 'O rascunho está guardado neste dispositivo.');
                });
                window.addEventListener('online', saveNow);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') persistOutbox();
                });

                const restored = restoreOutbox();
                updateProgress();
                if (restored) saveNow();

                @if($submission->deadline_at)
                    const serverNowMs = {{ now()->getTimestamp() * 1000 }};
                    const deadlineMs = {{ $submission->deadline_at->getTimestamp() * 1000 }};
                    const endAt = performance.now() + Math.max(0, deadlineMs - serverNowMs);
                    const timerValue = document.getElementById('timerValue');
                    const timer = document.getElementById('examTimer');
                    const timerAnnouncement = document.getElementById('timerAnnouncement');
                    const announced = new Set();

                    const updateTimer = () => {
                        const remaining = Math.max(0, Math.ceil((endAt - performance.now()) / 1000));
                        const hours = Math.floor(remaining / 3600);
                        const minutes = Math.floor((remaining % 3600) / 60);
                        const seconds = remaining % 60;
                        timerValue.textContent = hours > 0
                            ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                            : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                        timer.setAttribute('aria-label', `Tempo restante: ${timerValue.textContent}`);

                        [[300, 'Restam cinco minutos.'], [60, 'Resta um minuto.']].forEach(([threshold, message]) => {
                            if (remaining <= threshold && remaining > 0 && !announced.has(threshold)) {
                                announced.add(threshold);
                                timerAnnouncement.textContent = message;
                            }
                        });

                        if (remaining === 0) {
                            timerAnnouncement.textContent = 'O tempo terminou. Enviando respostas salvas.';
                            finalizeViaApi();
                            return;
                        }
                        window.setTimeout(updateTimer, remaining > 60 ? 1000 : 250);
                    };
                    updateTimer();
                @endif
            })();
        </script>
    @endpush
</x-app-layout>
