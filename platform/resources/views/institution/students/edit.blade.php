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
                <div class="text-gray-800 font-bold text-sm">Nome da conta</div>
                <p class="px-4 py-3 bg-gray-50 border-2 border-duo-border rounded-xl text-gray-700">{{ $student->name }}</p>
            </div>

            <div class="space-y-2">
                <div class="text-gray-800 font-bold text-sm">E-mail da conta</div>
                <p class="px-4 py-3 bg-gray-50 border-2 border-duo-border rounded-xl text-gray-700">{{ $student->email }}</p>
                <p class="text-xs text-gray-500">Nome, e-mail e senha só podem ser alterados pelo titular da conta.</p>
            </div>

            <div class="space-y-2">
                <label for="registration_number" class="text-gray-800 font-bold text-sm">Matrícula (Opcional)</label>
                <input type="text" id="registration_number" name="registration_number"
                    value="{{ old('registration_number', $student->studentProfiles->first()?->registration_number ?? '') }}"
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium"
                    placeholder="Matrícula do aluno">
            </div>

            <div class="space-y-2">
                <label for="status" class="text-gray-800 font-bold text-sm">Status do vínculo institucional</label>
                <select id="status" name="status"
                    class="block w-full px-4 py-3 bg-background-light border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">
                    <option value="active" {{ (old('status', $membershipStatus) == 'active') ? 'selected' : '' }}>Ativo
                    </option>
                    <option value="inactive" {{ (old('status', $membershipStatus) == 'inactive') ? 'selected' : '' }}>
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
        <div class="mt-8 flex items-center justify-between p-6 {{ $membershipStatus === 'active' ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200' }} border-2 rounded-xl">
            <div>
                <h3 class="{{ $membershipStatus === 'active' ? 'text-red-700' : 'text-green-700' }} font-bold text-lg">
                    {{ $membershipStatus === 'active' ? 'Desativar vínculo' : 'Reativar vínculo' }}
                </h3>
                <p class="{{ $membershipStatus === 'active' ? 'text-red-600' : 'text-green-700' }} text-sm mt-1">
                    A ação altera somente o vínculo com esta instituição e preserva a conta global.
                </p>
            </div>
            <form action="{{ route('institution.students.destroy', $student->id) }}" method="POST"
                onsubmit="return confirm('{{ $membershipStatus === 'active' ? 'Desativar o vínculo deste aluno?' : 'Reativar o vínculo deste aluno?' }}');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="px-6 py-3 {{ $membershipStatus === 'active' ? 'bg-red-600 hover:bg-red-700 shadow-[0_4px_0_0_#991b1b]' : 'bg-green-600 hover:bg-green-700 shadow-[0_4px_0_0_#166534]' }} text-white font-bold rounded-xl active:shadow-none active:translate-y-1 transition-all">
                    {{ $membershipStatus === 'active' ? 'Desativar vínculo' : 'Reativar vínculo' }}
                </button>
            </form>
        </div>

    </div>
</x-app-layout>
