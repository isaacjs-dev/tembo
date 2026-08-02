<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tembo — Entrar</title>
    <meta name="theme-color" content="#1d78a6">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col items-center justify-center p-4"
    style="background-color: #f5fafd; background-image: radial-gradient(#c9e5f2 0.8px, transparent 0.8px); background-size: 24px 24px;">

    <div class="w-full max-w-[440px] flex flex-col items-center animate-fade-in">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8 text-center">
            <a href="/" class="flex items-center gap-3 mb-4">
                <x-application-logo class="size-12 text-primary shadow-tactile rounded-[14px]" />
                <h1 class="brand-wordmark text-3xl">Tembo</h1>
            </a>
            <h2 class="text-xl font-bold text-duo-heading leading-tight max-w-[320px]">
                Bem-vindo de volta! Vamos ensinar algo novo hoje?
            </h2>
        </div>

        <!-- Login Card -->
        <div class="w-full card p-8">
            @if ($errors->any())
                <div role="alert" aria-live="assertive" class="error-list mb-6">
                    @foreach ($errors->all() as $error)
                        <div class="error-list-item">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form class="space-y-5" method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div class="space-y-2">
                    <label for="email" class="input-label">E-mail</label>
                    <div class="input-icon-wrapper">
                        <div class="input-icon">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">mail</span>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                            autocomplete="username" class="input-field" placeholder="seu@email.com" />
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <label for="password" class="input-label">Senha</label>
                    <div class="input-icon-wrapper">
                        <div class="input-icon">
                            <span aria-hidden="true" class="material-symbols-outlined text-xl">lock</span>
                        </div>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="input-field" placeholder="••••••••" />
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <label for="remember_me" class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <input id="remember_me" type="checkbox" name="remember" class="sr-only peer" />
                            <div
                                class="w-5 h-5 bg-white border-2 border-gray-400 rounded-md peer-checked:bg-primary peer-checked:border-primary peer-focus-visible:ring-4 peer-focus-visible:ring-secondary/30 transition-all">
                            </div>
                            <span aria-hidden="true"
                                class="material-symbols-outlined absolute top-0 left-0 text-white text-sm opacity-0 peer-checked:opacity-100 font-bold">check</span>
                        </div>
                        <span
                            class="ml-3 text-sm font-semibold text-gray-500 group-hover:text-duo-heading transition-colors">Lembrar
                            de mim</span>
                    </label>
                </div>

                <!-- Submit -->
                <div class="pt-2">
                    <button class="btn-primary w-full btn-lg text-sm uppercase tracking-wider" type="submit">
                        Entrar
                    </button>
                </div>
            </form>

            <!-- Links -->
            <div class="mt-8 flex flex-col gap-4 items-center">
                @if (Route::has('password.request'))
                    <a class="text-primary font-bold text-sm hover:underline decoration-2 underline-offset-4"
                        href="{{ route('password.request') }}">
                        Esqueci minha senha
                    </a>
                @endif
                <div class="w-full flex items-center gap-3">
                    <div class="h-px flex-1 bg-duo-border"></div>
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">ou</span>
                    <div class="h-px flex-1 bg-duo-border"></div>
                </div>
                <a href="{{ route('register') }}" class="btn-secondary w-full text-sm text-center">
                    <span aria-hidden="true" class="material-symbols-outlined text-xl">person_add</span>
                    Criar uma conta
                </a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-10 text-center">
            <p class="text-gray-500 text-xs font-medium">
                © {{ date('Y') }} Tembo. Todos os direitos reservados.
            </p>
        </footer>
    </div>
</body>

</html>
