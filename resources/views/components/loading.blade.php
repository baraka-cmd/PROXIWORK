@props([
    'label' => 'Chargement…',
])

<div
    role="status"
    aria-live="polite"
    {{ $attributes->merge(['class' => 'state-loading']) }}
>
    <x-spinner />
    <span>{{ $label }}</span>
</div>
