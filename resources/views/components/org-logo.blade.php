@props([
    'size' => 'md',
    'path' => null,
])

{{--
    The organisation's mark, wherever it appears — printed letterhead, report
    footer, sidebar.

    Nothing here assumes the Harme artwork specifically, so a site deploying
    this application can point LAB_LOGO_PATH at its own file:

      * the file is named by config, not hard-coded;
      * height is fixed and width is free up to a cap, so square marks, tall
        crests and wide wordmarks all sit correctly without being distorted or
        pushing the surrounding layout out of shape;
      * remote and data: URIs are passed through untouched;
      * when no artwork resolves, the slot is rendered instead. The report
        passes nothing and falls back to a text-only letterhead; the sidebar
        passes its icon tile so the navigation never shows an empty gap.

    The mark is decorative: the organisation name is set in type beside it in
    every placement, so alt text here would only make screen readers announce
    the name twice.
--}}

@php
    $source = trim((string) ($path ?? config('laboratory.organisation.logo', '')));

    $isRemote = $source !== '' && (
        str_starts_with($source, 'http://')
        || str_starts_with($source, 'https://')
        || str_starts_with($source, 'data:')
    );

    if ($isRemote) {
        $src = $source;
    } else {
        $relative = ltrim($source, '/');
        $src = ($relative !== '' && is_file(public_path($relative))) ? asset($relative) : null;
    }

    // Millimetres for the placements that land on paper, pixels for the ones
    // that only ever land on a screen.
    $box = match ($size) {
        'nav' => 'h-9 max-w-[72px]',
        'sm' => 'h-7 max-w-[26mm]',
        'lg' => 'h-20 max-w-[60mm]',
        default => 'h-14 max-w-[45mm]',
    };
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        alt=""
        aria-hidden="true"
        {{ $attributes->merge(['class' => $box.' w-auto shrink-0 object-contain']) }}
    />
@else
    {{-- Null-coalesced so the component is also safe to render directly,
         outside the <x-org-logo> component syntax that defines $slot. --}}
    {{ $slot ?? '' }}
@endif
