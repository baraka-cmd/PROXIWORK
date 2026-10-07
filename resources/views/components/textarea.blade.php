@props([
    'name',
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'rows' => 5,
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

    <textarea
        id="{{ $inputId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @elseif ($help) aria-describedby="{{ $helpId }}" @endif
        {{ $attributes->except(['id', 'name', 'rows', 'placeholder', 'required'])->merge(['class' => 'form-control form-control--textarea']) }}
    >{{ old($name, $value) }}</textarea>

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
