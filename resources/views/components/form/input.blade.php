@props(['name', 'type' => 'text', 'value' => null])

{{--
    A password control carries its own reveal toggle. Putting it here rather
    than at each call site means a field added by a later module cannot forget
    it, and the eight existing password inputs needed no change to gain it.

    The toggle is an enhancement: the rendered type is `password`, and only
    Alpine flips it. Without JavaScript the button is cloaked and the field
    behaves exactly as it always has.
--}}

@php
    $isPassword = $type === 'password';
    $inputClasses = 'field-control '.($errors->has($name) ? 'field-control-invalid' : '').($isPassword ? ' pr-11' : '');
@endphp

@if ($isPassword)
<div class="relative" x-data="passwordField" x-on:password-reveal="revealed = true">
@endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        value="{{ old($name, $value) }}"
        @if ($isPassword) x-bind:type="revealed ? 'text' : 'password'" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->merge(['class' => $inputClasses]) }}
    />

@if ($isPassword)
    <button
        type="button"
        x-cloak
        x-on:click="revealed = ! revealed"
        x-bind:aria-pressed="revealed ? 'true' : 'false'"
        x-bind:aria-label="revealed ? 'Hide password' : 'Show password'"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition-colors
               hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2
               focus-visible:outline-brand-700 rounded-r-lg"
    >
        <x-icon name="eye" class="size-5" x-show="! revealed" />
        <x-icon name="eye-slash" class="size-5" x-show="revealed" x-cloak />
    </button>
</div>
@endif
