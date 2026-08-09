<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-3xl font-extrabold text-duo-heading dark:text-white">Escolha seu espaço</h1>
            <p class="mt-1 text-sm text-gray-500">Cada espaço mantém pessoas, turmas e conteúdo isolados.</p>
        </div>
    </x-slot>

    <div class="mx-auto grid max-w-4xl gap-4 md:grid-cols-2">
        @forelse ($workspaces as $workspace)
            <form method="POST" action="{{ route('workspaces.switch', $workspace) }}">
                @csrf
                <button type="submit"
                    class="w-full rounded-2xl border-2 p-6 text-left transition hover:border-primary hover:shadow-md {{ (int) $currentWorkspaceId === (int) $workspace->id ? 'border-primary bg-primary/5' : 'border-duo-border bg-white' }}">
                    <span class="mb-3 inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined" aria-hidden="true">{{ $workspace->workspace_type === 'personal' ? 'person' : 'domain' }}</span>
                    </span>
                    <span class="block text-lg font-extrabold text-duo-heading">{{ $workspace->name }}</span>
                    <span class="mt-1 block text-sm text-gray-500">
                        {{ $workspace->workspace_type === 'personal' ? 'Espaço pessoal' : 'Instituição' }}
                    </span>
                    @if ((int) $currentWorkspaceId === (int) $workspace->id)
                        <span class="mt-4 inline-flex rounded-full bg-primary px-3 py-1 text-xs font-bold text-white">Atual</span>
                    @endif
                </button>
            </form>
        @empty
            <div class="rounded-2xl border-2 border-duo-border bg-white p-8 text-center md:col-span-2">
                <p class="font-bold text-duo-heading">Nenhum espaço ativo disponível.</p>
                <p class="mt-1 text-sm text-gray-500">Aceite um convite válido ou procure a administração.</p>
            </div>
        @endforelse
    </div>

    @if (in_array(Auth::user()->type, ['teacher', 'institution_admin'], true) && ! $workspaces->contains(fn ($workspace) => $workspace->isPersonalWorkspace()))
        <form method="POST" action="{{ route('workspaces.personal.store') }}" class="mx-auto mt-6 max-w-4xl">
            @csrf
            <button type="submit" class="btn-secondary w-full justify-center">
                <span class="material-symbols-outlined" aria-hidden="true">person_add</span>
                Criar meu espaÃ§o pessoal de professor
            </button>
        </form>
    @endif
</x-app-layout>
