@props([
    'title' => 'Aucun résultat',
    'description' => null,
    'icon' => 'fa-solid fa-inbox',
    'id' => null,
])

<section
    {{ $attributes->merge(['class' => 'state-empty']) }}
    aria-label="{{ $title }}"
>
    <div class="state-empty__icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <h2>{{ $title }}</h2>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="state-empty__actions">
            {{ $slot }}
        </div>
    @endif
</section>
