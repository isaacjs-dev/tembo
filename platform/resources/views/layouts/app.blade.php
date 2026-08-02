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

<body class="bg-background-light text-duo-text">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:font-bold focus:text-secondary focus:shadow-float">
        Pular para o conteúdo principal
    </a>

    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

        <!-- Mobile Overlay -->
        <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-black/40 md:hidden"
            @click="sidebarOpen = false" aria-hidden="true">
        </div>

        <!-- Sidebar -->
        <aside id="primary-sidebar" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            :aria-hidden="(!sidebarOpen && window.innerWidth < 768).toString()"
            :inert="!sidebarOpen && window.innerWidth < 768" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-duo-border p-4 flex flex-col gap-6 shadow-card
                   transform transition-transform duration-200 ease-out
                   md:translate-x-0 md:static md:flex">

            <!-- Logo -->
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2">
                    <x-application-logo class="size-10 shrink-0 text-primary shadow-tactile rounded-[14px]" />
                    <span class="brand-wordmark text-xl">Tembo</span>
                </a>
                <button type="button" class="md:hidden p-2 text-gray-500 hover:text-duo-heading"
                    @click="sidebarOpen = false" aria-label="Fechar menu">
                    <span aria-hidden="true" class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Navigation -->
            <nav aria-label="Navegação principal" class="flex flex-col gap-1.5 flex-1 overflow-y-auto">
                {{-- Painel — dinâmico por perfil --}}
                @role('global_admin')
                <a class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    href="{{ route('admin.dashboard') }}">
                    <span class="material-symbols-outlined">grid_view</span>
                    <span>Painel</span>
                </a>
                @else
                <a class="sidebar-item {{ request()->routeIs('dashboard') || request()->routeIs('institution.dashboard') || request()->routeIs('student.dashboard') || request()->routeIs('guardian.dashboard') ? 'active' : '' }}"
                    href="{{ match (auth()->user()->type) {
                        'student' => route('student.dashboard'),
                        'guardian' => route('guardian.dashboard'),
                        'institution_admin' => route('institution.dashboard'),
                        default => route('dashboard'),
                    } }}">
                    <span class="material-symbols-outlined">grid_view</span>
                    <span>Painel</span>
                </a>
                @endrole

                @if(auth()->user()->type === 'student')
                    <a class="sidebar-item {{ request()->routeIs('student.learning.*') ? 'active' : '' }}"
                        href="{{ route('student.learning.index') }}">
                        <span class="material-symbols-outlined" aria-hidden="true">auto_stories</span>
                        <span>Estudar e revisar</span>
                    </a>
                @endif

                {{-- ══════════════════════════════════════════ --}}
                {{-- GLOBAL ADMIN (SaaS) --}}
                {{-- ══════════════════════════════════════════ --}}
                @role('global_admin')
                <div class="mt-4">
                    <h3 class="sidebar-section-title">Administração</h3>
                    <div class="space-y-0.5">
                        <a href="{{ route('admin.plans.index') }}"
                            class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">diamond</span> Planos e SaaS
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}"
                            class="sidebar-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">history</span> Logs de Auditoria
                        </a>
                        <a href="{{ route('admin.users.index') }}"
                            class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">group</span> Usuários
                        </a>
                        <a href="{{ route('admin.trash.index') }}"
                            class="sidebar-link {{ request()->routeIs('admin.trash.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">delete_sweep</span> Lixeira
                        </a>
                        <a href="{{ route('admin.logs') }}"
                            class="sidebar-link {{ request()->routeIs('admin.logs*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">history</span> Logs
                        </a>
                        <a href="{{ route('admin.config.index') }}"
                            class="sidebar-link {{ request()->routeIs('admin.config.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">tune</span> Config OMR
                        </a>
                    </div>
                </div>
                @endrole

                {{-- ══════════════════════════════════════════ --}}
                {{-- INSTITUTION ADMIN --}}
                {{-- ══════════════════════════════════════════ --}}
                @role('institution_admin')
                <div class="mt-4">
                    <h3 class="sidebar-section-title">Operação</h3>
                    <div class="space-y-0.5">
                        <a href="{{ route('institution.teachers.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.teachers.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">person_add</span> Professores
                        </a>
                        <a href="{{ route('institution.classes.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.classes.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">groups</span> Turmas
                        </a>
                        <a href="{{ route('institution.students.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.students.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">school</span> Alunos
                        </a>
                        <a href="{{ route('institution.guardians.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.guardians.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">family_restroom</span> Responsáveis
                        </a>
                        <a href="{{ route('institution.invites.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.invites.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">mail</span> Convites
                        </a>
                        <a href="{{ route('institution.roles.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.roles.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">badge</span> Cargos
                        </a>
                        <a href="{{ route('institution.reports') }}"
                            class="sidebar-link {{ request()->routeIs('institution.reports') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">monitoring</span> Relatórios
                        </a>
                        <a href="{{ route('institution.omr.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.omr.*') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">document_scanner</span> Leitura OMR
                        </a>
                    </div>
                </div>
                <div class="mt-4">
                    <h3 class="sidebar-section-title">Configurações</h3>
                    <div class="space-y-0.5">
                        <a href="{{ route('institution.settings') }}"
                            class="sidebar-link {{ request()->routeIs('institution.settings') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">domain</span> Instituição
                        </a>
                        <a href="{{ route('institution.billing.index') }}"
                            class="sidebar-link {{ request()->routeIs('institution.billing.index') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">credit_card</span> Assinatura
                        </a>
                    </div>
                </div>
                @endrole

                {{-- ══════════════════════════════════════════ --}}
                {{-- ACADÊMICO (Teacher + Institution Admin) --}}
                {{-- ══════════════════════════════════════════ --}}
                @if(in_array(auth()->user()->type, ['teacher', 'institution_admin'], true))
                    <div class="mt-4">
                        <h3 class="sidebar-section-title">Acadêmico</h3>
                        <div class="space-y-0.5">
                            <a class="sidebar-link {{ request()->routeIs('questions.*') ? 'active' : '' }}"
                                href="{{ route('questions.index') }}">
                                <span class="material-symbols-outlined text-[20px]">import_contacts</span> Questões
                            </a>
                            <a class="sidebar-link {{ request()->routeIs('exams.*') ? 'active' : '' }}"
                                href="{{ route('exams.index') }}">
                                <span class="material-symbols-outlined text-[20px]">assignment</span> Avaliações
                            </a>
                            <a class="sidebar-link {{ request()->routeIs('learning-materials.*') ? 'active' : '' }}"
                                href="{{ route('learning-materials.index') }}">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">auto_stories</span>
                                Materiais
                            </a>
                            @if(auth()->user()->type === 'teacher')
                                <a href="{{ route('institution.reports') }}"
                                    class="sidebar-link {{ request()->routeIs('institution.reports') ? 'active' : '' }}">
                                    <span class="material-symbols-outlined text-[20px]">monitoring</span> Relatórios
                                </a>
                                <a href="{{ route('institution.omr.index') }}"
                                    class="sidebar-link {{ request()->routeIs('institution.omr.*') ? 'active' : '' }}">
                                    <span class="material-symbols-outlined text-[20px]">document_scanner</span> Leitura OMR
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                @php
                    $navigationOrganization = auth()->user()->organization;
                    $canUseInstitutionTrash = auth()->user()->type !== 'global_admin'
                        && $navigationOrganization
                        && (
                            (auth()->user()->type === 'institution_admin' && $navigationOrganization->can_access_trash)
                            || in_array(auth()->id(), $navigationOrganization->trash_access_users ?? [], true)
                        );
                    $canUseInstitutionLogs = auth()->user()->type !== 'global_admin'
                        && $navigationOrganization
                        && (
                            (auth()->user()->type === 'institution_admin' && $navigationOrganization->can_access_logs)
                            || in_array(auth()->id(), $navigationOrganization->logs_access_users ?? [], true)
                        );
                @endphp
                @if($canUseInstitutionTrash || $canUseInstitutionLogs)
                    <div class="mt-4">
                        <h3 class="sidebar-section-title">Governança</h3>
                        <div class="space-y-0.5">
                            @if($canUseInstitutionTrash)
                                <a href="{{ route('institution.trash.index') }}"
                                    class="sidebar-link {{ request()->routeIs('institution.trash.*') ? 'active' : '' }}">
                                    <span class="material-symbols-outlined text-[20px]">delete_sweep</span> Lixeira
                                </a>
                            @endif
                            @if($canUseInstitutionLogs)
                                <a href="{{ route('institution.logs') }}"
                                    class="sidebar-link {{ request()->routeIs('institution.logs') ? 'active' : '' }}">
                                    <span class="material-symbols-outlined text-[20px]">history</span> Auditoria
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ══════════════════════════════════════════ --}}
                {{-- CONVITES RECEBIDOS (qualquer autenticado) --}}
                {{-- ══════════════════════════════════════════ --}}
                @auth
                <div class="mt-4">
                    <h3 class="sidebar-section-title">Vínculos</h3>
                    <div class="space-y-0.5">
                        <a href="{{ route('institution.invites.received') }}"
                            class="sidebar-link {{ request()->routeIs('institution.invites.received') ? 'active' : '' }}">
                            <span class="material-symbols-outlined text-[20px]">mark_email_unread</span> Convites Recebidos
                        </a>
                    </div>
                </div>
                @endauth

                {{-- Minha Conta --}}
                <a class="sidebar-item mt-auto {{ request()->routeIs('profile.edit') ? 'active' : '' }}"
                    href="{{ route('profile.edit') }}">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Minha Conta</span>
                </a>
            </nav>

            <!-- Sidebar Footer Promo -->
            <div class="bg-primary/5 border-2 border-primary/15 rounded-xl p-4">
                <p class="text-primary-dark font-bold text-sm">Tembo</p>
                <p class="text-xs text-gray-500 mt-1">Transforme avaliações em resultados.</p>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex flex-col flex-1 overflow-hidden">

            <!-- Topbar -->
            <header
                class="flex items-center justify-between h-16 px-4 md:px-8 bg-white/95 backdrop-blur border-b border-duo-border sticky top-0 z-30">
                <!-- Mobile Hamburger -->
                <button type="button" aria-label="Abrir menu" aria-controls="primary-sidebar"
                    :aria-expanded="sidebarOpen.toString()"
                    class="md:hidden p-2 text-gray-500 hover:text-duo-heading rounded-xl hover:bg-gray-100 transition-colors"
                    @click="sidebarOpen = true">
                    <span aria-hidden="true" class="material-symbols-outlined !text-2xl">menu</span>
                </button>

                <div class="flex-1"></div>

                <div class="flex items-center gap-4">
                    <button type="button" disabled title="Central de notificações em breve"
                        aria-label="Notificações — recurso em breve"
                        class="relative p-2 text-gray-500 rounded-xl">
                        <span aria-hidden="true"
                            class="material-symbols-outlined !text-2xl">notifications</span>
                    </button>
                    <div class="h-8 w-px bg-duo-border hidden sm:block"></div>

                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                        @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            class="group flex items-center gap-3 rounded-xl p-1.5 text-left hover:bg-gray-50">
                            <span class="text-right hidden sm:block">
                                <span class="block text-sm font-bold text-duo-heading leading-none">{{ Auth::user()->name }}</span>
                                <span class="block text-xs text-gray-500 font-medium capitalize mt-0.5">
                                    {{ str_replace('_', ' ', Auth::user()->type) }}
                                </span>
                            </span>
                            <span
                                class="size-10 rounded-full border-2 border-primary p-0.5 group-hover:scale-105 transition-transform">
                                <span
                                    class="rounded-full bg-primary/10 size-full flex items-center justify-center text-primary font-bold text-sm">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </span>
                            </span>
                            <span class="sr-only">Abrir menu da conta</span>
                        </button>

                        <!-- Dropdown -->
                        <div x-cloak x-show="open" role="menu" x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-float border-2 border-duo-border py-1 z-50">
                            <a role="menuitem" href="{{ route('profile.edit') }}"
                                class="block px-4 py-2.5 text-sm text-duo-text font-bold hover:bg-gray-50 transition-colors">
                                Minha Conta
                            </a>
                            <div class="border-t border-duo-border my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button role="menuitem" type="submit"
                                    class="block w-full text-left px-4 py-2.5 text-sm text-red-500 font-bold hover:bg-red-50 transition-colors">
                                    Sair
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main id="main-content" tabindex="-1" class="flex-1 overflow-y-auto p-4 md:p-8">
                <!-- Flash Messages -->
                @if (session('status'))
                    <div role="status" aria-live="polite" class="alert alert-success mb-6 animate-fade-in">
                        <span aria-hidden="true" class="material-symbols-outlined">check_circle</span>
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any() && !isset($hideGlobalErrors))
                    <div role="alert" aria-live="assertive" class="error-list mb-6 animate-fade-in">
                        @foreach ($errors->all() as $error)
                            <div class="error-list-item">
                                <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                                <span>{{ $error }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Subscription Warning -->
                @if(auth()->check() && auth()->user()->type !== 'global_admin' && auth()->user()->organization)
                    @php $sub = auth()->user()->organization->subscription; @endphp
                    @if(!$sub || $sub->status !== 'active')
                        <div class="alert alert-warning mb-6 justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined">warning</span>
                                <span>Sua instituição está sem plano ativo. Recursos limitados.</span>
                            </div>
                            @role('institution_admin')
                            <a href="{{ route('institution.billing.index') }}"
                                class="text-sm uppercase tracking-wider underline hover:text-yellow-600 whitespace-nowrap">Assinar</a>
                            @endrole
                        </div>
                    @endif
                @endif

                <!-- Page Heading -->
                @if (isset($header))
                    <div class="mb-6">{{ $header }}</div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>
