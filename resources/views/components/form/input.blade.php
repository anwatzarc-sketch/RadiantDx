@props(['name', 'type' => 'text', 'value' => null])

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    value="{{ old($name, $value) }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->merge(['class' => 'field-control '.($errors->has($name) ? 'field-control-invalid' : '')]) }}
/>
