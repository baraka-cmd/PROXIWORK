@props([
    'name',
    'label' => null,
    'value' => null,
    'type' => 'text',
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'autocomplete' => null,
    'id' => null,
])

@php
    $inputId = $id ?? str($name)->slug()->toString();
    $hasError = $errors->has($name);
    $errorId = "{$inputId}-error";
    $helpId = "{$inputId}-help";
@endphp

<div class="form-field">
    @if ($label)
        <label class="form-label" for="{{ $inputId }}">
            {{ $label }}
            @if ($required)
                <span class="form-label__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="form-control-wrapper">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if ($required) required @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @elseif ($help) aria-describedby="{{ $helpId }}" @endif
            {{ $attributes->except(['id', 'name', 'type', 'value', 'placeholder', 'required', 'autocomplete'])->merge(['class' => 'form-control']) }}
        />

        @if ($type === 'password')
            <button
                type="button"
                class="form-control-action"
                data-password-toggle="{{ $inputId }}"
                aria-label="Afficher le mot de passe"
                aria-pressed="false"
            >
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </button>
        @endif
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
