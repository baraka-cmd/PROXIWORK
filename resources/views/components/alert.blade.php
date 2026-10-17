@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $icons = [
        'success' => 'fa-solid fa-circle-check',
        'info' => 'fa-solid fa-circle-info',
        'warning' => 'fa-solid fa-triangle-exclamation',
        'danger' => 'fa-solid fa-circle-exclamation',
        'error' => 'fa-solid fa-circle-exclamation',
    ];

    $isUrgent = in_array($type, ['danger', 'error', 'warning'], true);
@endphp

<div
    role="{{ $isUrgent ? 'alert' : 'status' }}"
    aria-live="{{ $isUrgent ? 'assertive' : 'polite' }}"
    {{ $attributes->merge(['class' => "alert alert--{$type}"]) }}
>
    <div class="alert__icon" aria-hidden="true">
        <i class="{{ $icons[$type] ?? $icons['info'] }}"></i>
    </div>

    <div class="alert__content">
        @if ($title)
            <strong class="alert__title">{{ $title }}</strong>
        @endif

        <div class="alert__message">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button
            type="button"
            class="icon-button icon-button--sm alert__dismiss"
            data-alert-dismiss
            aria-label="Fermer l'alerte"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>
