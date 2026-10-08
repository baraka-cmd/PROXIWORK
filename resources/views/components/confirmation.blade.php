@props([
    'id' => 'confirmation-dialog',
    'title' => 'Confirmer l’action',
    'message' => 'Cette action peut avoir des conséquences irréversibles.',
    'confirmLabel' => 'Confirmer',
    'cancelLabel' => 'Annuler',
    'variant' => 'danger',
])

<div
    id="{{ $id }}"
    class="confirmation"
    data-confirmation-dialog
    hidden
    aria-hidden="true"
>
    <div class="confirmation__backdrop" data-confirmation-cancel></div>

    <section
        class="confirmation__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        aria-describedby="{{ $id }}-message"
        tabindex="-1"
    >
        <div class="confirmation__icon confirmation__icon--{{ $variant }}" aria-hidden="true">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2 id="{{ $id }}-title" class="confirmation__title">{{ $title }}</h2>
        <p id="{{ $id }}-message" class="confirmation__message">{{ $message }}</p>

        <div class="confirmation__actions">
            <button
                type="button"
                class="button button--ghost"
                data-confirmation-cancel
            >
                {{ $cancelLabel }}
            </button>

            <button
                type="button"
                class="button button--{{ $variant }}"
                data-confirmation-accept
            >
                {{ $confirmLabel }}
            </button>
        </div>
    </section>
</div>
