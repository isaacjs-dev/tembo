@php
    $currentVersion = $resource->currentVersion;
    $content = $currentVersion?->content ?? [];
    $selectedTeachers = collect(old(
        'teacher_ids',
        $resource->exists ? $resource->shares->pluck('shared_with_user_id')->all() : [],
    ))->map(fn ($id) => (int) $id)->all();
    $typeLabels = [
        'text' => 'Texto', 'image' => 'Imagem', 'chart' => 'Gráfico', 'table' => 'Tabela',
        'formula' => 'Fórmula', 'diagram' => 'Diagrama', 'document' => 'Documento',
    ];
@endphp

<div class="space-y-6" x-data="{ visibility: @js(old('visibility_scope', $resource->visibility_scope ?: 'private')) }">
    <section class="card space-y-5 p-5 md:p-7" aria-labelledby="resource-identification-heading">
        <div>
            <h2 id="resource-identification-heading" class="text-xl font-extrabold text-duo-heading">Identificação e acesso</h2>
            <p class="mt-1 text-sm text-gray-600">Um recurso pode ser reutilizado por várias questões sem duplicar seu conteúdo.</p>
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="title" class="input-label">Título</label>
                <input id="title" name="title" type="text" maxlength="180" required value="{{ old('title', $resource->title) }}" class="input-field">
                @error('title') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="type" class="input-label">Tipo</label>
                @if($resource->exists)
                    <input class="input-field bg-gray-100" value="{{ $typeLabels[$resource->type] ?? ucfirst($resource->type) }}" disabled>
                    <p class="mt-1 text-xs text-gray-600">O tipo permanece fixo entre versões.</p>
                @else
                    <select id="type" name="type" class="input-field" required>
                        @foreach($typeLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'text') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @error('type') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="visibility_scope" class="input-label">Visibilidade</label>
            <select id="visibility_scope" name="visibility_scope" x-model="visibility" class="input-field" required>
                <option value="private">Privado — somente o autor</option>
                <option value="shared_specific">Professores específicos</option>
                <option value="organization">Institucional — professores autorizados</option>
            </select>
            @error('visibility_scope') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>
        <fieldset x-show="visibility === 'shared_specific'" x-cloak class="rounded-xl border-2 border-duo-border p-4">
            <legend class="px-2 font-extrabold text-gray-800">Professores com acesso</legend>
            @if($teachers->isEmpty())
                <p class="text-sm text-gray-600">Não há outro professor ativo neste workspace.</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($teachers as $teacher)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-gray-200 p-3">
                            <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" @checked(in_array($teacher->id, $selectedTeachers, true)) class="size-5 rounded border-gray-400 text-primary focus:ring-primary">
                            <span class="font-bold text-gray-800">{{ $teacher->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('teacher_ids') <p class="mt-2 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            @error('teacher_ids.*') <p class="mt-2 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </fieldset>
    </section>

    <section class="card space-y-5 p-5 md:p-7" aria-labelledby="resource-content-heading">
        <div>
            <h2 id="resource-content-heading" class="text-xl font-extrabold text-duo-heading">Conteúdo da versão</h2>
            <p class="mt-1 text-sm text-gray-600">Ao alterar o conteúdo, uma nova versão imutável será criada. Questões existentes continuam ligadas à versão anterior.</p>
        </div>
        <div>
            <label for="body" class="input-label">Texto ou descrição estruturada</label>
            <textarea id="body" name="body" rows="8" maxlength="100000" class="input-field" placeholder="Informe o texto-base, tabela em formato textual, fórmula ou descrição do material.">{{ old('body', $content['body'] ?? '') }}</textarea>
            @error('body') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="external_url" class="input-label">URL externa (opcional)</label>
                <input id="external_url" name="external_url" type="url" maxlength="2048" value="{{ old('external_url', $content['external_url'] ?? '') }}" class="input-field" placeholder="https://">
                @error('external_url') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="alt_text" class="input-label">Texto alternativo</label>
                <input id="alt_text" name="alt_text" type="text" maxlength="1000" value="{{ old('alt_text', $content['alt_text'] ?? '') }}" class="input-field" placeholder="Descreva imagens, gráficos e diagramas.">
                @error('alt_text') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="file" class="input-label">Arquivo privado (imagem ou PDF, até 10 MB)</label>
            <input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full rounded-xl border-2 border-duo-border bg-white p-3 text-sm font-bold text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-extrabold file:text-white">
            @if($currentVersion?->storage_path)
                <p class="mt-2 text-sm text-gray-700">Arquivo atual: <a class="font-extrabold text-primary underline" href="{{ route('question-resources.versions.download', [$resource, $currentVersion]) }}">baixar versão {{ $currentVersion->version_number }}</a>. Envie outro arquivo somente para substituí-lo.</p>
            @endif
            @error('file') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="metadata_json" class="input-label">Metadados JSON (opcional)</label>
            <textarea id="metadata_json" name="metadata_json" rows="4" maxlength="50000" class="input-field font-mono text-sm" placeholder='{"fonte":"...","licenca":"..."}'>{{ old('metadata_json', !empty($content['metadata']) ? json_encode($content['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            @error('metadata_json') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
        </div>
    </section>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ route('question-resources.index') }}" class="btn-secondary justify-center">Cancelar</a>
        <button type="submit" class="btn-primary justify-center"><span aria-hidden="true" class="material-symbols-outlined">save</span>{{ $resource->exists ? 'Salvar nova versão' : 'Criar recurso' }}</button>
    </div>
</div>
