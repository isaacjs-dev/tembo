<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">

    <title>{{ config('app.name', 'Tembo') }}</title>
    <meta name="theme-color" content="#1d78a6">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col items-center justify-center p-4"
    style="background-color: #f5fafd; background-image: radial-gradient(#c9e5f2 0.8px, transparent 0.8px); background-size: 24px 24px;">
    <div class="w-full max-w-md flex flex-col items-center">
        <!-- Logo -->
        <div class="flex items-center gap-3 mb-8">
            <a href="/" class="flex items-center gap-3">
                <x-application-logo class="size-11 text-primary shadow-tactile rounded-[14px]" />
                <span class="brand-wordmark text-2xl">Tembo</span>
            </a>
        </div>

        <!-- Content Card -->
        <div class="w-full card p-8">
            {{ $slot }}
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center">
            <p class="text-gray-500 text-xs font-medium">
                © {{ date('Y') }} Tembo. Todos os direitos reservados.
            </p>
        </footer>
    </div>
</body>

</html>
