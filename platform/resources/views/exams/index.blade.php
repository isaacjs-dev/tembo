<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Avaliações</span>
                </nav>
                <h1 class="page-title">Avaliações</h1>
            </div>
            <a href="{{ route('exams.create') }}" class="btn-primary">
                <span class="material-symbols-outlined">add_circle</span>
                Nova Avaliação
            </a>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        @forelse($exams as $exam)
            <div class="card card-hover p-6 flex flex-col h-full">
                <div class="flex items-center justify-between mb-4">
                    <span class="badge
                        @if($exam->status === 'draft') badge-neutral
                        @elseif($exam->status === 'published') badge-success
                        @else badge-danger @endif">
                        @if($exam->status === 'draft') Rascunho
                        @elseif($exam->status === 'published') Publicada
                        @else Encerrada @endif
                    </span>
                    <span class="text-xs font-bold text-gray-400">{{ $exam->created_at->format('d/m/Y') }}</span>
                </div>

                <h3 class="text-xl font-extrabold text-duo-heading mb-2 leading-tight flex-1">
                    {{ $exam->title }}
                </h3>

                <div class="flex items-center gap-4 text-sm font-bold text-gray-400 mb-6">
                    <div class="flex items-center gap-1" title="Questões">
                        <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
                        <span>{{ $exam->questions_count }}</span>
                    </div>
                    @if(!empty($exam->settings['time_limit']))
                        <div class="flex items-center gap-1" title="Tempo Limite">
                            <span class="material-symbols-outlined text-[18px]">timer</span>
                            <span>{{ $exam->settings['time_limit'] }} min</span>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 pt-4 border-t-2 border-duo-border">
                    <a href="{{ route('exams.show', $exam->id) }}"
                        class="flex-1 text-center bg-blue-50 text-blue-700 hover:bg-blue-100 py-2 rounded-xl font-bold text-xs uppercase tracking-wider transition-colors"
                        title="Resultados e Correção">
                        Resultados
                    </a>
                    <a href="{{ route('exams.edit', $exam->id) }}"
                        class="flex-1 btn-secondary btn-sm text-center py-2 rounded-xl text-xs uppercase tracking-wider">
                        Configurar
                    </a>
                    <form action="{{ route('exams.duplicate', $exam->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="btn-icon" title="Duplicar">
                            <span class="material-symbols-outlined text-[20px]">content_copy</span>
                        </button>
                    </form>
                    <form action="{{ route('exams.destroy', $exam->id) }}" method="POST"
                        onsubmit="return confirm('Deseja excluir esta avaliação?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="p-2.5 rounded-xl transition-all duration-150 text-gray-400 hover:text-red-500 hover:bg-red-50"
                            title="Excluir">
                            <span class="material-symbols-outlined text-[20px]">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full card p-16 text-center">
                <span class="material-symbols-outlined text-6xl text-gray-300 mb-4 block">assignment</span>
                <h3 class="text-2xl font-bold text-duo-heading">Nenhuma avaliação encontrada</h3>
                <p class="text-gray-500 mt-2 max-w-md mx-auto">
                    Você ainda não criou nenhuma avaliação. Clique no botão acima para começar.
                </p>
                <a href="{{ route('exams.create') }}" class="btn-primary mt-6">
                    <span class="material-symbols-outlined">add_circle</span>
                    Criar Primeira Avaliação
                </a>
            </div>
        @endforelse
    </div>

    @if($exams->hasPages())
        <div class="mt-6">
            {{ $exams->links() }}
        </div>
    @endif
</x-app-layout>
