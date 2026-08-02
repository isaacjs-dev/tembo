<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Tembo') }} — Plataforma de Avaliações</title>
    <meta name="theme-color" content="#1d78a6">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="description"
        content="Plataforma SaaS completa para criar, aplicar e corrigir avaliações com automatização, gestão de turmas e relatórios em tempo real.">

    <!-- Scripts / Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased bg-white text-duo-text">

    <!-- Navigation -->
    <header class="bg-white/80 backdrop-blur-lg border-b border-duo-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <a href="/" class="flex items-center gap-2.5">
                    <x-application-logo class="size-9 text-primary shadow-tactile rounded-xl" />
                    <span class="brand-wordmark text-xl">Tembo</span>
                </a>
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#funcionalidades"
                        class="text-gray-500 hover:text-duo-heading font-semibold transition-colors text-sm">Funcionalidades</a>
                    <a href="#como-funciona"
                        class="text-gray-500 hover:text-duo-heading font-semibold transition-colors text-sm">Como
                        Funciona</a>
                    <a href="#planos"
                        class="text-gray-500 hover:text-duo-heading font-semibold transition-colors text-sm">Planos</a>
                </nav>
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}"
                        class="text-duo-heading hover:text-primary font-bold transition-colors text-sm hidden sm:inline-flex">Entrar</a>
                    <a href="{{ route('register') }}" class="btn-primary btn-sm text-xs uppercase tracking-wider">
                        Criar Conta
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-secondary/5"></div>
        <div class="absolute top-20 right-10 w-72 h-72 bg-primary/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-secondary/10 rounded-full blur-3xl"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-24 md:pt-28 md:pb-32">
            <div class="text-center flex flex-col items-center">
                <div
                    class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary-dark font-bold text-sm mb-8 border border-primary/20">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                    </span>
                    Plataforma 100% online
                </div>

                <h1
                    class="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight text-duo-heading mb-6 leading-[1.1] max-w-4xl">
                    Crie, aplique e corrija
                    <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">avaliações</span>
                    com facilidade
                </h1>

                <p class="text-lg md:text-xl text-gray-500 mb-10 max-w-2xl leading-relaxed">
                    Plataforma completa para instituições, professores e alunos. Gerencie turmas, crie provas
                    rapidamente e obtenha correções automáticas em tempo real.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('register') }}" class="btn-primary btn-lg text-sm uppercase tracking-wider">
                        <span class="material-symbols-outlined text-xl">rocket_launch</span>
                        Comece Gratuitamente
                    </a>
                    <a href="#como-funciona" class="btn-secondary btn-lg text-sm uppercase tracking-wider">
                        <span class="material-symbols-outlined text-xl">play_circle</span>
                        Como Funciona
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-6 max-w-3xl mx-auto">
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-duo-heading">100+</p>
                    <p class="text-sm font-medium text-gray-500 mt-1">Instituições</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-duo-heading">5k+</p>
                    <p class="text-sm font-medium text-gray-500 mt-1">Alunos Ativos</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-duo-heading">10k+</p>
                    <p class="text-sm font-medium text-gray-500 mt-1">Provas Aplicadas</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-duo-heading">99.9%</p>
                    <p class="text-sm font-medium text-gray-500 mt-1">Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="funcionalidades" class="bg-background-light py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="badge badge-primary text-sm mb-4">Funcionalidades</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-duo-heading mb-4">Tudo que sua instituição precisa
                </h2>
                <p class="text-lg text-gray-500 max-w-2xl mx-auto">
                    Desenvolvido com foco na experiência do professor e do aluno para simplificar a rotina escolar.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-primary !text-2xl">import_contacts</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Banco de Questões</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Crie, compartilhe e reutilize questões. Organize
                        seu acervo para montar provas em minutos.</p>
                </div>

                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-green-600 !text-2xl">check_circle</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Correção Automática</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Libere os professores do trabalho manual. Provas
                        objetivas corrigidas instantaneamente.</p>
                </div>

                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-secondary/10 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-secondary !text-2xl">groups</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Gestão de Turmas</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Acompanhe alunos, sincronize classes e tenha
                        relatórios de desempenho precisos.</p>
                </div>

                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-accent/15 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-accent !text-2xl">bar_chart</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Relatórios Detalhados</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Visualize métricas de desempenho por aluno, turma e
                        disciplina para tomar decisões.</p>
                </div>

                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-orange-600 !text-2xl">devices</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Multiplataforma</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Acesse de qualquer dispositivo. Interface otimizada
                        para desktop, tablet e celular.</p>
                </div>

                <div class="card p-8 card-hover group">
                    <div
                        class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-red-500 !text-2xl">shield</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Segurança Total</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Dados protegidos com criptografia. Controle de
                        acesso por perfil e auditoria completa.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section id="como-funciona" class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="badge badge-info text-sm mb-4">Como Funciona</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-duo-heading mb-4">Simples em 3 passos</h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-5 border-2 border-primary/20">
                        <span class="text-2xl font-black text-primary">1</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Cadastre-se</h3>
                    <p class="text-sm text-gray-500">Crie sua conta institucional e configure suas turmas e professores
                        em poucos minutos.</p>
                </div>
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-secondary/10 rounded-2xl flex items-center justify-center mx-auto mb-5 border-2 border-secondary/20">
                        <span class="text-2xl font-black text-secondary">2</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Crie suas Provas</h3>
                    <p class="text-sm text-gray-500">Monte avaliações usando o banco de questões ou crie suas próprias
                        com múltipla escolha, V/F e dissertativas.</p>
                </div>
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-accent/10 rounded-2xl flex items-center justify-center mx-auto mb-5 border-2 border-accent/20">
                        <span class="text-2xl font-black text-accent">3</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-duo-heading mb-2">Veja os Resultados</h3>
                    <p class="text-sm text-gray-500">Correção automática instantânea, relatórios detalhados e exportação
                        simplificada dos resultados.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="planos" class="bg-background-light py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="badge badge-primary text-sm mb-4">Planos</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-duo-heading mb-4">Escolha o plano ideal</h2>
                <p class="text-lg text-gray-500 max-w-2xl mx-auto">Preços transparentes. Cancele a qualquer momento.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto items-start">
                @forelse($plans as $plan)
                    @php
                        $isPopular = $plan->is_popular ?? (strtolower($plan->name) === 'pro');
                        $displayPrice = ($plan->promotional_price && $plan->promo_ends_at && now()->lt($plan->promo_ends_at))
                            ? $plan->promotional_price
                            : ($plan->original_price ?? $plan->price ?? 0);
                    @endphp
                    <div class="card p-8 flex flex-col relative {{ $isPopular ? 'border-primary ring-2 ring-primary/30 shadow-card-hover md:scale-105 z-10' : '' }}">

                        @if($isPopular)
                            <div class="absolute top-0 right-6 transform -translate-y-1/2">
                                <span class="bg-primary text-white text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wide">Popular</span>
                            </div>
                        @endif

                        <h3 class="text-xl font-extrabold text-duo-heading mb-1">{{ $plan->name }}</h3>
                        <div class="flex items-baseline gap-1 mb-6">
                            <span class="text-4xl font-extrabold text-duo-heading">R$ {{ number_format($displayPrice, 2, ',', '.') }}</span>
                            <span class="text-gray-500 font-medium text-sm">/mês</span>
                        </div>

                        @php
                            $planLimitsMap = $plan->planLimits->pluck('limit_value', 'resource_key');
                            $maxClasses = $planLimitsMap->get('max_classes');
                            $maxExams = $planLimitsMap->get('max_exams_active');
                        @endphp
                        <div class="text-sm text-gray-500 mb-6 space-y-1">
                            <p>Turmas: <span class="font-bold text-duo-heading">{{ $maxClasses !== null ? $maxClasses : 'Ilimitado' }}</span></p>
                            <p>Avaliações: <span class="font-bold text-duo-heading">{{ $maxExams !== null ? $maxExams : 'Ilimitado' }}</span></p>
                        </div>

                        <div class="flex-grow">
                            <ul class="space-y-3 mb-8">
                                @foreach($plan->planFeatures->where('enabled', true) as $feature)
                                    <li class="flex items-start gap-3">
                                        <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">check_circle</span>
                                        <span class="text-sm text-gray-600">{{ $feature->feature_key }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <a href="{{ route('register', ['plan' => $plan->slug]) }}"
                            class="{{ $isPopular ? 'btn-primary' : 'btn-secondary' }} w-full text-sm uppercase tracking-wider">
                            Assinar {{ $plan->name }}
                        </a>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 card border-dashed">
                        <span class="material-symbols-outlined text-4xl text-gray-300 mb-2 block">inventory_2</span>
                        <p class="text-gray-500 font-medium">Nenhum plano cadastrado ainda.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gradient-to-br from-primary to-primary-dark">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Pronto para transformar a educação?</h2>
            <p class="text-primary-light text-lg mb-8 max-w-2xl mx-auto">
                Junte-se às instituições e professores que já otimizam o seu tempo com a nossa plataforma.
            </p>
            <a href="{{ route('register') }}"
                class="inline-flex items-center gap-2 bg-white text-primary-dark font-extrabold px-8 py-4 rounded-xl text-lg hover:bg-gray-50 transition-all shadow-lg">
                <span class="material-symbols-outlined">arrow_forward</span>
                Criar Conta Gratuita
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-duo-heading border-t border-gray-800 py-12">
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2.5">
                <x-application-logo class="size-8 text-primary rounded-[10px]" />
                <span class="font-bold text-lg text-white">Tembo</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="{{ route('login') }}"
                    class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Login</a>
                <a href="{{ route('register') }}"
                    class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Cadastro</a>
                <span class="text-gray-500 text-sm">&copy; {{ date('Y') }} Todos os direitos reservados.</span>
            </div>
        </div>
    </footer>

</body>

</html>
