@props([
    'label' => 'Recurso',
    'current' => 0,
    'limit' => null,
    'icon' => '📊',
    'color' => '#1d78a6',
])

@php
    $unlimited = $limit === null;
    $percent = (!$unlimited && $limit > 0) ? round(($current / $limit) * 100) : 0;
    $remaining = $unlimited ? null : max(0, $limit - $current);
    $isWarning = $percent >= 80 && $percent < 100;
    $isDanger = $percent >= 100;
    $barColor = $isDanger ? '#ef4444' : ($isWarning ? '#f59e0b' : $color);
@endphp

<div class="card px-5 py-4">
    <div class="mb-2 flex items-center justify-between gap-3">
        <span class="text-sm font-bold text-duo-heading">{{ $icon }} {{ $label }}</span>
        <span class="text-xs font-medium text-gray-500">
            @if($unlimited)
                {{ $current }} / ∞
            @else
                {{ $current }} / {{ $limit }}
            @endif
        </span>
    </div>

    {{-- Barra de Progresso --}}
    <div class="h-2 overflow-hidden rounded-full bg-primary-light" role="progressbar"
        aria-label="Uso de {{ $label }}" aria-valuemin="0"
        aria-valuemax="{{ $unlimited ? 100 : $limit }}"
        aria-valuenow="{{ $unlimited ? min($current > 0 ? 30 : 0, 100) : min($current, $limit) }}">
        @if($unlimited)
            <div style="width:{{ min($current > 0 ? 30 : 0, 100) }}%;height:100%;background:{{ $barColor }};border-radius:9999px;transition:width .3s;"></div>
        @else
            <div style="width:{{ min($percent, 100) }}%;height:100%;background:{{ $barColor }};border-radius:9999px;transition:width .3s;"></div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="mt-1.5 text-xs font-medium text-gray-400">
        @if($unlimited)
            Ilimitado
        @elseif($isDanger)
            <span class="font-bold text-red-600">⚠️ Limite atingido</span>
        @elseif($isWarning)
            <span class="text-yellow-700">{{ $remaining }} restante(s)</span>
        @else
            {{ $remaining }} restante(s)
        @endif
    </div>
</div>
