<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Lixeira</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Lixeira</h1>
                <p class="text-sm text-gray-400 mt-1">Itens excluídos recentemente. Restaure ou exclua permanentemente.
                </p>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div
            class="mb-6 p-4 bg-green-50 border-2 border-green-200 rounded-xl text-green-700 font-medium text-sm flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    {{-- Questões na Lixeira --}}
    <div class="mb-8">
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-primary">quiz</span>
            Questões Excluídas
        </h2>

        @if ($trashedQuestions->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center">
                <p class="text-gray-400">Nenhuma questão na lixeira.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedQuestions as $q)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">
                                {{ \Illuminate\Support\Str::limit($q->content['statement'] ?? 'Sem enunciado', 80) }}</p>
                            <p class="text-xs text-gray-400">Excluída em {{ $q->deleted_at->format('d/m/Y H:i') }} · Por
                                {{ $q->owner?->name ?? 'N/A' }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('institution.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="question">
                                <input type="hidden" name="model_id" value="{{ $q->id }}">
                                <button type="submit" class="text-primary font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('institution.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('Excluir permanentemente? Esta ação não pode ser desfeita.')">
                                @csrf
                                <input type="hidden" name="model_type" value="question">
                                <input type="hidden" name="model_id" value="{{ $q->id }}">
                                <button type="submit" class="text-red-500 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedQuestions->links() }}</div>
        @endif
    </div>

    {{-- Avaliações na Lixeira --}}
    <div class="mb-8">
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-primary">assignment</span>
            Avaliações Excluídas
        </h2>

        @if ($trashedExams->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center">
                <p class="text-gray-400">Nenhuma avaliação na lixeira.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedExams as $exam)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">{{ $exam->title }}</p>
                            <p class="text-xs text-gray-400">
                                Excluída em {{ $exam->deleted_at->format('d/m/Y H:i') }} ·
                                Por {{ $exam->author?->name ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('institution.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="exam">
                                <input type="hidden" name="model_id" value="{{ $exam->id }}">
                                <button type="submit" class="text-primary font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('institution.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('Excluir permanentemente? Esta ação não pode ser desfeita.')">
                                @csrf
                                <input type="hidden" name="model_type" value="exam">
                                <input type="hidden" name="model_id" value="{{ $exam->id }}">
                                <button type="submit" class="text-red-500 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedExams->links() }}</div>
        @endif
    </div>

    {{-- Materiais na Lixeira --}}
    <div class="mb-8">
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary" aria-hidden="true">auto_stories</span>
            Materiais de Estudo Excluídos
        </h2>

        @if ($trashedMaterials->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center">
                <p class="text-gray-500">Nenhum material na lixeira.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedMaterials as $material)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-bold text-gray-900 text-sm">{{ $material->title }}</p>
                            <p class="text-xs text-gray-600">
                                Excluído em {{ $material->deleted_at->format('d/m/Y H:i') }} ·
                                Por {{ $material->author?->name ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <form action="{{ route('institution.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="learning_material">
                                <input type="hidden" name="model_id" value="{{ $material->id }}">
                                <button type="submit" class="text-primary-dark font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('institution.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('Excluir permanentemente? Esta ação não pode ser desfeita.')">
                                @csrf
                                <input type="hidden" name="model_type" value="learning_material">
                                <input type="hidden" name="model_id" value="{{ $material->id }}">
                                <button type="submit" class="text-red-700 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedMaterials->links() }}</div>
        @endif
    </div>

    {{-- Turmas na Lixeira --}}
    <div>
        <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
            <span aria-hidden="true" class="material-symbols-outlined text-primary">groups</span>
            Turmas Excluídas
        </h2>

        @if ($trashedClasses->isEmpty())
            <div class="bg-white rounded-2xl border-2 border-duo-border p-8 text-center">
                <p class="text-gray-400">Nenhuma turma na lixeira.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($trashedClasses as $c)
                    <div class="bg-white rounded-2xl border-2 border-duo-border p-4 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">{{ $c->name }} ({{ $c->year }})</p>
                            <p class="text-xs text-gray-400">Excluída em {{ $c->deleted_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('institution.trash.restore') }}" method="POST">
                                @csrf
                                <input type="hidden" name="model_type" value="class">
                                <input type="hidden" name="model_id" value="{{ $c->id }}">
                                <button type="submit" class="text-primary font-bold text-sm hover:underline">Restaurar</button>
                            </form>
                            <form action="{{ route('institution.trash.forceDelete') }}" method="POST"
                                onsubmit="return confirm('Excluir permanentemente?')">
                                @csrf
                                <input type="hidden" name="model_type" value="class">
                                <input type="hidden" name="model_id" value="{{ $c->id }}">
                                <button type="submit" class="text-red-500 font-bold text-sm hover:underline">Excluir</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $trashedClasses->links() }}</div>
        @endif
    </div>
</x-app-layout>
