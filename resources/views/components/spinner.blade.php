@props([
    'size' => 'md',
    'label' => 'Chargement…',
])

<span
    role="status"
    aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => "spinner spinner--{$size}"]) }}
>
    <span class="spinner__circle" aria-hidden="true"></span>
</span>
