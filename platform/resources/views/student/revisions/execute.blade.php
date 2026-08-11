<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-bold text-primary-dark">Tentativa {{ $attempt->attempt_number }}</p><h1 class="text-2xl font-black text-duo-heading">{{ $revision->title }}</h1></div></x-slot>
    <div class="mx-auto max-w-3xl space-y-5" x-data="{open:{{ $attempt->current_position }}}">
        <div class="sticky top-0 z-10 rounded-xl bg-white p-3 shadow"><div class="h-3 overflow-hidden rounded-full bg-gray-200"><div class="h-full bg-primary transition-all" style="width:{{ $revision->activeItems->count()?min(100,($attempt->responses->count()/$revision->activeItems->count())*100):0 }}%"></div></div><p class="mt-1 text-center text-xs font-bold text-gray-600">{{ $attempt->responses->count() }} de {{ $revision->activeItems->count() }} etapas registradas</p></div>
        @foreach($revision->activeItems as $item)
            @php $saved = $attempt->responses->firstWhere('snapshot_item_key', $item->id); @endphp
            <section class="rounded-2xl border-2 bg-white p-5 {{ $saved?'border-green-300':'border-duo-border' }}" id="item-{{ $item->id }}"><div class="flex items-center justify-between gap-3"><span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold">Etapa {{ $loop->iteration }}</span><span class="text-xs font-bold text-gray-600">{{ str_replace('_',' ',ucfirst($item->type)) }}</span></div><h2 class="mt-4 text-lg font-extrabold">{{ $item->prompt }}</h2>
                @if(!empty($item->content['resources']))
                    <aside class="mt-4 space-y-3 rounded-xl border-2 border-blue-200 bg-blue-50 p-4" aria-label="Materiais de apoio">
                        @foreach($item->content['resources'] as $resource)
                            @php
                                $resourceVersionId = (int) ($resource['resource_version_id'] ?? 0);
                                $resourceMime = (string) data_get($resource, 'file.mime_type', '');
                                $resourceHasFile = filled(data_get($resource, 'file.storage_path'));
                                $resourceUrl = $resourceHasFile
                                    ? route('student.revisions.resource', [$revision, $attempt, $item, $resourceVersionId])
                                    : null;
                            @endphp
                            <section class="space-y-2">
                                <h3 class="font-extrabold text-blue-950">{{ $resource['title'] }}</h3>
                                @if(filled(data_get($resource, 'content.body')))<p class="whitespace-pre-wrap text-gray-900">{{ data_get($resource, 'content.body') }}</p>@endif
                                @if($resourceUrl && str_starts_with($resourceMime, 'image/'))
                                    <a href="{{ $resourceUrl }}" target="_blank" rel="noopener"><img class="max-h-96 max-w-full rounded-lg" src="{{ $resourceUrl }}" alt="{{ data_get($resource, 'content.alt_text', $resource['title']) }}"></a>
                                @elseif($resourceUrl)
                                    <a class="inline-flex min-h-11 items-center font-extrabold text-blue-800 underline" href="{{ $resourceUrl }}" target="_blank" rel="noopener">Baixar arquivo de apoio</a>
                                @endif
                                @if(filled(data_get($resource, 'content.external_url')))
                                    <a class="block break-all text-sm font-bold text-blue-800 underline" href="{{ data_get($resource, 'content.external_url') }}" target="_blank" rel="nofollow noopener">Abrir fonte externa</a>
                                @endif
                            </section>
                        @endforeach
                    </aside>
                @endif
                @if(in_array($item->type,['explanation','example']))<div class="prose mt-4 max-w-none whitespace-pre-wrap">{{ $item->content['body']??'' }}</div>
                @elseif($item->type==='flashcard')<div x-data="{flip:false}" class="mt-4"><button type="button" @click="flip=!flip" class="min-h-40 w-full rounded-2xl bg-gradient-to-br from-sky-100 to-violet-100 p-6 text-xl font-bold"><span x-show="!flip">{{ $item->content['front']??$item->prompt }}</span><span x-show="flip" x-cloak>{{ $item->content['back']??'' }}</span></button><form class="mt-3" method="POST" action="{{ route('student.revisions.answer',[$revision,$attempt,$item]) }}">@csrf<input type="hidden" name="answer" value="reviewed"><button class="duo-button-primary w-full rounded-xl p-3 font-bold">Marcar como revisado</button></form></div>
                @else<form class="mt-4 space-y-3" method="POST" action="{{ route('student.revisions.answer',[$revision,$attempt,$item]) }}">@csrf
                    @if($item->type==='multiple_choice')@foreach($item->content['options']??[] as $key=>$option)<label class="flex min-h-12 items-center gap-3 rounded-xl border-2 p-3"><input type="radio" name="answer" value="{{ $key }}" @checked(data_get($saved?->answer,'value')==$key) required><span>{{ $option }}</span></label>@endforeach
                    @elseif($item->type==='true_false')@foreach(['0'=>'Verdadeiro','1'=>'Falso'] as $key=>$option)<label class="flex min-h-12 items-center gap-3 rounded-xl border-2 p-3"><input type="radio" name="answer" value="{{ $key }}" @checked(data_get($saved?->answer,'value')==$key) required><span>{{ $option }}</span></label>@endforeach
                    @elseif($item->type==='matching')@foreach($item->content['left']??[] as $key=>$left)<label class="grid gap-2 rounded-xl bg-gray-50 p-3 sm:grid-cols-2"><strong>{{ $left }}</strong><select class="input-field" name="answer[{{ $key }}]" required><option value="">Associe...</option>@foreach($item->content['right']??[] as $rightKey=>$right)<option value="{{ $rightKey }}" @selected(data_get($saved?->answer,$key)==$rightKey)>{{ $right }}</option>@endforeach</select></label>@endforeach
                    @elseif($item->type==='ordering')@foreach($item->content['items']??[] as $position=>$unused)<label class="grid grid-cols-[auto_1fr] items-center gap-3"><strong>{{ $position+1 }}º</strong><select class="input-field" name="answer[]" required><option value="">Escolha...</option>@foreach($item->content['items']??[] as $key=>$label)<option value="{{ $key }}" @selected(data_get($saved?->answer,"value.$position")==$key)>{{ $label }}</option>@endforeach</select></label>@endforeach
                    @else<input class="input-field w-full" name="answer" value="{{ data_get($saved?->answer,'value') }}" autocomplete="off" required placeholder="Digite sua resposta">
                    @endif
                    @if($item->hints)<details class="rounded-xl bg-amber-50 p-3"><summary class="cursor-pointer font-bold">Ver dicas</summary><ul class="mt-2 list-disc pl-5">@foreach($item->hints as $hint)<li>{{ $hint }}</li>@endforeach</ul></details>@endif
                    <button class="duo-button-primary w-full rounded-xl p-3 font-extrabold">{{ $saved?'Atualizar resposta':'Salvar resposta' }}</button>@if($saved&&$revision->feedback_mode==='immediate')<p class="rounded-xl p-3 font-bold {{ $saved->is_correct?'bg-green-100 text-green-900':'bg-amber-100 text-amber-950' }}">{{ $saved->feedback }}</p>@endif
                </form>@endif
            </section>
        @endforeach
        <form method="POST" action="{{ route('student.revisions.complete',[$revision,$attempt]) }}" class="pb-10">@csrf<button class="duo-button-primary w-full rounded-xl px-6 py-4 text-lg font-black">Concluir revisão</button></form>
    </div>
</x-app-layout>
