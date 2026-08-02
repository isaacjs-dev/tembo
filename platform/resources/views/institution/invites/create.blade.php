<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm font-bold text-gray-400 mb-2">
                    <a class="hover:text-primary transition-colors" href="{{ route('dashboard') }}">Dashboard</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <a class="hover:text-primary transition-colors"
                        href="{{ route('institution.invites.index') }}">Convites</a>
                    <span aria-hidden="true" class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="text-primary">Enviar Convite</span>
                </nav>
                <h1 class="text-4xl font-extrabold text-duo-heading dark:text-white tracking-tight">Enviar Convite</h1>
                <p class="text-gray-400 mt-1">Convide um novo membro para a sua instituição.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('institution.invites.index') }}"
                    class="flex items-center gap-2 px-6 py-4 bg-white border-2 border-duo-border text-gray-600 font-extrabold rounded-xl transition-all uppercase tracking-wider text-sm whitespace-nowrap hover:border-gray-400">
                    Cancelar
                </a>
                <button type="submit" form="invite-form"
                    class="flex items-center gap-2 px-6 py-4 bg-primary text-white font-extrabold rounded-xl duo-button-shadow transition-all uppercase tracking-wider text-sm whitespace-nowrap duo-button-primary">
                    <span aria-hidden="true" class="material-symbols-outlined">send</span>
                    Enviar Convite
                </button>
            </div>
        </div>
    </x-slot>

    @if($errors->any())
        <div
            class="flex items-center gap-3 bg-red-50 border-2 border-red-200 text-red-700 px-5 py-3 rounded-xl mb-6 font-bold text-sm">
            <span aria-hidden="true" class="material-symbols-outlined text-red-500">error</span>
            {{ $errors->first() }}
        </div>
    @endif

    <form id="invite-form" action="{{ route('institution.invites.store') }}" method="POST">
        @csrf

        <div class="bg-white rounded-2xl border-2 border-duo-border overflow-hidden mb-6">
            <div class="px-6 py-4 border-b-2 border-duo-border bg-background-light">
                <h2 class="text-base font-extrabold text-gray-700 flex items-center gap-2">
                    <span aria-hidden="true" class="material-symbols-outlined text-primary">person_add</span> Dados do Convite
                </h2>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="invite-email" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">E-mail do
                            Convidado *</label>
                        <input id="invite-email" type="email" name="email" value="{{ old('email') }}" required
                            placeholder="professor@example.com"
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                    </div>
                    <div>
                        <label for="invite-role" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Perfil
                            *</label>
                        <select id="invite-role" name="target_role" required
                            class="w-full px-4 py-3 bg-white border-2 border-duo-border rounded-xl text-sm font-medium focus:border-primary focus:ring-0 transition-colors">
                            <option value="teacher" {{ old('target_role') == 'teacher' ? 'selected' : '' }}>Professor
                            </option>
                            <option value="admin" {{ old('target_role') == 'admin' ? 'selected' : '' }}>Administrador
                            </option>
                            <option value="student" {{ old('target_role') == 'student' ? 'selected' : '' }}>Aluno</option>
                        </select>
                    </div>
                </div>

                <div class="bg-background-light border-2 border-duo-border rounded-xl p-5">
                    <div class="flex items-start gap-3">
                        <span aria-hidden="true" class="material-symbols-outlined text-blue-400 text-[20px] mt-0.5">info</span>
                        <div>
                            <p class="text-sm font-bold text-gray-700">Como funciona?</p>
                            <ul class="text-xs text-gray-500 mt-1 space-y-1">
                                <li>• O convidado receberá um e-mail com link para aceitar o vínculo</li>
                                <li>• O convite expira em <strong>7 dias</strong></li>
                                <li>• Se o e-mail já tiver conta, basta aceitar. Caso contrário, será criada uma conta
                                    nova</li>
                                <li>• Você pode cancelar convites pendentes a qualquer momento</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
