<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><span class="material-symbols-outlined text-[16px]">chevron_right</span><span class="current">Aparência</span></nav>
                <h1 class="page-title">Layouts e cabeçalhos</h1>
                <p class="mt-1 text-sm text-gray-500">Escolha um modelo pronto ou personalize uma cópia versionada.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('appearance-templates.create', ['kind' => 'assessment_layout']) }}" class="btn-secondary"><span class="material-symbols-outlined">dashboard_customize</span>Novo layout</a>
                <a href="{{ route('appearance-templates.create', ['kind' => 'assessment_header']) }}" class="btn-primary"><span class="material-symbols-outlined">view_headline</span>Novo cabeçalho</a>
            </div>
        </div>
    </x-slot>

    @foreach(['assessment_layout' => ['Layouts da Avaliação', 'description'], 'assessment_header' => ['Cabeçalhos da Avaliação', 'view_headline']] as $kind => [$label, $icon])
        <section class="mb-10" aria-labelledby="heading-{{ $kind }}">
            <div class="mb-4 flex items-center justify-between">
                <h2 id="heading-{{ $kind }}" class="text-xl font-extrabold text-duo-heading">{{ $label }}</h2>
                <span class="badge badge-neutral">{{ $catalog[$kind]->count() }} opções</span>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($catalog[$kind] as $template)
                    @php
                        $userDefault = $defaults->has('user:'.auth()->id().':'.$kind) && (int) $defaults['user:'.auth()->id().':'.$kind]->template_id === (int) $template->id;
                        $orgDefault = $defaults->has('organization:'.$organization->id.':'.$kind) && (int) $defaults['organization:'.$organization->id.':'.$kind]->template_id === (int) $template->id;
                        $canManageOrganization = auth()->user()->hasWorkspaceRole('admin', 'institution_admin', 'global_admin');
                        $canEdit = app(\App\Services\AppearanceTemplateService::class)->canMutate($template, auth()->user());
                    @endphp
                    <article class="card p-5" data-template-kind="{{ $kind }}">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-extrabold text-duo-heading">{{ $template->name }}</h3>
                                <p class="text-xs font-semibold text-gray-400">v{{ $template->current_version }} · {{ $template->is_system ? 'Sistema' : ($template->visibility_scope === 'org_public' ? 'Institucional' : 'Pessoal') }}</p>
                            </div>
                            @if($userDefault || $orgDefault)<span class="badge badge-success">Padrão</span>@endif
                        </div>
                        <div class="mb-4 min-h-24 rounded-xl border border-duo-border bg-gray-50 p-3 text-xs text-gray-500">
                            @if($kind === 'assessment_layout')
                                <div class="mx-auto h-20 w-14 rounded border bg-white shadow-sm"></div>
                                <p class="mt-2 text-center">{{ data_get($template->currentVersion->definition, 'questions.columns', 1) }} coluna(s) · {{ implode(' / ', data_get($template->currentVersion->definition, 'page.margins_mm', [])) }} mm</p>
                            @else
                                @php
                                    $previewDefinition = $template->currentVersion->definition;
                                    $previewHeight = max(1, (int) data_get($previewDefinition, 'canvas.height_units', 360));
                                @endphp
                                <div class="relative h-24 overflow-hidden rounded border bg-white p-2">
                                    @foreach(array_slice(data_get($previewDefinition, 'elements', []), 0, 8) as $element)
                                        @if(($previewDefinition['mode'] ?? null) === 'canvas')
                                            @php $miniStyle = sprintf('left:%.1f%%;top:%.1f%%;width:%.1f%%;height:%.1f%%', ($element['x'] ?? 0) / 10, ($element['y'] ?? 0) / $previewHeight * 100, ($element['width'] ?? 100) / 10, ($element['height'] ?? 20) / $previewHeight * 100); @endphp
                                            @if(($element['type'] ?? null) === 'image' && isset(($template->currentVersion->assets ?? [])[$element['asset_key'] ?? '']))
                                                <img class="absolute object-contain" style="{{ $miniStyle }}" alt="" src="{{ route('appearance-templates.asset', [$template, $template->currentVersion, $element['asset_key']]) }}">
                                            @elseif(($element['type'] ?? null) === 'line')
                                                <span class="absolute border-t border-gray-400" style="{{ $miniStyle }}"></span>
                                            @elseif(($element['type'] ?? null) === 'rectangle')
                                                <span class="absolute border border-gray-300" style="{{ $miniStyle }}"></span>
                                            @else
                                                <span class="absolute truncate rounded bg-primary/20 px-1 text-[7px] text-gray-600" style="{{ $miniStyle }}">{{ $element['token'] ?? $element['text'] ?? 'Campo' }}</span>
                                            @endif
                                        @elseif(($element['type'] ?? null) === 'line')
                                            <div class="my-1 h-px bg-gray-300"></div>
                                        @else
                                            <div class="mb-1 truncate rounded bg-primary/15 px-1 text-[7px]">{{ $element['token'] ?? $element['text'] ?? 'Campo' }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if(!$canEdit)
                                <form method="POST" action="{{ route('appearance-templates.duplicate', $template) }}">@csrf<button class="btn-primary btn-sm" type="submit"><span class="material-symbols-outlined text-[18px]">content_copy</span>Personalizar cópia</button></form>
                            @else
                                <a href="{{ route('appearance-templates.edit', $template) }}" class="btn-primary btn-sm"><span class="material-symbols-outlined text-[18px]">edit</span>Editar</a>
                                <form method="POST" action="{{ route('appearance-templates.duplicate', $template) }}">@csrf<button class="btn-secondary btn-sm" type="submit"><span class="material-symbols-outlined text-[18px]">content_copy</span>Duplicar</button></form>
                            @endif
                            <form method="POST" action="{{ route('appearance-templates.default', $template) }}">@csrf<input type="hidden" name="scope" value="user"><button class="btn-secondary btn-sm" type="submit">Meu padrão</button></form>
                            @if($canManageOrganization && ($template->is_system || $template->visibility_scope === 'org_public'))
                                <form method="POST" action="{{ route('appearance-templates.default', $template) }}">@csrf<input type="hidden" name="scope" value="organization"><button class="btn-secondary btn-sm" type="submit">Padrão institucional</button></form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</x-app-layout>
