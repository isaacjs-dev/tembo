@props([
    'name',
    'show' => false,
    'maxWidth' => 'md',
    'focusable' => true,
    'closeable' => true,
    'label' => null,
])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
][$maxWidth] ?? 'sm:max-w-md';
@endphp

<div
    x-data="{
        show: @js($show),
        previouslyFocused: null,
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';
            return [...$refs.modalContent.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'));
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
        close() {
            if (@js($closeable)) {
                this.show = false;
            }
        }
    }"
    x-init="$watch('show', value => {
        if (value) {
            previouslyFocused = document.activeElement;
            document.body.style.overflow = 'hidden';
            @if($focusable) setTimeout(() => firstFocusable()?.focus(), 100); @endif
        } else {
            document.body.style.overflow = '';
            previouslyFocused?.focus();
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="close()"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable()?.focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable()?.focus()"
>
    <template x-teleport="body">
        <div 
            x-show="show" 
            class="fixed inset-0 flex items-center justify-center p-4 sm:p-0"
            style="z-index: 99999; display: none;"
            aria-label="{{ $label ?? \Illuminate\Support\Str::headline($name) }}"
            role="dialog" 
            aria-modal="true"
        >
            {{-- Backdrop layer escurecimento + blur leve --}}
            <div 
                x-show="show" 
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 transition-opacity"
                aria-hidden="true"
                style="background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px);"
                x-on:click="close()"
            ></div>

            {{-- Modal Content Box --}}
            <div 
                x-ref="modalContent"
                x-show="show"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all w-full {{ $maxWidthClass }} border-2 border-duo-border"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                @click.stop
            >
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
