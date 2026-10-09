@props([
    'lines' => 3,
])

<div
    {{ $attributes->merge(['class' => 'state-skeleton']) }}
    aria-hidden="true"
>
    @for ($line = 0; $line < $lines; $line++)
        <span class="state-skeleton__line {{ $line === 0 ? 'state-skeleton__line--title' : '' }}"></span>
    @endfor
</div>
