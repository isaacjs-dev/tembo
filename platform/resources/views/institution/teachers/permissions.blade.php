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
                    <span class="text-primary">Permissões</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Permissões de Acesso
                </h1>
                <p class="text-gray-500 font-medium mt-1">Gerenciando permissões para
                    <strong>{{ $teacher->name }}</strong></p>
            </div>
            <a href="{{ route('institution.teachers.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12 max-w-4xl">
        <form action="{{ route('institution.teachers.permissions.update', $teacher->id) }}" method="POST">
            @csrf
            @method('PUT')

            @php
                $availablePermissions = \Spatie\Permission\Models\Permission::all();
                $userPermissions = $teacher->getAllPermissions()->pluck('name')->toArray();
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach($availablePermissions as $permission)
                    <label
                        class="flex items-start gap-3 p-4 border-2 border-duo-border rounded-xl cursor-pointer hover:border-primary transition-colors">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                            class="mt-1 size-5 rounded text-primary focus:ring-primary focus:ring-opacity-50 border-gray-300"
                            {{ in_array($permission->name, $userPermissions) ? 'checked' : '' }}>
                        <div class="flex flex-col">
                            <span class="font-bold text-gray-800 text-sm">{{ $permission->name }}</span>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end gap-4">
                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    Salvar Permissões
                </button>
            </div>
        </form>
    </div>
</x-app-layout>