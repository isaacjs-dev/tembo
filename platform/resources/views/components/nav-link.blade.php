@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-primary text-sm font-bold leading-5 text-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/30 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-semibold leading-5 text-gray-500 hover:text-primary-dark hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/30 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
