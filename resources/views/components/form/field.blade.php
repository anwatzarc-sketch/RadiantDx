@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
])

{{--
    Wraps one control with its label, hint and validation message, and ties them
    together with aria-describedby so assistive technology reads the error.
--}}

@php
    $errorId = $name.'-error';
    $hintId = $name.'-hint';
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label for="{{ $name }}" class="field-label">
            {{ $label }}
            @if ($required)
                <span class="text-rose-600" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $hintId }}" class="field-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="field-error">
            <x-icon name="warning" class="mt-px size-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
