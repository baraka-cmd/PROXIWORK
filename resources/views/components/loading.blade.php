@props([
    'label' => 'Chargement…',
    'variant' => 'section',
])

<div
    role="status"
    aria-live="polite"
    {{ $attributes->merge(['class' => 'state-loading state-loading--'.$variant]) }}
>
    <x-spinner />
    <span>{{ $label }}</span>
</div>
