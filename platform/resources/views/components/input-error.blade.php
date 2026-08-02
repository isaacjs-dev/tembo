@props(['messages'])

@if ($messages)
    <ul role="alert" {{ $attributes->merge(['class' => 'error-list mt-2']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
