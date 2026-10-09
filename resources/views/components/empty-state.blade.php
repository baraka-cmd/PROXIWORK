@props([
    'title' => 'Aucun résultat',
    'description' => null,
    'icon' => 'fa-solid fa-inbox',
    'ariaLabel' => null,
])

<section
    {{ $attributes->merge(['class' => 'state-empty']) }}
    aria-label="{{ $ariaLabel ?: $title }}"
>
    <div class="state-empty__icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <div class="state-empty__content">
        <h2>{{ $title }}</h2>

        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @if (trim((string) $slot) !== '')
        <div class="state-empty__actions">
            {{ $slot }}
        </div>
    @endif
</section>
