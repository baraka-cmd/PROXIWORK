@props([
    'title' => 'Opération réussie',
    'message' => null,
    'dismissible' => true,
])

@if ($message ?? $slot->isNotEmpty())
    <div
        class="alert alert--success"
        role="status"
        aria-live="polite"
        {{ $attributes }}
    >
        <div class="alert__icon" aria-hidden="true">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <div class="alert__content">
            @if ($title)
                <strong class="alert__title">{{ $title }}</strong>
            @endif

            <div class="alert__message">{{ $message ?? $slot }}</div>
        </div>

        @if ($dismissible)
            <button
                type="button"
                class="icon-button icon-button--sm alert__dismiss"
                data-alert-dismiss
                aria-label="Fermer le message de réussite"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        @endif
    </div>
@endif
