@props([
    'title' => 'Aucun résultat',
    'description' => null,
    'icon' => 'fa-solid fa-inbox',
    'id' => null,
])

@php($headingId = $id ?: 'empty-state-' . Illuminate\Support\Str::uuid())

<section
    {{ $attributes->merge(['class' => 'state-empty']) }}
    aria-labelledby="{{ $headingId }}"
>
    <div class="state-empty__icon" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <h2 id="{{ $headingId }}">{{ $title }}</h2>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="state-empty__actions">
            {{ $slot }}
        </div>
    @endif
</section>
