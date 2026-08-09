<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors"
                        href="{{ route('institution.teachers.index') }}">Professores</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Adicionar Professor</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Adicionar Professor
                </h1>
            </div>
            <a href="{{ route('institution.teachers.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Cancelar
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl" x-data="teacherForm()">

        <!-- Error Handling -->
        @if ($errors->any())
            <div
                role="alert"
                class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- STEP 1: Buscar professor existente --}}
        <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-6">
            <h2 class="text-lg font-extrabold text-gray-700 mb-1 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">search</span>
                Buscar Professor Existente
            </h2>
            <p class="text-sm text-gray-400 mb-4">Digite o e-mail ou código de vínculo (8 caracteres) para verificar se
                o professor já existe no sistema.</p>

            <div class="flex gap-3">
                <div class="relative group flex-1">
                    <div
                        class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">person_search</span>
                    </div>
                    <label for="teacher-search" class="sr-only">Buscar professor por e-mail ou código de vínculo</label>
                    <input id="teacher-search" type="search" x-model="searchQuery" @keydown.enter.prevent="search()"
                        class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="E-mail ou código de vínculo (ex: ABCD1234)">
                </div>
                <button type="button" @click="search()" :disabled="searching"
                    class="duo-button-secondary px-6 py-3 rounded-xl font-extrabold text-sm uppercase tracking-wider disabled:opacity-50">
                    <span x-show="!searching">Buscar</span>
                    <span x-show="searching" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"
                                class="opacity-25"></circle>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                class="opacity-75"></path>
                        </svg>
                        Buscando...
                    </span>
                </button>
            </div>

            {{-- Resultado da busca --}}
            <template x-if="searchResult !== null">
                <div class="mt-4">
                    {{-- Usuário encontrado --}}
                    <template x-if="searchResult.found">
                        <div class="border-2 rounded-xl p-5"
                            :class="searchResult.already_linked ? 'border-yellow-300 bg-yellow-50' : 'border-green-300 bg-green-50'">
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span aria-hidden="true" class="material-symbols-outlined text-primary text-[24px]">person</span>
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-gray-800" x-text="searchResult.user.name"></p>
                                    <p class="text-sm text-gray-500" x-text="searchResult.user.email"></p>
                                    <p class="text-xs text-gray-400 mt-0.5">Código: <span class="font-mono font-bold"
                                            x-text="searchResult.user.link_code"></span></p>
                                </div>
                                <template x-if="!searchResult.already_linked">
                                    <form method="POST" action="{{ route('institution.teachers.store') }}">
                                        @csrf
                                        <input type="hidden" name="invite_user_id" :value="searchResult.user.id">
                                        <button type="submit"
                                            class="duo-button-primary px-6 py-3 rounded-xl font-extrabold text-sm uppercase flex items-center gap-2">
                                            <span aria-hidden="true" class="material-symbols-outlined text-[18px]">mail</span>
                                            Enviar Convite
                                        </button>
                                    </form>
                                </template>
                            </div>
                            <p class="text-sm font-medium mt-3"
                                :class="searchResult.already_linked ? 'text-yellow-700' : 'text-green-700'"
                                x-text="searchResult.message"></p>
                        </div>
                    </template>

                    {{-- Não encontrado --}}
                    <template x-if="!searchResult.found">
                        <div class="border-2 border-blue-200 bg-blue-50 rounded-xl p-5">
                            <div class="flex items-center gap-3">
                                <span aria-hidden="true" class="material-symbols-outlined text-blue-500 text-[24px]">info</span>
                                <p class="text-sm font-medium text-blue-700" x-text="searchResult.message"></p>
                            </div>
                            <button type="button" @click="showCreateForm = true"
                                class="mt-3 duo-button-primary px-6 py-3 rounded-xl font-extrabold text-sm uppercase flex items-center gap-2">
                                <span aria-hidden="true" class="material-symbols-outlined text-[18px]">person_add</span>
                                Cadastrar Novo Professor
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- STEP 2: Formulário de criação (novo professor) --}}
        <div x-show="showCreateForm" x-transition class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12">
            <h2 class="text-lg font-extrabold text-gray-700 mb-4 flex items-center gap-2">
                <span aria-hidden="true" class="material-symbols-outlined text-primary">person_add</span>
                Cadastrar Novo Professor
            </h2>

            <form action="{{ route('institution.teachers.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <label for="name" class="text-gray-800 font-bold text-sm">Nome Completo</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="Nome do Professor">
                </div>

                <div class="space-y-2">
                    <label for="email" class="text-gray-800 font-bold text-sm">E-mail (Usado para login)</label>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">mail</span>
                        </div>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                            class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                            placeholder="professor@escola.com.br">
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="password" class="text-gray-800 font-bold text-sm">Senha provisória</label>
                    <div class="relative group">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">lock</span>
                        </div>
                        <input type="password" id="password" name="password" required
                            class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                            placeholder="Mínimo 8 caracteres">
                    </div>
                    <p class="text-xs text-gray-500">O titular deverá confirmar o e-mail e trocar esta senha antes de acessar dados institucionais.</p>
                </div>

                <div class="space-y-2">
                    <label for="status" class="text-gray-800 font-bold text-sm">Status</label>
                    <select id="status" name="status"
                        class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>

                <div class="pt-6 border-t-2 border-duo-border flex justify-end">
                    <button type="submit"
                        class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                        Salvar Professor
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function teacherForm() {
                return {
                    searchQuery: '',
                    searching: false,
                    searchResult: null,
                    showCreateForm: false,

                    async search() {
                        if (!this.searchQuery || this.searchQuery.length < 3) return;

                        this.searching = true;
                        this.searchResult = null;
                        this.showCreateForm = false;

                        try {
                            const response = await fetch('{{ route("institution.teachers.search") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ search: this.searchQuery }),
                            });

                            if (!response.ok) {
                                const err = await response.json();
                                this.searchResult = { found: false, message: err.errors?.search?.[0] || 'Erro ao buscar.' };
                                return;
                            }

                            this.searchResult = await response.json();
                        } catch (e) {
                            this.searchResult = { found: false, message: 'Erro de conexão. Tente novamente.' };
                        } finally {
                            this.searching = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
