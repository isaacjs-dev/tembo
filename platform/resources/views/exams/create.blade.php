<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb" aria-label="Navegação estrutural">
                    <a href="{{ route('dashboard') }}">Painel</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a href="{{ route('exams.index') }}">Avaliações</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Nova avaliação</span>
                </nav>
                <h1 class="page-title">Planejar nova avaliação</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Defina as regras agora. No próximo passo você adicionará questões, pontuações e turmas antes de
                    publicar.
                </p>
            </div>
            <a href="{{ route('exams.index') }}" class="btn-secondary btn-sm">Cancelar</a>
        </div>
    </x-slot>

    <div class="mx-auto mb-12 max-w-5xl">
        <ol class="mb-6 grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-8" aria-label="Etapas da criação">
            @foreach($wizardSteps as $stepLabel)
                <li @if($loop->first) aria-current="step" @endif
                    class="rounded-xl border-2 p-3 {{ $loop->first ? 'border-primary bg-primary/5' : 'border-duo-border bg-white' }}">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $loop->first ? 'text-primary' : 'text-gray-500' }}">
                        Etapa {{ $loop->iteration }}
                    </span>
                    <span class="mt-1 block text-xs font-extrabold {{ $loop->first ? 'text-duo-heading' : 'text-gray-600' }}">
                        {{ $stepLabel }}
                    </span>
                </li>
            @endforeach
        </ol>

        @if ($errors->any())
            <div role="alert" class="error-list mb-6">
                @foreach ($errors->all() as $error)
                    <div class="error-list-item">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('exams.store') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="status" value="draft">
            <input type="hidden" name="settings_form" value="1">

            <section class="card p-6 md:p-8" aria-labelledby="exam-identity-heading">
                <h2 id="exam-identity-heading" class="mb-6 text-xl font-extrabold text-duo-heading">
                    Identificação e aplicação
                </h2>

                <div class="space-y-6">
                    <div>
                        <label for="title" class="input-label">Título da avaliação</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255"
                            class="input-field" placeholder="Ex.: Avaliação bimestral de Matemática">
                    </div>

                    <div>
                        <label for="instructions" class="input-label">Instruções ao estudante</label>
                        <textarea id="instructions" name="instructions" rows="4" maxlength="10000"
                            class="input-field"
                            placeholder="Explique materiais permitidos, critérios e orientações importantes.">{{ old('instructions') }}</textarea>
                    </div>

                    <div>
                        <label for="application_mode" class="input-label">Modalidade de aplicação</label>
                        <select id="application_mode" name="application_mode" class="input-field">
                            <option value="hybrid" @selected(old('application_mode', 'hybrid') === 'hybrid')>
                                Híbrida — on-line e impressa
                            </option>
                            <option value="online" @selected(old('application_mode') === 'online')>
                                Somente on-line
                            </option>
                            <option value="paper" @selected(old('application_mode') === 'paper')>
                                Somente impressa / cartão-resposta
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="card p-6 md:p-8" aria-labelledby="exam-rules-heading">
                <h2 id="exam-rules-heading" class="mb-6 text-xl font-extrabold text-duo-heading">
                    Prazo e tentativas
                </h2>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="available_from" class="input-label">Disponível a partir de</label>
                        <input type="datetime-local" id="available_from" name="available_from"
                            value="{{ old('available_from') }}" class="input-field">
                        <p class="mt-1 text-xs text-gray-500">Em branco: imediatamente após publicar.</p>
                    </div>
                    <div>
                        <label for="available_until" class="input-label">Prazo final</label>
                        <input type="datetime-local" id="available_until" name="available_until"
                            value="{{ old('available_until') }}" class="input-field">
                        <p class="mt-1 text-xs text-gray-500">Em branco: sem encerramento automático.</p>
                    </div>
                    <div>
                        <label for="time_limit" class="input-label">Tempo por tentativa (minutos)</label>
                        <input type="number" id="time_limit" name="time_limit" value="{{ old('time_limit') }}"
                            min="1" max="1440" class="input-field" placeholder="Sem limite">
                    </div>
                    <div>
                        <label for="attempts" class="input-label">Tentativas permitidas</label>
                        <input type="number" id="attempts" name="attempts" value="{{ old('attempts', 1) }}"
                            min="1" max="20" required class="input-field">
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <fieldset class="card p-6 md:p-8">
                    <legend class="px-1 text-xl font-extrabold text-duo-heading">Apresentação</legend>
                    <div class="mt-4 space-y-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="shuffle_questions" value="1"
                                @checked(old('shuffle_questions'))
                                class="mt-0.5 size-6 rounded border-gray-400 text-primary focus:ring-primary">
                            <span>
                                <span class="block text-sm font-bold text-gray-900">Embaralhar questões</span>
                                <span class="block text-xs text-gray-600">Ordem estável e diferente por tentativa.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="shuffle_options" value="1"
                                @checked(old('shuffle_options'))
                                class="mt-0.5 size-6 rounded border-gray-400 text-primary focus:ring-primary">
                            <span>
                                <span class="block text-sm font-bold text-gray-900">Embaralhar alternativas</span>
                                <span class="block text-xs text-gray-600">Mantém o gabarito original com segurança.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="card p-6 md:p-8">
                    <legend class="px-1 text-xl font-extrabold text-duo-heading">Liberação de resultados</legend>
                    <div class="mt-4 space-y-4">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="show_score" value="1" @checked(old('show_score', true))
                                class="size-6 rounded border-gray-400 text-primary focus:ring-primary">
                            <span class="text-sm font-bold text-gray-900">Exibir nota</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="show_feedback" value="1"
                                @checked(old('show_feedback', true))
                                class="size-6 rounded border-gray-400 text-primary focus:ring-primary">
                            <span class="text-sm font-bold text-gray-900">Exibir comentários e recomendações</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" name="show_answers" value="1" @checked(old('show_answers'))
                                class="size-6 rounded border-gray-400 text-primary focus:ring-primary">
                            <span class="text-sm font-bold text-gray-900">Exibir respostas e gabarito</span>
                        </label>
                        <div>
                            <label for="results_available_from" class="input-label">Liberar a partir de</label>
                            <input type="datetime-local" id="results_available_from" name="results_available_from"
                                value="{{ old('results_available_from') }}" class="input-field">
                            <p class="mt-1 text-xs text-gray-500">Em branco: após a correção.</p>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t-2 border-duo-border pt-6 sm:flex-row sm:justify-end">
                <label class="mr-auto flex items-center gap-3 rounded-xl bg-sky-50 px-4 py-3 text-sm font-bold">
                    <input type="checkbox" name="generate_review" value="1" @checked(old('generate_review'))>
                    Gerar revisão na primeira publicação
                </label>
                <a href="{{ route('exams.index') }}" class="btn-secondary text-center">Cancelar</a>
                <button type="submit" class="btn-primary justify-center text-xs uppercase tracking-wider">
                    Criar rascunho e adicionar questões
                    <span aria-hidden="true" class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
