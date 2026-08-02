<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="{{ route('questions.index') }}">Banco de Questões</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Compartilhar</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Compartilhar Questão</h1>
                <p class="text-gray-500 font-medium mt-1">ID: {{ $question->id }} - {{ Str::limit($question->content['statement'] ?? '', 80) }}</p>
            </div>
            <a href="{{ route('questions.index') }}" class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12 max-w-3xl">
        <form action="{{ route('questions.storeShare', $question->id) }}" method="POST">
            @csrf
            
            <div class="mb-6 bg-blue-50 text-blue-800 p-4 rounded-xl border-2 border-blue-200">
                <div class="flex items-start gap-3">
                    <span aria-hidden="true" class="material-symbols-outlined">info</span>
                    <div class="text-sm">
                        <strong class="block">Sobre o compartilhamento:</strong>
                        Questões compartilhadas poderão ser visualizadas por outros professores e copiadas para os seus bancos de questões locais. Se você quiser que a escola toda veja, edite a questão e defina como <strong>Pública na Instituição</strong>.
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b-2 border-duo-border pb-2">Selecionar Professores</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 max-h-96 overflow-y-auto p-2">
                @forelse($teachers as $teacher)
                <label class="flex items-center gap-3 p-4 border-2 border-duo-border rounded-xl cursor-pointer hover:border-primary transition-colors {{ in_array($teacher->id, $sharedWithIds) ? 'bg-primary/5 border-primary' : '' }}">
                    <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}"
                           class="size-5 rounded text-primary focus:ring-primary focus:ring-opacity-50 border-gray-300"
                           {{ in_array($teacher->id, $sharedWithIds) ? 'checked' : '' }}>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 text-sm">{{ $teacher->name }}</span>
                        <span class="text-xs text-gray-500">{{ $teacher->email }}</span>
                    </div>
                </label>
                @empty
                    <p class="text-gray-500 italic col-span-2">Nenhum outro professor encontrado na sua instituição.</p>
                @endforelse
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end gap-4">
                <button type="submit" class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined">share</span>
                    Salvar Compartilhamento
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
