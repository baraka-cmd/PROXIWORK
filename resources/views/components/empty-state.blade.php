@props([
    'title' => 'Aucun résultat',
    'description' => null,
    'icon' => 'fa-solid fa-inbox',
])

<section
    {{ $attributes->merge(['class' => 'state-empty']) }}
    aria-labelledby="empty-state-title"
>
    <div class="state-empty__icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <h2 id="empty-state-title">{{ $title }}</h2>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="state-empty__actions">
            {{ $slot }}
        </div>
    @endif
</section>
