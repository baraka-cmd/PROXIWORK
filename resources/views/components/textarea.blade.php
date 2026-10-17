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
    $validationErrors = app('view')->shared('errors');
    $hasError = $validationErrors?->has($name) ?? false;
    $errorId = "{$inputId}-error";
    $helpId = "{$inputId}-help";
    $describedBy = trim(($help ? $helpId : '').' '.($hasError ? $errorId : ''));
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
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id', 'name', 'rows', 'placeholder', 'required'])->merge(['class' => 'form-control form-control--textarea']) }}
    >{{ old($name, $value) }}</textarea>

    @if ($help)
        <p id="{{ $helpId }}" class="form-help">{{ $help }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="form-error">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span>{{ $validationErrors->first($name) }}</span>
        </p>
    @endif
</div>
