<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors"
                        href="{{ route('institution.students.index') }}">Alunos</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Editar Aluno</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Editar Aluno</h1>
            </div>
            <a href="{{ route('institution.students.index') }}"
                class="duo-button-secondary px-6 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                Voltar
            </a>
        </div>
    </x-slot>

    <div class="bg-white rounded-2xl border-2 border-duo-border p-8 mb-12 max-w-3xl">

        @if ($errors->any())
            <div
                class="mb-6 flex flex-col gap-1 p-3 bg-error-soft border border-red-200 rounded-lg text-error-text text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">error</span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('institution.students.update', $student->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-2">
                <label for="name" class="text-gray-800 font-bold text-sm">Nome Completo</label>
                <input type="text" id="name" name="name" value="{{ old('name', $student->name) }}" required
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
            </div>

            <div class="space-y-2">
                <label for="email" class="text-gray-800 font-bold text-sm">E-mail</label>
                <div class="relative group">
                    <div
                        class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">mail</span>
                    </div>
                    <input type="email" id="email" name="email" value="{{ old('email', $student->email) }}" required
                        class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                </div>
            </div>

            <div class="space-y-2">
                <label for="registration_number" class="text-gray-800 font-bold text-sm">Matrícula (Opcional)</label>
                <input type="text" id="registration_number" name="registration_number"
                    value="{{ old('registration_number', $student->studentProfile->registration_number ?? '') }}"
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                    placeholder="Matrícula do aluno">
            </div>

            <div class="space-y-2">
                <label for="password" class="text-gray-800 font-bold text-sm">Nova Senha (deixe em branco para manter a
                    atual)</label>
                <div class="relative group">
                    <div
                        class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <span aria-hidden="true" class="material-symbols-outlined text-xl">lock</span>
                    </div>
                    <input type="password" id="password" name="password"
                        class="block w-full pl-11 pr-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                        placeholder="Mínimo 8 caracteres (opcional)">
                </div>
            </div>

            <div class="space-y-2">
                <label for="status" class="text-gray-800 font-bold text-sm">Status</label>
                <select id="status" name="status"
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                    <option value="active" {{ (old('status', $student->status) == 'active') ? 'selected' : '' }}>Ativo
                    </option>
                    <option value="inactive" {{ (old('status', $student->status) == 'inactive') ? 'selected' : '' }}>
                        Inativo</option>
                </select>
            </div>

            <div class="space-y-2">
                <label for="school_classes" class="text-gray-800 font-bold text-sm">Turmas</label>
                <select id="school_classes" name="school_classes[]" multiple
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                    @php
                        $selectedClasses = $student->schoolClasses->pluck('id')->toArray();
                    @endphp
                    @foreach(\App\Models\SchoolClass::where('organization_id', auth()->user()->organization_id)->get() as $schoolClass)
                        <option value="{{ $schoolClass->id }}" {{ in_array($schoolClass->id, $selectedClasses) ? 'selected' : '' }}>{{ $schoolClass->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-6 border-t-2 border-duo-border flex justify-end gap-4">
                <button type="submit"
                    class="duo-button-primary px-8 py-4 rounded-xl font-extrabold text-sm uppercase tracking-wider">
                    Atualizar Aluno
                </button>
            </div>
        </form>

        <hr class="my-8 border-duo-border border-2">
        <div class="mt-8 flex items-center justify-between p-6 bg-red-50 border-2 border-red-200 rounded-xl">
            <div>
                <h3 class="text-red-700 font-bold text-lg">Remover Aluno</h3>
                <p class="text-red-600 text-sm mt-1">Desativar e remover este aluno do sistema. (Soft Delete)</p>
            </div>
            <form action="{{ route('institution.students.destroy', $student->id) }}" method="POST"
                onsubmit="return confirm('Tem certeza que deseja inativar/remover este aluno?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="px-6 py-3 bg-red-600 text-white font-bold rounded-xl shadow-[0_4px_0_0_#991b1b] hover:bg-red-700 active:shadow-none active:translate-y-1 transition-all">
                    Remover Aluno
                </button>
            </form>
        </div>

    </div>
</x-app-layout>