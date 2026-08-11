<x-app-layout>
    <x-slot name="header"><h1 class="text-3xl font-black text-duo-heading">Relatório · {{ $lesson->title }}</h1></x-slot>
    <div class="overflow-x-auto rounded-2xl border-2 border-duo-border bg-white">
        <table class="min-w-full"><thead><tr class="border-b text-left"><th class="p-4">Aluno</th><th class="p-4">Status</th><th class="p-4">Início</th><th class="p-4">Conclusão</th></tr></thead>
            <tbody>@forelse($students as $student) @php($item=$progress->get($student->id))
                <tr class="border-b"><td class="p-4 font-bold">{{ $student->name }}</td><td class="p-4">{{ $item ? ($item->status==='completed'?'Concluída':'Em andamento') : 'Não iniciada' }}</td><td class="p-4">{{ $item?->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</td><td class="p-4">{{ $item?->completed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</td></tr>
            @empty<tr><td class="p-6" colspan="4">Nenhum aluno ativo nas turmas.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->withQueryString()->links() }}</div>
</x-app-layout>
