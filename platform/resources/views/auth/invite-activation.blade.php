<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-extrabold text-duo-heading">Convite para o Tembo</h1>
        <p class="mt-2 text-sm text-gray-500">
            {{ $invite->organization?->name ?? 'Um workspace' }} convidou você como
            <strong>{{ ucfirst($invite->target_role) }}</strong>.
        </p>
    </div>

    @if ($existingAccount)
        <div class="space-y-4">
            <p class="rounded-xl border-2 border-duo-border bg-background-light p-4 text-sm text-gray-600">
                Este e-mail já possui conta. Entre com sua senha e aceite o convite na área de convites recebidos.
            </p>
            <a href="{{ route('login') }}" class="btn-primary block w-full text-center text-sm uppercase tracking-wider">
                Entrar na conta
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('invite.activation.store', $invite->token) }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="input-label">Nome completo</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                    autocomplete="name" class="input-field" placeholder="Seu nome">
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="input-label">E-mail convidado</label>
                <input id="email" type="email" value="{{ $invite->invitee_email }}" disabled class="input-field bg-gray-50">
            </div>

            <div>
                <label for="password" class="input-label">Crie sua senha</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    class="input-field">
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="input-label">Confirme sua senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    autocomplete="new-password" class="input-field">
            </div>

            <button type="submit" class="btn-primary w-full text-sm uppercase tracking-wider">
                Ativar minha conta
            </button>
        </form>
    @endif
</x-guest-layout>
