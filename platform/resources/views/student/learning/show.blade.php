<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('student.learning.index') }}" class="text-sm font-bold text-primary-dark hover:underline">
                    Estudar e revisar
                </a>
                <h1 class="mt-1 text-3xl font-black text-duo-heading">{{ $material->title }}</h1>
                <p class="mt-1 text-gray-700">
                    {{ $material->discipline?->name ?: 'Conteúdo geral' }} · por {{ $material->author->name }}
                </p>
            </div>
            <a href="{{ route('student.learning.index') }}"
                class="duo-button-secondary rounded-xl px-5 py-3 text-center font-extrabold">Voltar aos materiais</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div role="status" aria-live="polite"
            class="mx-auto mb-5 max-w-4xl rounded-xl border-2 border-green-200 bg-green-50 px-5 py-4 font-bold text-green-900">
            {{ session('status') }}
        </div>
    @endif

    <article class="mx-auto max-w-4xl overflow-hidden rounded-2xl border-2 border-duo-border bg-white shadow-sm">
        @if($material->description)
            <div class="border-b-2 border-duo-border bg-blue-50 p-5 md:p-7">
                <p class="text-lg font-medium leading-relaxed text-blue-950">{{ $material->description }}</p>
            </div>
        @endif

        @if($material->body)
            <div class="whitespace-pre-wrap p-5 text-base leading-8 text-gray-900 md:p-8">{{ $material->body }}</div>
        @endif

        @if($material->external_url)
            <div class="border-t-2 border-duo-border p-5 md:p-7">
                <h2 class="text-lg font-extrabold text-gray-900">Recurso complementar</h2>
                <p class="mt-1 text-sm text-gray-700">O endereço será aberto em uma nova aba.</p>
                <a href="{{ $material->external_url }}" target="_blank" rel="noopener noreferrer"
                    class="duo-button-primary mt-4 inline-flex items-center gap-2 rounded-xl px-5 py-3 font-extrabold">
                    Acessar recurso externo
                    <span class="material-symbols-outlined" aria-hidden="true">open_in_new</span>
                </a>
            </div>
        @endif

        @if($material->customSkill || $material->bnccNode)
            <footer class="border-t-2 border-duo-border bg-gray-50 p-5 text-sm text-gray-700 md:px-8">
                <span class="font-extrabold">Foco desta revisão:</span>
                {{ $material->customSkill?->name ?: ($material->bnccNode?->code ?: $material->bnccNode?->title) }}
            </footer>
        @endif

        <div class="border-t-2 border-duo-border bg-background-light p-5 md:p-7">
            @if($progress->status === 'completed')
                <p class="flex items-center gap-2 font-extrabold text-green-800">
                    <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                    Revisão concluída
                    @if($progress->completed_at)
                        em {{ $progress->completed_at->timezone(config('app.timezone'))->format('d/m/Y') }}
                    @endif
                </p>
            @else
                <form method="POST" action="{{ route('student.learning.complete', $material) }}">
                    @csrf
                    <button type="submit"
                        class="duo-button-primary inline-flex min-h-11 items-center gap-2 rounded-xl px-5 py-3 font-extrabold">
                        <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                        Marcar revisão como concluída
                    </button>
                </form>
            @endif
        </div>
    </article>
</x-app-layout>
