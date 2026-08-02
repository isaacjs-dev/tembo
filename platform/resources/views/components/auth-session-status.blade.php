@props(['status'])

@if ($status)
    <div role="status" aria-live="polite" {{ $attributes->merge(['class' => 'alert alert-success text-sm']) }}>
        {{ $status }}
    </div>
@endif
