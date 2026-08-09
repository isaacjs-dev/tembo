@php
    $title = $targetType === 'question' ? data_get($target->content, 'statement', 'Questão sem enunciado') : $target->title;
    $reasonLabels = ['copyright'=>'Direitos autorais','incorrect'=>'Conteúdo incorreto','inappropriate'=>'Conteúdo inadequado','duplicate'=>'Duplicado','privacy'=>'Privacidade','spam'=>'Spam','other'=>'Outro'];
@endphp
<x-app-layout>
    <x-slot name="header"><div class="page-header"><div><h1 class="page-title">Denunciar conteúdo público</h1><p class="mt-1 text-sm text-gray-600">A denúncia é privada e será analisada pela moderação global.</p></div></div></x-slot>
    <div class="mx-auto max-w-2xl"><div class="card mb-5 p-5"><p class="text-xs font-extrabold uppercase text-primary">{{ $targetType === 'question' ? 'Questão' : 'Recurso' }}</p><h2 class="mt-2 text-lg font-extrabold">{{ $title }}</h2></div>
        <form method="POST" action="{{ route('public-catalog.reports.store') }}" class="card space-y-5 p-6">@csrf<input type="hidden" name="type" value="{{ $targetType }}"><input type="hidden" name="target_id" value="{{ $target->id }}"><input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div><label for="reason_code" class="input-label">Motivo</label><select id="reason_code" name="reason_code" class="input-field" required><option value="">Selecione</option>@foreach($reasons as $reason)<option value="{{ $reason }}" @selected(old('reason_code') === $reason)>{{ $reasonLabels[$reason] }}</option>@endforeach</select><x-input-error :messages="$errors->get('reason_code')" class="mt-2" /></div>
            <div><label for="details" class="input-label">Detalhes verificáveis</label><textarea id="details" name="details" rows="7" minlength="20" maxlength="5000" class="input-field" required>{{ old('details') }}</textarea><p class="mt-1 text-xs text-gray-500">Não inclua dados pessoais de estudantes.</p><x-input-error :messages="$errors->get('details')" class="mt-2" /></div>
            <div class="flex justify-end gap-2"><a href="{{ $targetType === 'question' ? route('questions.index', ['tab'=>'platform']) : route('question-resources.index', ['scope'=>'platform']) }}" class="btn-secondary">Cancelar</a><button type="submit" class="btn-primary">Registrar denúncia</button></div>
        </form>
    </div>
</x-app-layout>
