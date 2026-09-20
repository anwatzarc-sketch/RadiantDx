@props([
    'staff',
    'size' => 'md',
])

{{--
    A staff member's picture, or their initials when there is none.

    The initials tile IS the default: rather than shipping a placeholder image
    file, a record with no photograph renders type. That avoids a default file
    to deploy and keeps the fallback legible at every size.

    The photo is fetched through the authorised route, never from a public path
    — staff photographs live on the private disk.
--}}

@php
    $box = match ($size) {
        'xs' => 'size-8 text-[0.65rem]',
        'sm' => 'size-10 text-xs',
        'lg' => 'size-24 text-2xl',
        'xl' => 'size-32 text-3xl',
        default => 'size-12 text-sm',
    };

    $initials = collect(preg_split('/\s+/', trim((string) $staff->full_name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $hasPhoto = $staff->photo_path !== null;
@endphp

@if ($hasPhoto)
    <img
        src="{{ route('administration.staff.photo.show', $staff) }}"
        alt="{{ $staff->full_name }}"
        {{ $attributes->merge(['class' => $box.' shrink-0 rounded-full object-cover ring-1 ring-slate-200']) }}
    />
@else
    <span
        {{ $attributes->merge([
            'class' => $box.' flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800 ring-1 ring-brand-200',
        ]) }}
        aria-hidden="true"
    >{{ $initials ?: '?' }}</span>
@endif
