@php
    $initialBenefits = old('benefits', isset($courtesy) ? $courtesy->benefits->map->only(['benefit_type','resource_key','quantity','plan_id','feature_key'])->values()->all() : [['benefit_type' => 'credit', 'resource_key' => 'monthly_omr_scans', 'quantity' => 100]]);
@endphp
<div x-data="{ scope: '{{ old('target_scope', $courtesy->target_scope ?? 'user') }}', benefits: @js($initialBenefits) }" class="space-y-6">
    <section class="rounded-2xl border-2 border-duo-border bg-white p-6">
        <h2 class="text-lg font-extrabold">Beneficiário</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-bold">Escopo</label>
                <select name="target_scope" x-model="scope" class="input-field">
                    <option value="user">Usuário individual</option><option value="role">Perfil de usuários</option>
                    <option value="organization">Instituição — todos os professores</option><option value="all">Todos os usuários</option>
                </select>
            </div>
            <div x-show="scope === 'user'"><label class="mb-1 block text-sm font-bold">Usuário</label>
                <select name="target_id" class="input-field" :disabled="scope !== 'user'">@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) old('target_id', $courtesy->target_id ?? '') === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach</select>
            </div>
            <div x-show="scope === 'organization'"><label class="mb-1 block text-sm font-bold">Instituição</label>
                <select name="target_id" class="input-field" :disabled="scope !== 'organization'">@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string) old('target_id', $courtesy->target_id ?? '') === (string) $organization->id)>{{ $organization->name }}</option>@endforeach</select>
            </div>
            <div x-show="scope === 'role'"><label class="mb-1 block text-sm font-bold">Perfil</label>
                <select name="target_role" class="input-field" :disabled="scope !== 'role'">
                    @foreach(['teacher'=>'Professores','institution_admin'=>'Administradores institucionais','student'=>'Alunos','guardian'=>'Responsáveis'] as $value => $label)<option value="{{ $value }}" @selected(old('target_role', $courtesy->target_role ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border-2 border-duo-border bg-white p-6">
        <div class="flex items-center justify-between"><h2 class="text-lg font-extrabold">Benefícios acumuláveis</h2><button type="button" class="btn-secondary btn-sm" @click="benefits.push({benefit_type:'credit',resource_key:'monthly_omr_scans',quantity:100})">Adicionar benefício</button></div>
        <div class="mt-4 space-y-4">
            <template x-for="(benefit, index) in benefits" :key="index"><div class="grid gap-3 rounded-xl border-2 border-duo-border bg-background-light p-4 md:grid-cols-5">
                <select :name="`benefits[${index}][benefit_type]`" x-model="benefit.benefit_type" class="input-field">
                    <option value="plan">Plano gratuito</option><option value="credit">Créditos adicionais</option><option value="replace">Substituir limite</option><option value="unlimited">Uso ilimitado</option><option value="feature">Liberar funcionalidade</option>
                </select>
                <select x-show="['credit','replace','unlimited'].includes(benefit.benefit_type)" :name="`benefits[${index}][resource_key]`" x-model="benefit.resource_key" class="input-field">
                    @foreach($resources as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
                <input x-show="['credit','replace'].includes(benefit.benefit_type)" type="number" min="1" :name="`benefits[${index}][quantity]`" x-model="benefit.quantity" placeholder="Quantidade" class="input-field">
                <select x-show="benefit.benefit_type === 'plan'" :name="`benefits[${index}][plan_id]`" x-model="benefit.plan_id" class="input-field">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select>
                <select x-show="benefit.benefit_type === 'feature'" :name="`benefits[${index}][feature_key]`" x-model="benefit.feature_key" class="input-field">@foreach($features as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <button type="button" class="font-bold text-red-600" @click="benefits.splice(index,1)" x-show="benefits.length > 1">Remover</button>
            </div></template>
        </div>
    </section>

    <section class="rounded-2xl border-2 border-duo-border bg-white p-6">
        <h2 class="text-lg font-extrabold">Validade e autorização</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block text-sm font-bold">Início</label><input type="datetime-local" name="starts_at" required class="input-field" value="{{ old('starts_at', isset($courtesy) ? $courtesy->starts_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}"></div>
            <div><label class="mb-1 block text-sm font-bold">Término</label><input type="datetime-local" name="ends_at" required class="input-field" value="{{ old('ends_at', isset($courtesy) ? $courtesy->ends_at->format('Y-m-d\TH:i') : now()->addMonth()->format('Y-m-d\TH:i')) }}"></div>
        </div>
        <div class="mt-4"><label class="mb-1 block text-sm font-bold">Justificativa obrigatória</label><textarea name="reason" required minlength="10" maxlength="2000" rows="4" class="input-field">{{ old('reason', $courtesy->reason ?? '') }}</textarea></div>
    </section>
    <div class="flex gap-3"><button class="btn-primary">{{ $submitLabel }}</button><a href="{{ route('admin.courtesies.index') }}" class="btn-secondary">Cancelar</a></div>
</div>
