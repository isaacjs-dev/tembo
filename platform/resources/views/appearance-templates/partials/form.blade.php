@php
    $assetUrls = [];
    if ($template?->currentVersion) {
        foreach (array_keys($template->currentVersion->assets ?? []) as $assetKey) {
            $assetUrls[$assetKey] = route('appearance-templates.asset', [$template, $template->currentVersion, $assetKey]);
        }
    }
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
    x-data="appearanceEditor(@js($definition), @js($kind), @js($assetUrls))" x-init="init()" class="space-y-6">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <input type="hidden" name="kind" value="{{ $kind }}">
    <input type="hidden" name="definition" :value="definitionString">
    @if($template)<input type="hidden" name="base_version" value="{{ $template->current_version }}">@endif

    <section class="card p-5">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2"><label class="input-label" for="appearance-name">Nome</label><input id="appearance-name" class="input-field" name="name" maxlength="160" required value="{{ old('name', $template?->name) }}"></div>
            @if(!$template)
                <div><label class="input-label" for="appearance-owner">Proprietário</label><select id="appearance-owner" name="ownership" class="input-field"><option value="personal">Pessoal</option>@if(auth()->user()->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'))<option value="organization">Instituição</option>@endif</select></div>
            @else
                <div><label class="input-label" for="appearance-summary">Resumo da versão</label><input id="appearance-summary" class="input-field" name="summary" maxlength="500" placeholder="Ex.: logo e campos atualizados"></div>
            @endif
        </div>
    </section>

    @if($kind === 'assessment_layout')
        <section class="card p-6">
            <h2 class="mb-5 text-xl font-extrabold">Configuração do layout</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><label class="input-label">Colunas</label><select class="input-field" x-model.number="definition.questions.columns" @change="sync(false)"><option :value="1">Uma</option><option :value="2">Duas</option></select></div>
                <div><label class="input-label">Separador</label><select class="input-field" x-model="definition.questions.separator" @change="sync(false)"><option value="line">Linha</option><option value="box">Caixa</option><option value="none">Sem separador</option></select></div>
                <div><label class="input-label">Margem superior (mm)</label><input class="input-field" type="number" min="5" max="30" step="1" x-model.number="definition.page.margins_mm[0]" @input="sync(false)"></div>
                <div><label class="input-label">Margem lateral (mm)</label><input class="input-field" type="number" min="5" max="30" step="1" x-model.number="definition.page.margins_mm[1]" @input="definition.page.margins_mm[3]=definition.page.margins_mm[1];sync(false)"></div>
                <div><label class="input-label">Margem inferior (mm)</label><input class="input-field" type="number" min="5" max="30" step="1" x-model.number="definition.page.margins_mm[2]" @input="sync(false)"></div>
                <label class="flex items-center gap-3 font-bold"><input type="checkbox" class="rounded" x-model="definition.questions.avoid_break_inside" @change="sync(false)">Evitar quebra interna</label>
            </div>
            <div class="mt-6 rounded-xl bg-gray-100 p-5"><div class="mx-auto aspect-[210/297] max-h-80 rounded border bg-white p-4 shadow" :style="`padding:${definition.page.margins_mm[0]}px ${definition.page.margins_mm[1]}px`"><div class="h-full border border-dashed border-primary/30" :class="definition.questions.columns === 2 ? 'grid grid-cols-2 gap-2' : ''"><div class="space-y-2 p-2"><template x-for="i in 5"><div class="h-3 rounded bg-gray-200"></div></template></div><div x-show="definition.questions.columns === 2" class="space-y-2 border-l p-2"><template x-for="i in 5"><div class="h-3 rounded bg-gray-200"></div></template></div></div></div></div>
        </section>
    @else
        <section class="card overflow-hidden">
            <div class="border-b p-4">
                <div class="flex flex-wrap items-center gap-2" role="toolbar" aria-label="Ferramentas do editor">
                    <button type="button" class="btn-secondary btn-sm" @click="add('text')">Texto</button>
                    <button type="button" class="btn-secondary btn-sm" @click="add('field')">Campo</button>
                    @if($template)<button type="button" class="btn-secondary btn-sm" @click="add('image')">Imagem</button>@endif
                    <button type="button" class="btn-secondary btn-sm" @click="add('line')">Linha</button>
                    <button type="button" class="btn-secondary btn-sm" @click="add('rectangle')">Retângulo</button>
                    <span class="mx-1 h-7 border-l"></span>
                    <button type="button" class="btn-secondary btn-sm" @click="duplicate()" :disabled="!selectedId">Duplicar</button>
                    <button type="button" class="btn-secondary btn-sm" @click="remove()" :disabled="!selectedId">Apagar</button>
                    <button type="button" class="btn-secondary btn-sm" @click="undo()" :disabled="!history.length" aria-label="Desfazer">↶</button>
                    <button type="button" class="btn-secondary btn-sm" @click="redo()" :disabled="!future.length" aria-label="Refazer">↷</button>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="min-w-0 bg-slate-100 p-4 sm:p-8">
                    <p class="mb-3 text-sm font-semibold text-gray-500">Arraste e redimensione. A estrutura salva usa coordenadas normalizadas, não o JSON interno do canvas.</p>
                    <div x-ref="canvas" class="mx-auto w-full min-w-0 max-w-4xl bg-white shadow-lg" aria-label="Canvas visual do cabeçalho"></div>
                </div>
                <aside class="border-l bg-white p-5" aria-label="Propriedades do elemento">
                    <label class="input-label">Altura impressa (mm)</label><input class="input-field mb-5" type="number" min="18" max="80" step="1" x-model.number="definition.height_mm" @input="sync(false)">
                    @if($template)
                        <label class="input-label" for="appearance-asset">Enviar logo/imagem (PNG/JPEG, 2 MB)</label><input id="appearance-asset" class="mb-4 block w-full text-sm" type="file" name="asset" accept="image/png,image/jpeg" @change="previewAsset($event)"><input type="hidden" name="asset_key" value="logo">
                    @endif
                    <h3 class="mb-2 font-extrabold">Elementos</h3>
                    <div class="mb-5 max-h-48 space-y-1 overflow-auto" role="listbox" aria-label="Elementos do cabeçalho">
                        <template x-for="item in definition.elements" :key="item.id"><button type="button" role="option" :aria-selected="(selectedId === item.id).toString()" class="w-full rounded-lg px-3 py-2 text-left text-sm" :class="selectedId === item.id ? 'bg-primary/10 font-bold text-primary' : 'hover:bg-gray-50'" @click="selectedId=item.id;render()"><span x-text="`${item.type} · ${item.token || item.text || item.asset_key || item.id}`"></span></button></template>
                    </div>
                    <template x-if="element()"><div class="space-y-3">
                        <div class="grid grid-cols-2 gap-2"><template x-for="field in ['x','y','width','height']"><label class="text-xs font-bold uppercase text-gray-500"><span x-text="field"></span><input class="input-field mt-1" type="number" step="1" :value="element()[field]" @change="updateElement(field,$event.target.value)"></label></template></div>
                        <div class="flex flex-wrap gap-1"><template x-for="side in ['left','center','right','top','middle','bottom']"><button type="button" class="rounded border px-2 py-1 text-xs font-bold" @click="align(side)" x-text="side"></button></template></div>
                        <div class="flex gap-2"><button type="button" class="rounded border px-2 py-1 text-xs font-bold" @click="distribute('horizontal')">Distribuir ↔</button><button type="button" class="rounded border px-2 py-1 text-xs font-bold" @click="distribute('vertical')">Distribuir ↕</button></div>
                        <div class="flex gap-2"><button type="button" class="rounded border px-2 py-1 text-xs font-bold" @click="moveLayer('back')">Enviar para trás</button><button type="button" class="rounded border px-2 py-1 text-xs font-bold" @click="moveLayer('front')">Trazer para frente</button></div>
                        <template x-if="['text','field'].includes(element().type)"><div class="space-y-3">
                            <template x-if="element().type === 'text'"><label class="block"><span class="input-label">Texto livre</span><input class="input-field" maxlength="500" :value="element().text || ''" @change="snapshot();element().text=$event.target.value;delete element().token;sync()"></label></template>
                            <label class="input-label">Token</label><select class="input-field" :value="element().token" @change="snapshot();element().token=$event.target.value;delete element().text;sync()">@foreach(\App\Services\AppearanceTokenResolver::TOKENS as $token)<option value="{{ $token }}">{{ $token }}</option>@endforeach</select>
                            <label class="input-label">Alinhamento</label><select class="input-field" :value="element().align" @change="updateElement('align',$event.target.value)"><option value="left">Esquerda</option><option value="center">Centro</option><option value="right">Direita</option></select>
                            <label class="input-label">Tamanho</label><input class="input-field" type="number" min="7" max="32" :value="element().font_size" @change="updateElement('font_size',$event.target.value)">
                        </div></template>
                    </div></template>
                    <p x-show="!selectedId" class="text-sm text-gray-400">Selecione um elemento para editar suas propriedades também pelo teclado.</p>
                </aside>
            </div>
        </section>
    @endif

    @error('definition')<p class="text-sm font-bold text-red-600">{{ $message }}</p>@enderror
    @error('asset')<p class="text-sm font-bold text-red-600">{{ $message }}</p>@enderror
    <div class="flex justify-end gap-3"><a href="{{ route('appearance-templates.index') }}" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit"><span class="material-symbols-outlined">save</span>{{ $template ? 'Salvar nova versão' : 'Criar template' }}</button></div>
</form>
