@php
    $selectedClasses = old('class_ids', isset($material) ? $material->schoolClasses->pluck('id')->all() : []);
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
    <div class="space-y-5 rounded-2xl border-2 border-duo-border bg-white p-5 md:p-7">
        <div>
            <label for="title" class="mb-1 block font-extrabold text-gray-900">Título <span aria-hidden="true">*</span></label>
            <input id="title" name="title" required maxlength="180"
                value="{{ old('title', $material->title ?? '') }}"
                class="w-full rounded-xl border-2 border-gray-300 @error('title') border-red-500 @enderror">
            @error('title') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="mb-1 block font-extrabold text-gray-900">Descrição breve</label>
            <textarea id="description" name="description" rows="3" maxlength="2000"
                class="w-full rounded-xl border-2 border-gray-300">{{ old('description', $material->description ?? '') }}</textarea>
            @error('description') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="body" class="mb-1 block font-extrabold text-gray-900">Conteúdo de revisão</label>
            <p id="body-help" class="mb-2 text-sm text-gray-600">Use texto simples e estruturado. O conteúdo é exibido exatamente como digitado.</p>
            <textarea id="body" name="body" rows="14" maxlength="100000" aria-describedby="body-help"
                class="w-full rounded-xl border-2 border-gray-300 @error('body') border-red-500 @enderror">{{ old('body', $material->body ?? '') }}</textarea>
            @error('body') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="external_url" class="mb-1 block font-extrabold text-gray-900">Link complementar</label>
            <input id="external_url" name="external_url" type="url" maxlength="2048"
                placeholder="https://exemplo.org/recurso"
                value="{{ old('external_url', $material->external_url ?? '') }}"
                class="w-full rounded-xl border-2 border-gray-300">
            <p class="mt-1 text-sm text-gray-600">Somente endereços HTTP ou HTTPS. Informe um conteúdo ou um link.</p>
            @error('external_url') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <aside class="space-y-5">
        <section class="rounded-2xl border-2 border-duo-border bg-white p-5" aria-labelledby="publication-title">
            <h2 id="publication-title" class="text-lg font-extrabold text-gray-900">Publicação</h2>
            <div class="mt-4">
                <label for="status" class="mb-1 block text-sm font-bold text-gray-800">Situação</label>
                <select id="status" name="status" required class="input-field">
                    <option value="draft" @selected(old('status', $material->status ?? 'draft') === 'draft')>Rascunho</option>
                    <option value="published" @selected(old('status', $material->status ?? '') === 'published')>Publicado</option>
                </select>
            </div>

            <fieldset class="mt-5">
                <legend class="font-extrabold text-gray-900">Turmas</legend>
                <p class="mt-1 text-sm text-gray-600">Materiais publicados exigem ao menos uma turma.</p>
                <div class="mt-3 max-h-52 space-y-2 overflow-y-auto rounded-xl border-2 border-gray-200 p-3">
                    @forelse($classes as $class)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-gray-50">
                            <input type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                                @checked(in_array($class->id, $selectedClasses))
                                class="mt-0.5 rounded border-2 border-gray-400 text-primary focus:ring-primary">
                            <span>
                                <span class="block font-bold text-gray-900">{{ $class->name }}</span>
                                @if($class->year)<span class="text-xs text-gray-600">{{ $class->year }}</span>@endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-600">Você ainda não está associado a nenhuma turma.</p>
                    @endforelse
                </div>
                @error('class_ids') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
                @error('class_ids.*') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            </fieldset>
        </section>

        <section class="rounded-2xl border-2 border-duo-border bg-white p-5" aria-labelledby="taxonomy-title">
            <h2 id="taxonomy-title" class="text-lg font-extrabold text-gray-900">Assunto da revisão</h2>
            <p class="mt-1 text-sm text-gray-600">Esses vínculos tornam as recomendações explicáveis.</p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="discipline_id" class="mb-1 block text-sm font-bold text-gray-800">Disciplina</label>
                    <select id="discipline_id" name="discipline_id" class="input-field">
                        <option value="">Conteúdo geral</option>
                        @foreach($disciplines as $discipline)
                            <option value="{{ $discipline->id }}" @selected((string) old('discipline_id', $material->discipline_id ?? '') === (string) $discipline->id)>
                                {{ $discipline->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="custom_skill_id" class="mb-1 block text-sm font-bold text-gray-800">Habilidade personalizada</label>
                    <select id="custom_skill_id" name="custom_skill_id" class="input-field">
                        <option value="">Nenhuma</option>
                        @foreach($customSkills as $skill)
                            <option value="{{ $skill->id }}" @selected((string) old('custom_skill_id', $material->custom_skill_id ?? '') === (string) $skill->id)>
                                {{ $skill->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bncc_node_id" class="mb-1 block text-sm font-bold text-gray-800">Habilidade BNCC</label>
                    <select id="bncc_node_id" name="bncc_node_id" class="input-field">
                        <option value="">Nenhuma</option>
                        @foreach($bnccNodes as $node)
                            <option value="{{ $node->id }}" @selected((string) old('bncc_node_id', $material->bncc_node_id ?? '') === (string) $node->id)>
                                {{ $node->code ? $node->code . ' — ' : '' }}{{ $node->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>
    </aside>
</div>

@if(!$material->exists)
    <label class="mt-6 flex items-start gap-3 rounded-xl border-2 border-sky-200 bg-sky-50 p-4">
        <input type="checkbox" name="generate_review" value="1" @checked(old('generate_review'))>
        <span><strong>Gerar rascunho de revisão deste material</strong><br><span class="text-sm text-gray-600">A revisão será aberta no editor antes da publicação.</span></span>
    </label>
@endif
<div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
    <a href="{{ route('learning-materials.index') }}"
        class="duo-button-secondary rounded-xl px-6 py-3 text-center font-extrabold">Cancelar</a>
    <button class="duo-button-primary rounded-xl px-7 py-3 font-extrabold">{{ $submitLabel }}</button>
</div>
