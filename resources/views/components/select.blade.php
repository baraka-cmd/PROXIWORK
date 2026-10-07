@props([
    'name',
    'label' => null,
    'value' => null,
    'help' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    $selectId = $id ?? IlluminateSupportStr::slug($name);
    $hasError = $errors->has($name);
    $errorId = "{$selectId}-error";
    $helpId = "{$selectId}-help";
@endphp

<div class="form-field">
    @if ($label)
        <label class="form-label" for="{{ $selectId }}">
            {{ $label }}
            @if ($required)
                <span class="form-label__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="form-select-wrapper">
        <select
            id="{{ $selectId }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @elseif ($help) aria-describedby="{{ $helpId }}" @endif
            {{ $attributes->except(['id', 'name', 'required'])->merge(['class' => 'form-control form-select']) }}
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            {{ $slot }}
        </select>

        <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
    </div>

    @if ($help && ! $hasError)
        <p id="{{ $helpId }}" class="form-help">{{ $help }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="form-error">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>
