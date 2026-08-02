<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 mb-6">
            <h1 class="text-3xl font-extrabold tracking-tight">Painel Institucional</h1>
            <p class="text-gray-500 font-medium">Visão geral do sistema e principais métricas.</p>
        </div>
    </x-slot>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center">
                    <span aria-hidden="true" class="material-symbols-outlined">group</span>
                </div>
            </div>
            <p class="text-sm font-medium text-gray-500">Alunos</p>
            <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['students_count'], 0, ',', '.') }}</p>
        </div>

        <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                    <span aria-hidden="true" class="material-symbols-outlined">school</span>
                </div>
            </div>
            <p class="text-sm font-medium text-gray-500">Professores</p>
            <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['teachers_count'], 0, ',', '.') }}</p>
        </div>

        <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center">
                    <span aria-hidden="true" class="material-symbols-outlined">meeting_room</span>
                </div>
            </div>
            <p class="text-sm font-medium text-gray-500">Turmas</p>
            <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['classes_count'], 0, ',', '.') }}</p>
        </div>

        <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-accent-light text-accent rounded-xl flex items-center justify-center">
                    <span aria-hidden="true" class="material-symbols-outlined">assignment</span>
                </div>
            </div>
            <p class="text-sm font-medium text-gray-500">Avaliações</p>
            <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['exams_count'], 0, ',', '.') }}</p>
        </div>

        <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-cyan-50 text-cyan-500 rounded-xl flex items-center justify-center">
                    <span aria-hidden="true" class="material-symbols-outlined">chat_bubble</span>
                </div>
            </div>
            <p class="text-sm font-medium text-gray-500">Respostas</p>
            <p class="text-2xl font-extrabold mt-1">{{ number_format($stats['submissions_count'], 0, ',', '.') }}</p>
        </div>
    </div>

    <!-- Main Grid Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Recent Activity -->
        <div class="bg-white dark:bg-white/5 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 overflow-hidden lg:col-span-2">
            <div class="p-6 border-b border-gray-100 dark:border-white/10 flex items-center justify-between">
                <h2 class="font-bold text-lg">Atividade Recente</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse($recentActivities as $activity)
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center">
                                <span aria-hidden="true" class="material-symbols-outlined text-[20px]">how_to_reg</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ $activity->user->name ?? 'Aluno' }} concluiu a avaliação
                                </p>
                                <p class="text-xs text-gray-500">Avaliação: {{ $activity->exam->title ?? 'N/A' }} • {{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-1 rounded-md {{ $activity->status === 'graded' ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                            {{ $activity->status === 'graded' ? 'Corrigido' : 'Pendente' }}
                        </span>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        Nenhuma atividade recente encontrada.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-white/5 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 h-full">
                <h2 class="font-bold text-lg mb-6">Ações Rápidas</h2>
                <div class="flex flex-col gap-3">
                    <a href="{{ route('institution.teachers.index') }}"
                        class="w-full bg-primary text-white py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-opacity-90 transition-all shadow-lg shadow-primary/20">
                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">person_add</span>
                        Gerenciar Professores
                    </a>

                    <a href="{{ route('institution.settings') }}"
                        class="w-full bg-white dark:bg-transparent border-2 border-primary/20 text-primary py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-primary/5 transition-all">
                        <span aria-hidden="true" class="material-symbols-outlined text-[20px]">settings</span>
                        Configurações Institucionais
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
