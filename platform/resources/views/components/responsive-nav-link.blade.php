@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-3 border-l-4 border-primary text-start text-base font-bold text-primary-dark bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary/30 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-3 border-l-4 border-transparent text-start text-base font-semibold text-gray-600 hover:text-primary-dark hover:bg-primary-light/70 hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/30 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
