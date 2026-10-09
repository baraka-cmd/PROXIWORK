@props([
    'padding' => 'md',
    'interactive' => false,
])

<article
    {{ $attributes->merge([
        'class' => trim("surface-card surface-card--{$padding} " . ($interactive ? 'surface-card--interactive' : '')),
    ]) }}
>
    @isset($header)
        <div class="surface-card__header">
            {{ $header }}
        </div>
    @endisset

    <div class="surface-card__body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="surface-card__footer">
            {{ $footer }}
        </div>
    @endisset
</article>
