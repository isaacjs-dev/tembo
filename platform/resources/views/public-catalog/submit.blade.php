@php
    $title = $targetType === 'question' ? data_get($target->content, 'statement', 'Questão sem enunciado') : $target->title;
    $rightsLabels = [
        'own_work' => 'Sou autor(a) integral do conteúdo',
        'public_domain' => 'O conteúdo está em domínio público',
        'licensed' => 'Possuo licença compatível',
        'authorized' => 'Possuo autorização do titular',
    ];
@endphp
<x-app-layout>
    <x-slot name="header"><div class="page-header"><div><nav class="breadcrumb"><a href="{{ route('public-catalog.index') }}">Catálogo público</a><span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span><span class="current">Nova submissão</span></nav><h1 class="page-title">Enviar para moderação</h1></div></div></x-slot>
    <div class="mx-auto max-w-3xl">
        <div class="card mb-5 p-5"><p class="text-xs font-extrabold uppercase tracking-wide text-primary">{{ $targetType === 'question' ? 'Questão' : 'Recurso de questão' }}</p><h2 class="mt-2 text-lg font-extrabold text-duo-heading">{{ $title }}</h2><p class="mt-2 text-sm text-gray-600">Será criado um snapshot imutável. Você poderá continuar editando o original; somente o snapshot aprovado será publicado.</p></div>
        <form method="POST" action="{{ route('public-catalog.submissions.store') }}" class="card space-y-5 p-6">
            @csrf
            <input type="hidden" name="type" value="{{ $targetType }}"><input type="hidden" name="target_id" value="{{ $target->id }}"><input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div><label for="rights_basis" class="input-label">Base dos direitos de uso</label><select id="rights_basis" name="rights_basis" class="input-field" required><option value="">Selecione</option>@foreach($rightsBases as $basis)<option value="{{ $basis }}" @selected(old('rights_basis') === $basis)>{{ $rightsLabels[$basis] }}</option>@endforeach</select><x-input-error :messages="$errors->get('rights_basis')" class="mt-2" /></div>
            <div><label for="rights_notes" class="input-label">Explicação ou referência da licença</label><textarea id="rights_notes" name="rights_notes" rows="4" class="input-field" maxlength="5000" placeholder="Obrigatório para conteúdo licenciado ou autorizado. Não inclua dados pessoais desnecessários.">{{ old('rights_notes') }}</textarea><x-input-error :messages="$errors->get('rights_notes')" class="mt-2" /></div>
            <div><label for="attribution" class="input-label">Atribuição pública, se aplicável</label><input id="attribution" name="attribution" value="{{ old('attribution') }}" class="input-field" maxlength="500"><x-input-error :messages="$errors->get('attribution')" class="mt-2" /></div>
            <div><label for="evidence_url" class="input-label">URL de evidência, se aplicável</label><input id="evidence_url" type="url" name="evidence_url" value="{{ old('evidence_url') }}" class="input-field" placeholder="https://"><x-input-error :messages="$errors->get('evidence_url')" class="mt-2" /></div>
            <label class="flex items-start gap-3 rounded-xl border-2 border-duo-border p-4"><input type="checkbox" name="rights_confirmed" value="1" class="mt-1 rounded" required @checked(old('rights_confirmed'))><span class="text-sm text-gray-700">Confirmo que tenho os direitos necessários e que as informações fornecidas são verdadeiras.</span></label>
            <label class="flex items-start gap-3 rounded-xl border-2 border-duo-border p-4"><input type="checkbox" name="terms_accepted" value="1" class="mt-1 rounded" required @checked(old('terms_accepted'))><span class="text-sm text-gray-700">Aceito os termos de contribuição do catálogo, versão <strong>{{ $termsVersion }}</strong>. A aprovação não gera créditos automaticamente.</span></label>
            <x-input-error :messages="$errors->get('target')" class="mt-2" />
            <div class="flex flex-wrap justify-end gap-2"><a href="{{ $targetType === 'question' ? route('questions.index') : route('question-resources.index') }}" class="btn-secondary">Cancelar</a><button class="btn-primary" type="submit">Enviar para análise</button></div>
        </form>
    </div>
</x-app-layout>
