<x-guest-layout>
    <div class="text-center mb-6">
        <h2 class="text-xl font-extrabold text-duo-heading">Crie sua conta</h2>
        <p class="text-sm text-gray-500 mt-1">Crie o espaço da sua instituição e comece gratuitamente.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- Name -->
        <fieldset>
            <legend class="input-label">Como você vai usar o Tembo?</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="rounded-xl border-2 border-duo-border p-3 text-sm font-bold text-duo-heading">
                    <input type="radio" name="account_type" value="personal" class="mr-2 text-primary"
                        @checked(old('account_type', 'personal') === 'personal')>
                    Professor independente
                </label>
                <label class="rounded-xl border-2 border-duo-border p-3 text-sm font-bold text-duo-heading">
                    <input type="radio" name="account_type" value="institution" class="mr-2 text-primary"
                        @checked(old('account_type') === 'institution')>
                    Instituição
                </label>
            </div>
            <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
        </fieldset>

        <div>
            <label for="name" class="input-label">Nome Completo</label>
            <div class="input-icon-wrapper">
                <div class="input-icon">
                    <span class="material-symbols-outlined text-xl">person</span>
                </div>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                    autocomplete="name" class="input-field" placeholder="Seu nome">
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <label for="organization_name" class="input-label">Instituição ou projeto</label>
            <div class="input-icon-wrapper">
                <div class="input-icon">
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">domain</span>
                </div>
                <input id="organization_name" type="text" name="organization_name"
                    value="{{ old('organization_name') }}" autocomplete="organization"
                    class="input-field" placeholder="Ex.: Escola Horizonte">
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Para professor independente, pode ficar em branco; criaremos um espaço pessoal renomeável.
            </p>
            <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="input-label">E-mail</label>
            <div class="input-icon-wrapper">
                <div class="input-icon">
                    <span class="material-symbols-outlined text-xl">mail</span>
                </div>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                    class="input-field" placeholder="seu@email.com">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="input-label">Senha</label>
            <div class="input-icon-wrapper">
                <div class="input-icon">
                    <span class="material-symbols-outlined text-xl">lock</span>
                </div>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                    class="input-field" placeholder="Mínimo 8 caracteres">
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="input-label">Confirmar Senha</label>
            <div class="input-icon-wrapper">
                <div class="input-icon">
                    <span class="material-symbols-outlined text-xl">lock</span>
                </div>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                    autocomplete="new-password" class="input-field" placeholder="Repita a senha">
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="pt-2">
            <button class="btn-primary w-full text-sm uppercase tracking-wider" type="submit">
                Criar Conta
            </button>
        </div>

        <p class="text-center text-sm text-gray-500 mt-4">
            Já tem uma conta?
            <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">Entrar</a>
        </p>
    </form>
</x-guest-layout>
