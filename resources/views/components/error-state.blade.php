@props([
    'title' => 'Une erreur est survenue',
    'description' => 'Nous ne pouvons pas afficher ces informations pour le moment.',
    'icon' => 'fa-solid fa-triangle-exclamation',
    'status' => null,
])

<section
    {{ $attributes->merge(['class' => 'state-error']) }}
    role="alert"
    aria-live="assertive"
>
    <div class="state-error__icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <div class="state-error__content">
        @if ($status)
            <p class="state-error__status">{{ $status }}</p>
        @endif
        <h2>{{ $title }}</h2>
        <p>{{ $description }}</p>

        @if (trim((string) $slot) !== '')
            <div class="state-error__actions">
                {{ $slot }}
            </div>
        @endif
    </div>
</section>
