<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Lixeira</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Lixeira Administrativa
                </h1>
                <p class="text-gray-500 font-medium mt-1">Restaure itens excluídos ou apague-os permanentemente.</p>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl font-bold">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8 mb-12">
        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden">
            <div class="p-6 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined">quiz</span>
                    Questões Excluídas
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <caption class="sr-only">Usuários removidos</caption>
                    <tbody class="divide-y-2 divide-duo-border">
                        @forelse($deletedQuestions as $uq)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">
                                        {{ Str::limit($uq->content['statement'] ?? 'Sem enunciado', 80) }}</div>
                                    <div class="text-xs text-gray-500">Excluído em:
                                        {{ $uq->deleted_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form
                                            action="{{ route('institution.trash.restore', ['type' => 'question', 'id' => $uq->id]) }}"
                                            method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="p-2 text-gray-400 hover:text-green-500 hover:bg-green-50 rounded-lg transition-all"
                                                title="Restaurar">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">restore</span>
                                            </button>
                                        </form>
                                        <form
                                            action="{{ route('institution.trash.force', ['type' => 'question', 'id' => $uq->id]) }}"
                                            method="POST"
                                            onsubmit="return confirm('ATENÇÃO: Isso vai excluir a questão permanentemente. Deseja continuar?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                                title="Excluir Permanentemente">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">delete_forever</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-gray-500 font-medium">Nenhuma questão na
                                    lixeira.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden">
            <div class="p-6 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined">group</span>
                    Usuários Excluídos (Professores e Alunos)
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <caption class="sr-only">Turmas removidas</caption>
                    <tbody class="divide-y-2 divide-duo-border">
                        @forelse($deletedUsers as $user)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">{{ $user->name }}
                                        ({{ ucfirst($user->type) }})</div>
                                    <div class="text-xs text-gray-500">{{ $user->email }} | Excluído em:
                                        {{ $user->deleted_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form
                                            action="{{ route('institution.trash.restore', ['type' => 'user', 'id' => $user->id]) }}"
                                            method="POST">
                                            @csrf
                                            <button type="submit"
                                                class="p-2 text-gray-400 hover:text-green-500 hover:bg-green-50 rounded-lg transition-all"
                                                title="Restaurar">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">restore</span>
                                            </button>
                                        </form>
                                        <form
                                            action="{{ route('institution.trash.force', ['type' => 'user', 'id' => $user->id]) }}"
                                            method="POST"
                                            onsubmit="return confirm('ATENÇÃO: A exclusão permanente de um usuário apaga também suas interações. Deseja continuar?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                                                title="Excluir Permanentemente">
                                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">delete_forever</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-gray-500 font-medium">Nenhum usuário na
                                    lixeira.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
