@props([
    'variant' => 'neutral',
    'icon' => null,
    'dot' => false,
])

<span {{ $attributes->merge(['class' => "badge badge--{$variant}"]) }}>
    @if ($dot)
        <span class="badge__dot" aria-hidden="true"></span>
    @elseif ($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif

    <span>{{ $slot }}</span>
</span>
