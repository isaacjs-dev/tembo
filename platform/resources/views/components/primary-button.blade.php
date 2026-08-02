<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary text-sm uppercase tracking-wider']) }}>
    {{ $slot }}
</button>