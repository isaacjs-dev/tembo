<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="{{ route('admin.users.index') }}">Usuários</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar Usuário</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Usuário</h1>
                <p class="text-gray-400 mt-1">Altere informações pessoais, perfil e organização.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 px-6 py-4 bg-white border-2 border-duo-border text-gray-600 font-extrabold rounded-xl transition-all uppercase tracking-wider text-sm whitespace-nowrap hover:border-gray-400">
                    Cancelar
                </a>
                <button type="submit" form="user-form" class="flex items-center gap-2 px-6 py-4 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all uppercase tracking-wider text-sm whitespace-nowrap duo-button-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">save</span>
                    Salvar Alterações
                </button>
            </div>
        </div>
    </x-slot>

    <form id="user-form" action="{{ route('admin.users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h3 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">person</span> Informações do Usuário
                </h3>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nome *</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                        @error('name') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">E-mail *</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                        @error('email') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Perfil de Acesso *</label>
                        <select name="type" required class="input-field text-sm">
                            <option value="global_admin" {{ old('type', $user->type) == 'global_admin' ? 'selected' : '' }}>Administrador Global (SaaS)</option>
                            <option value="institution_admin" {{ old('type', $user->type) == 'institution_admin' ? 'selected' : '' }}>Administrador Institucional</option>
                            <option value="teacher" {{ old('type', $user->type) == 'teacher' ? 'selected' : '' }}>Professor</option>
                            <option value="student" {{ old('type', $user->type) == 'student' ? 'selected' : '' }}>Aluno</option>
                            <option value="guardian" {{ old('type', $user->type) == 'guardian' ? 'selected' : '' }}>Responsável</option>
                        </select>
                        @error('type') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Organização</label>
                        <select name="organization_id" class="input-field text-sm">
                            <option value="">Nenhuma (Global)</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ old('organization_id', $user->organization_id) == $org->id ? 'selected' : '' }}>
                                    {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Obrigatório para Administrar uma Instituição específica.</p>
                        @error('organization_id') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Plano (SaaS)</label>
                        <select name="plan_id" class="input-field text-sm">
                            <option value="">Nenhum Plano Atribuído (Free)</option>
                            @php $currentPlan = $user->subscription->plan_id ?? null; @endphp
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" {{ old('plan_id', $currentPlan) == $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Ao vincular, uma nova assinatura é automaticamente gerada ou substituída.</p>
                        @error('plan_id') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nova Senha</label>
                        <input type="password" name="password" placeholder="Deixe vazio para manter a senha atual" class="input-field text-sm">
                        @error('password') <p class="mt-1 text-xs text-red-500 font-bold">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" placeholder="Repita a nova senha" class="input-field text-sm">
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
