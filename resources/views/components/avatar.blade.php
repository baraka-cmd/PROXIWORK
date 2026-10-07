@props([
    'src' => null,
    'alt' => '',
    'initials' => null,
    'size' => 'md',
])

<span
    {{ $attributes->merge(['class' => "avatar avatar--{$size}"]) }}
    @if (! $src) aria-hidden="true" @endif
>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" />
    @elseif ($initials)
        <span aria-hidden="true">{{ $initials }}</span>
    @else
        <i class="fa-solid fa-user" aria-hidden="true"></i>
    @endif
</span>
