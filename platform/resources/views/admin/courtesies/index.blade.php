<x-app-layout>
    <x-slot name="header"><div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center"><div><h1 class="text-4xl font-extrabold text-duo-heading">Cortesias</h1><p class="mt-1 text-gray-500">Planos gratuitos, créditos e recursos temporários.</p></div><a href="{{ route('admin.courtesies.create') }}" class="btn-primary">Conceder cortesia</a></div></x-slot>
    @if(session('status'))<div role="status" class="mb-6 rounded-xl border-2 border-green-200 bg-green-50 px-5 py-3 font-bold text-green-700">{{ session('status') }}</div>@endif
    <div class="overflow-hidden rounded-2xl border-2 border-duo-border bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-background-light"><tr><th class="p-4">Beneficiário</th><th class="p-4">Benefícios</th><th class="p-4">Validade</th><th class="p-4">Estado</th><th class="p-4">Responsável</th><th class="p-4 text-right">Ações</th></tr></thead>
        <tbody class="divide-y-2 divide-duo-border">@forelse($grants as $grant)<tr>
            <td class="p-4 font-bold">{{ match($grant->target_scope) {'all'=>'Todos','role'=>'Perfil: '.$grant->target_role,'organization'=>'Instituição #'.$grant->target_id,default=>'Usuário #'.$grant->target_id} }}</td>
            <td class="p-4"><ul>@foreach($grant->benefits as $benefit)<li>{{ match($benefit->benefit_type) {'plan'=>'Plano '.$benefit->plan?->name,'credit'=>'+'.$benefit->quantity.' '.$benefit->resource_key,'replace'=>'Limite '.$benefit->quantity.' '.$benefit->resource_key,'unlimited'=>'Ilimitado: '.$benefit->resource_key,'feature'=>'Recurso: '.$benefit->feature_key,default=>$benefit->benefit_type} }}</li>@endforeach</ul></td>
            <td class="p-4">{{ $grant->starts_at->format('d/m/Y H:i') }}<br>{{ $grant->ends_at->format('d/m/Y H:i') }}</td>
            <td class="p-4"><span class="badge badge-{{ $grant->status === 'active' ? 'success' : ($grant->status === 'suspended' ? 'warning' : 'neutral') }}">{{ $grant->status }}</span></td>
            <td class="p-4">{{ $grant->authorizer?->name }}<br><span class="text-xs text-gray-500">{{ $grant->reason }}</span></td>
            <td class="p-4"><div class="flex justify-end gap-2"><a href="{{ route('admin.courtesies.edit', $grant) }}" class="btn-secondary btn-sm">Editar</a>
                @if(in_array($grant->status, ['active','scheduled']))<form method="POST" action="{{ route('admin.courtesies.suspend', $grant) }}">@csrf<button class="btn-secondary btn-sm">Suspender</button></form>@endif
                @if($grant->status === 'suspended')<form method="POST" action="{{ route('admin.courtesies.activate', $grant) }}">@csrf<button class="btn-secondary btn-sm">Reativar</button></form>@endif
                @if(!in_array($grant->status, ['cancelled','expired']))<form method="POST" action="{{ route('admin.courtesies.cancel', $grant) }}" onsubmit="const reason=prompt('Justificativa do cancelamento (mínimo 10 caracteres):'); if(!reason||reason.length<10)return false; this.querySelector('[name=reason]').value=reason;"><input type="hidden" name="reason">@csrf<button class="btn-secondary btn-sm text-red-600">Cancelar</button></form>@endif
            </div></td>
        </tr>@empty<tr><td colspan="6" class="p-8 text-center text-gray-500">Nenhuma cortesia cadastrada.</td></tr>@endforelse</tbody>
    </table></div><div class="p-4">{{ $grants->links() }}</div></div>
</x-app-layout>
