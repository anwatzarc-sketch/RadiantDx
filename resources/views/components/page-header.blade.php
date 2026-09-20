@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back'])

{{--
    The optional $leading slot sits before the title — used for a staff
    photograph, so a person's face is the first thing on their own page. It is
    optional so every existing header is unaffected.
--}}

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex min-w-0 items-start gap-4">
        @isset($leading)
            <div class="shrink-0">{{ $leading }}</div>
        @endisset

        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">
                    <x-icon name="arrow-left" class="size-3.5" />
                    {{ $backLabel }}
                </a>
            @endif
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
            @isset($meta)
                <div class="mt-2 flex flex-wrap items-center gap-2">{{ $meta }}</div>
            @endisset
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
