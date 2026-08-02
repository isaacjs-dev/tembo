<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <nav class="breadcrumb">
                    <a href="{{ route('institution.dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="current">Configurações</span>
                </nav>
                <h1 class="page-title">Configurações da Instituição</h1>
                <p class="text-gray-500 font-medium mt-1 text-sm">Personalize os dados e a aparência da sua plataforma.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="card p-8 mb-12 max-w-3xl">

        @if (session('status'))
            <div class="alert alert-success mb-6" role="status" aria-live="polite">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-6" role="alert">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('institution.settings.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                <label for="name" class="input-label">Nome da Instituição</label>
                <input type="text" id="name" name="name" value="{{ old('name', $organization->name) }}" required
                    class="input-field">
            </div>

            <div class="space-y-2">
                <label for="subdomain" class="input-label">Subdomínio (Opcional)</label>
                <div class="flex items-center">
                    <input type="text" id="subdomain" name="subdomain"
                        value="{{ old('subdomain', $organization->subdomain) }}" class="input-field !rounded-r-none"
                        placeholder="minhaescola">
                    <div
                        class="px-4 py-3 bg-gray-100 border-y-2 border-r-2 border-duo-border rounded-r-xl text-gray-500 font-bold text-sm">
                        .tembo.aracruz.org
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label for="primary_color" class="input-label">Cor Principal (Hexadecimal)</label>
                <div class="flex items-center gap-3">
                    <input type="color" id="primary_color" name="primary_color"
                        value="{{ old('primary_color', $organization->settings['primary_color'] ?? '#1d78a6') }}"
                        class="h-12 w-12 rounded cursor-pointer border-0 bg-transparent p-0">
                    <input type="text"
                        value="{{ old('primary_color', $organization->settings['primary_color'] ?? '#1d78a6') }}"
                        aria-label="Valor hexadecimal da cor principal"
                        class="input-field uppercase font-mono" readonly>
                </div>
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end">
                <button type="submit" class="btn-primary text-xs uppercase tracking-wider">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
