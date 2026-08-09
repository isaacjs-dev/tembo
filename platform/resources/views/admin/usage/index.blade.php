<x-app-layout>
    <x-slot name="header">
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-duo-heading">Consumo mensal</h1>
            <p class="mt-1 text-gray-500">As franquias são individuais por professor e renovadas no primeiro dia de cada mês.</p>
        </div>
    </x-slot>

    @if(session('status'))
        <div role="status" class="mb-6 rounded-xl border-2 border-green-200 bg-green-50 px-5 py-3 font-bold text-green-700">{{ session('status') }}</div>
    @endif

    <section class="mb-8 rounded-2xl border-2 border-duo-border bg-white p-6"
        x-data="{
            scope: '{{ old('target_scope', 'user') }}', preview: null, loading: false,
            async loadPreview() {
                this.loading = true;
                const form = this.$refs.resetForm;
                const body = new FormData(form);
                const response = await fetch('{{ route('admin.usage.preview') }}', {
                    method: 'POST', headers: {'Accept': 'application/json'}, body
                });
                this.preview = response.ok ? await response.json() : {error: 'Não foi possível calcular a prévia.'};
                this.loading = false;
            }
        }">
        <h2 class="text-xl font-extrabold text-duo-heading">Redefinição manual auditada</h2>
        <p class="mt-1 text-sm text-gray-500">O histórico será preservado. Somente o consumo do período atual voltará a zero.</p>

        <form x-ref="resetForm" method="POST" action="{{ route('admin.usage.reset') }}" class="mt-6 space-y-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-bold">Público afetado</label>
                    <select name="target_scope" x-model="scope" class="input-field">
                        <option value="user">Um usuário</option><option value="role">Um perfil</option>
                        <option value="organization">Uma instituição</option><option value="all">Todos os usuários</option>
                    </select>
                </div>
                <div x-show="scope === 'user'">
                    <label class="mb-1 block text-sm font-bold">Usuário</label>
                    <select name="target_id" class="input-field" :disabled="scope !== 'user'">
                        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach
                    </select>
                </div>
                <div x-show="scope === 'role'">
                    <label class="mb-1 block text-sm font-bold">Perfil</label>
                    <select name="target_role" class="input-field" :disabled="scope !== 'role'">
                        <option value="teacher">Professores</option><option value="institution_admin">Administradores institucionais</option>
                        <option value="student">Alunos</option><option value="guardian">Responsáveis</option>
                    </select>
                </div>
                <div x-show="scope === 'organization'">
                    <label class="mb-1 block text-sm font-bold">Instituição</label>
                    <select name="target_id" class="input-field" :disabled="scope !== 'organization'">
                        @foreach($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <fieldset>
                <legend class="mb-2 text-sm font-bold">Recursos que voltarão a zero</legend>
                <div class="grid gap-3 md:grid-cols-3">
                    @foreach($resources as $key => $label)
                        <label class="rounded-xl border-2 border-duo-border p-4 font-bold"><input type="checkbox" name="resource_keys[]" value="{{ $key }}" class="mr-2" checked> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div>
                <label class="mb-1 block text-sm font-bold">Justificativa</label>
                <textarea name="reason" required minlength="10" maxlength="2000" class="input-field" rows="3">{{ old('reason') }}</textarea>
            </div>
            <button type="button" @click="loadPreview" class="btn-secondary" :disabled="loading" x-text="loading ? 'Calculando...' : 'Visualizar usuários afetados'"></button>
            <div x-show="preview" x-cloak class="rounded-xl border-2 border-blue-200 bg-blue-50 p-4">
                <template x-if="preview && !preview.error"><div>
                    <p class="font-extrabold" x-text="`${preview.affected_count} usuário(s) serão afetados.`"></p>
                    <ul class="mt-2 max-h-40 overflow-y-auto text-sm"><template x-for="user in preview.users" :key="user.id"><li x-text="`${user.name} — ${user.email} (${user.type})`"></li></template></ul>
                    <p x-show="preview.truncated" class="mt-2 text-xs font-bold">A lista foi resumida; a contagem acima representa o total.</p>
                </div></template>
                <p x-show="preview && preview.error" x-text="preview?.error || ''" class="font-bold text-red-700"></p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold">Digite REDEFINIR LIMITES para confirmar</label>
                <input name="confirmation" required pattern="REDEFINIR LIMITES" autocomplete="off" class="input-field">
            </div>
            <button class="btn-primary" onclick="return confirm('Confirma a redefinição dos limites do público selecionado?')">Redefinir limites</button>
        </form>
    </section>

    <section class="rounded-2xl border-2 border-duo-border bg-white overflow-hidden">
        <div class="border-b-2 border-duo-border p-5"><h2 class="text-xl font-extrabold">Períodos de consumo</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-background-light"><tr><th class="p-4">Usuário</th><th class="p-4">Recurso</th><th class="p-4">Período</th><th class="p-4">Uso</th><th class="p-4">Resets</th></tr></thead>
            <tbody class="divide-y-2 divide-duo-border">@forelse($periods as $period)<tr>
                <td class="p-4"><strong>{{ $period->user?->name }}</strong><br><span class="text-xs text-gray-500">{{ $period->user?->email }}</span></td>
                <td class="p-4">{{ $resources[$period->resource_key] ?? $period->resource_key }}</td>
                <td class="p-4">{{ $period->period_start->format('d/m/Y') }}–{{ $period->period_end->format('d/m/Y') }}</td>
                <td class="p-4 font-extrabold">{{ $period->consumed }} / {{ $period->allowance === null ? 'Ilimitado' : $period->allowance }}</td>
                <td class="p-4">{{ $period->manual_resets }}</td>
            </tr>@empty<tr><td colspan="5" class="p-8 text-center text-gray-500">Nenhum consumo registrado.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="p-4">{{ $periods->links() }}</div>
    </section>
</x-app-layout>
