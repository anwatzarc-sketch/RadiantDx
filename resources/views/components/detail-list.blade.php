@props(['columns' => 3])

@php
    $grid = match ((int) $columns) {
        1 => 'sm:grid-cols-1',
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<dl {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-x-6 '.$grid]) }}>
    {{ $slot }}
</dl>
