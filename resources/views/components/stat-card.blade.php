@props([
    'label',
    'value',
    'hint' => null,
    'hintIcon' => null,
    'href' => null,
    'tone' => 'slate',
    'icon' => 'inbox',
])

@php
    /*
     * Tone carries meaning across the dashboard rather than being decoration:
     * amber needs action, sky is in progress, indigo is awaiting review,
     * emerald is complete, rose is restricted, slate is a plain count. The
     * icon tile and the hint line are both driven from it so a card reads as
     * one colour, and the hint icon defaults to something that matches that
     * meaning — pass hint-icon to override per card.
     */
    $tones = [
        'slate' => ['tile' => 'bg-slate-100 text-slate-600', 'hint' => 'text-slate-500', 'icon' => 'info'],
        'sky' => ['tile' => 'bg-sky-50 text-sky-600', 'hint' => 'text-sky-600', 'icon' => 'pencil'],
        'amber' => ['tile' => 'bg-amber-50 text-amber-600', 'hint' => 'text-amber-600', 'icon' => 'clock'],
        'rose' => ['tile' => 'bg-rose-50 text-rose-600', 'hint' => 'text-rose-600', 'icon' => 'lock'],
        'emerald' => ['tile' => 'bg-emerald-50 text-emerald-600', 'hint' => 'text-emerald-600', 'icon' => 'check-circle'],
        'indigo' => ['tile' => 'bg-indigo-50 text-indigo-600', 'hint' => 'text-indigo-600', 'icon' => 'eye'],
        // Also emitted by DashboardController::cards().
        'blue' => ['tile' => 'bg-blue-50 text-blue-600', 'hint' => 'text-blue-600', 'icon' => 'users'],
        'cyan' => ['tile' => 'bg-cyan-50 text-cyan-600', 'hint' => 'text-cyan-600', 'icon' => 'sliders'],
        'purple' => ['tile' => 'bg-purple-50 text-purple-600', 'hint' => 'text-purple-600', 'icon' => 'layers'],
    ];

    $palette = $tones[$tone] ?? $tones['slate'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 ' .
                   ($href ? 'hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md' : '')
    ]) }}
>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-xs font-semibold tracking-wider text-slate-500 uppercase">
            {{ $label }}
        </span>

        <span class="mt-1.5 block text-3xl leading-none font-bold tracking-tight text-slate-900 tabular-nums">
            {{ $value }}
        </span>

        @if ($hint)
            <span class="mt-2 flex items-center gap-1.5 text-xs font-medium {{ $palette['hint'] }}">
                <x-icon :name="$hintIcon ?? $palette['icon']" class="size-3.5 shrink-0" />
                <span class="truncate">{{ $hint }}</span>
            </span>
        @endif
    </span>

    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $palette['tile'] }} transition duration-200 group-hover:scale-105">
        <x-icon :name="$icon" class="size-5" />
    </span>
</{{ $tag }}>
