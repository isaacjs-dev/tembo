<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <h2 class="page-title">Painel do Professor</h2>
                <p class="text-gray-500 font-medium mt-1">Bem-vindo(a) de volta, {{ auth()->user()->name }}!</p>
            </div>
        </div>
    </x-slot>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-card-icon bg-blue-50 text-blue-500 mb-3">
                <span class="material-symbols-outlined">assignment</span>
            </div>
            <p class="stat-card-label">Minhas Avaliações</p>
            <p class="stat-card-value">{{ number_format($stats['my_exams_count'] ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon bg-orange-50 text-orange-500 mb-3">
                <span class="material-symbols-outlined">meeting_room</span>
            </div>
            <p class="stat-card-label">Minhas Turmas</p>
            <p class="stat-card-value">{{ number_format($stats['my_classes_count'] ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-icon bg-red-50 text-red-500 mb-3">
                <span class="material-symbols-outlined">pending_actions</span>
            </div>
            <p class="stat-card-label">Avaliações p/ Corrigir</p>
            <p class="stat-card-value">{{ number_format($stats['submissions_to_grade'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Recent Exams -->
        <div class="card overflow-hidden lg:col-span-2">
            <div class="px-6 py-4 border-b-2 border-duo-border flex items-center justify-between">
                <h3 class="font-bold text-duo-heading">Avaliações Recentes</h3>
                <a href="{{ route('exams.index') }}" class="text-sm font-bold text-primary hover:underline">Ver todas</a>
            </div>
            <div class="divide-y-2 divide-duo-border">
                @forelse($recentExams as $exam)
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[20px]">description</span>
                            </div>
                            <div>
                                <p class="font-semibold text-duo-heading text-sm">{{ $exam->title }}</p>
                                <p class="text-xs text-gray-400">Criada {{ $exam->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <span class="badge
                            {{ $exam->status === 'published' ? 'badge-success' : ($exam->status === 'closed' ? 'badge-danger' : 'badge-neutral') }}">
                            {{ $exam->status === 'published' ? 'Publicada' : ($exam->status === 'closed' ? 'Encerrada' : 'Rascunho') }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-gray-400 font-medium">
                        Você ainda não criou nenhuma avaliação.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card p-6 h-full">
            <h3 class="font-bold text-duo-heading mb-6">Ações Rápidas</h3>
            <div class="flex flex-col gap-3">
                <a href="{{ route('exams.create') }}" class="btn-primary w-full justify-center">
                    <span class="material-symbols-outlined text-[20px]">add_box</span>
                    Nova Avaliação
                </a>
                <a href="{{ route('questions.create') }}" class="btn-secondary w-full justify-center">
                    <span class="material-symbols-outlined text-[20px]">add_circle</span>
                    Nova Questão
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
