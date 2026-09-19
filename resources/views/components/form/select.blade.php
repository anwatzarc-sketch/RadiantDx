@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])

<select
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    {{ $attributes->merge(['class' => 'field-control '.($errors->has($name) ? 'field-control-invalid' : '')]) }}
>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
            {{ $optionLabel }}
        </option>
    @endforeach
</select>
