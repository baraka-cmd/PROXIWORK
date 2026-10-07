@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
    'loading' => false,
    'disabled' => false,
])

@php
    $classes = trim("button button--{$variant} button--{$size}");
    $isDisabled = (bool) $disabled;
@endphp

@if ($href && ! $isDisabled)
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($loading) aria-busy="true" @endif
    >
        @if ($icon && $iconPosition === 'left')
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif

        <span>{{ $slot }}</span>

        @if ($icon && $iconPosition === 'right')
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif

        @if ($loading)
            <span class="button__spinner" aria-hidden="true"></span>
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($isDisabled)
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($loading) aria-busy="true" @endif
    >
        @if ($icon && $iconPosition === 'left')
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif

        <span>{{ $slot }}</span>

        @if ($icon && $iconPosition === 'right')
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif

        @if ($loading)
            <span class="button__spinner" aria-hidden="true"></span>
        @endif
    </button>
@endif
