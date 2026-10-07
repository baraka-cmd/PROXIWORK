@props([
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'divider']) }} @if ($label) role="separator" @endif>
    @if ($label)
        <span class="divider__label">{{ $label }}</span>
    @endif
</div>
