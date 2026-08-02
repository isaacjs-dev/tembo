<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger text-sm']) }}>
    {{ $slot }}
</button>