@props(['name', 'value' => null, 'rows' => 3])

<textarea
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    rows="{{ $rows }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->merge(['class' => 'field-control '.($errors->has($name) ? 'field-control-invalid' : '')]) }}
>{{ old($name, $value) }}</textarea>
