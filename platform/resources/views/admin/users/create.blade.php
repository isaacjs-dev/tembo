<x-app-layout>
    <x-slot name="header">
        <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('admin.users.index') }}"
                    class="mb-2 inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline">
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
                    Usuários
                </a>
                <h1 class="text-3xl font-extrabold tracking-tight text-duo-heading md:text-4xl">Novo usuário</h1>
                <p class="mt-1 text-gray-500">Cadastre a conta e aplique seu perfil de acesso inicial.</p>
            </div>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-xl border-2 border-red-200 bg-red-50 px-5 py-4 text-red-800">
            <p class="font-extrabold">Não foi possível criar a conta.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}"
        class="mx-auto max-w-4xl rounded-2xl border-2 border-duo-border bg-white p-6 md:p-8">
        @csrf

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-2 block text-sm font-bold text-gray-700">Nome</label>
                <input id="name" name="name" type="text" required autocomplete="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label for="email" class="mb-2 block text-sm font-bold text-gray-700">E-mail</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label for="type" class="mb-2 block text-sm font-bold text-gray-700">Perfil de acesso</label>
                <select id="type" name="type" required
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                    <option value="global_admin" @selected(old('type') === 'global_admin')>Administrador global</option>
                    <option value="institution_admin" @selected(old('type') === 'institution_admin')>Administrador institucional</option>
                    <option value="teacher" @selected(old('type') === 'teacher')>Professor</option>
                    <option value="student" @selected(old('type') === 'student')>Aluno</option>
                    <option value="guardian" @selected(old('type') === 'guardian')>Responsável</option>
                </select>
            </div>
            <div>
                <label for="organization_id" class="mb-2 block text-sm font-bold text-gray-700">Instituição</label>
                <select id="organization_id" name="organization_id"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                    <option value="">Nenhuma (somente administrador global)</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>
                            {{ $organization->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="plan_id" class="mb-2 block text-sm font-bold text-gray-700">Plano opcional</label>
                <select id="plan_id" name="plan_id"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                    <option value="">Sem plano individual</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div></div>
            <div>
                <label for="password" class="mb-2 block text-sm font-bold text-gray-700">Senha provisória</label>
                <input id="password" name="password" type="password" required minlength="12"
                    autocomplete="new-password"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Use pelo menos 12 caracteres.</p>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-bold text-gray-700">
                    Confirme a senha
                </label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12"
                    autocomplete="new-password"
                    class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
            </div>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.users.index') }}"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-duo-border px-5 py-3 font-bold text-gray-600 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-extrabold text-white hover:bg-primary-dark">
                <span class="material-symbols-outlined" aria-hidden="true">person_add</span>
                Criar usuário
            </button>
        </div>
    </form>
</x-app-layout>
