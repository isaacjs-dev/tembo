<x-app-layout>
    <x-slot name="header">
        <div class="mb-8">
            <p class="mb-2 text-sm font-bold text-primary">Gestão institucional</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-duo-heading md:text-4xl">
                Responsáveis e estudantes
            </h1>
            <p class="mt-2 max-w-3xl text-gray-500">
                Crie ou reutilize uma conta de responsável e autorize, de forma explícita, quais
                estudantes ela poderá acompanhar.
            </p>
        </div>
    </x-slot>

    @if (session('status'))
        <div role="status" class="mb-6 rounded-xl border-2 border-green-200 bg-green-50 px-5 py-4 font-bold text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-xl border-2 border-red-200 bg-red-50 px-5 py-4 text-red-800">
            <p class="font-extrabold">Revise os dados informados:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(19rem,0.8fr)_minmax(0,1.4fr)]">
        <section aria-labelledby="new-link-heading" class="rounded-2xl border-2 border-duo-border bg-white p-6">
            <h2 id="new-link-heading" class="text-xl font-extrabold text-duo-heading">Novo vínculo</h2>
            <p class="mt-1 text-sm text-gray-500">
                Se o e-mail já estiver cadastrado como responsável nesta instituição, a conta será reutilizada.
            </p>

            <form method="POST" action="{{ route('institution.guardians.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="student_id" class="mb-2 block text-sm font-bold text-gray-700">Estudante</label>
                    <select id="student_id" name="student_id" required
                        class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                        <option value="">Selecione</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                {{ $student->name }} — {{ $student->email }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="guardian_email" class="mb-2 block text-sm font-bold text-gray-700">
                        E-mail do responsável
                    </label>
                    <input id="guardian_email" name="guardian_email" type="email" required autocomplete="email"
                        value="{{ old('guardian_email') }}"
                        class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="guardian_name" class="mb-2 block text-sm font-bold text-gray-700">
                        Nome (para uma nova conta)
                    </label>
                    <input id="guardian_name" name="guardian_name" type="text" autocomplete="name"
                        value="{{ old('guardian_name') }}"
                        class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="relationship" class="mb-2 block text-sm font-bold text-gray-700">Vínculo familiar</label>
                    <input id="relationship" name="relationship" type="text" required maxlength="60"
                        value="{{ old('relationship', 'Responsável') }}"
                        class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                </div>

                <fieldset class="rounded-xl bg-background-light p-4">
                    <legend class="px-1 text-sm font-extrabold text-gray-700">Senha provisória para nova conta</legend>
                    <p class="mb-3 text-xs text-gray-500">Mínimo de 12 caracteres. Deixe em branco ao reutilizar uma conta.</p>
                    <div class="space-y-3">
                        <div>
                            <label for="guardian_password" class="sr-only">Senha provisória</label>
                            <input id="guardian_password" name="guardian_password" type="password"
                                autocomplete="new-password" placeholder="Senha provisória"
                                class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label for="guardian_password_confirmation" class="sr-only">Confirme a senha provisória</label>
                            <input id="guardian_password_confirmation" name="guardian_password_confirmation" type="password"
                                autocomplete="new-password" placeholder="Confirme a senha"
                                class="w-full rounded-xl border-2 border-duo-border px-4 py-3 focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                </fieldset>

                <button type="submit"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 font-extrabold text-white hover:bg-primary-dark">
                    <span class="material-symbols-outlined" aria-hidden="true">family_restroom</span>
                    Autorizar acompanhamento
                </button>
            </form>
        </section>

        <section aria-labelledby="links-heading" class="min-w-0 rounded-2xl border-2 border-duo-border bg-white">
            <div class="border-b-2 border-duo-border p-6">
                <h2 id="links-heading" class="text-xl font-extrabold text-duo-heading">Vínculos ativos</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $links->total() }} autorização(ões) registrada(s).</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left">
                    <caption class="sr-only">Responsáveis vinculados aos estudantes</caption>
                    <thead class="bg-background-light text-xs font-bold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-6 py-4">Responsável</th>
                            <th scope="col" class="px-6 py-4">Estudante</th>
                            <th scope="col" class="px-6 py-4">Vínculo</th>
                            <th scope="col" class="px-6 py-4 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y-2 divide-duo-border">
                        @forelse ($links as $link)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-duo-heading">{{ $link->guardian->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $link->guardian->email }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-bold text-duo-heading">{{ $link->student->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $link->student->email }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $link->relationship }}</td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" action="{{ route('institution.guardians.destroy', $link) }}"
                                        onsubmit="return confirm('Remover esta autorização de acompanhamento?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="rounded-lg px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50">
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">
                                    Nenhum responsável foi vinculado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($links->hasPages())
                <div class="border-t-2 border-duo-border p-5">{{ $links->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
