@props([
    'type' => 'button',
    'variant' => 'ghost',
    'size' => 'md',
    'label',
    'icon',
    'disabled' => false,
])

<button
    type="{{ $type }}"
    @disabled($disabled)
    aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => "icon-button icon-button--{$variant} icon-button--{$size}"]) }}
>
    <i class="{{ $icon }}" aria-hidden="true"></i>
</button>
