<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-secondary text-sm']) }}>
    {{ $slot }}
</button>