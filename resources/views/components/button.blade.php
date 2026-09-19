@props([
    'variant' => 'secondary',
    'href' => null,
    'icon' => null,
    'size' => 'md',
])

@php
    $base = 'tap-target inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition
             focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
    ];

    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-sm hover:bg-brand-800 focus-visible:outline-brand-700',
        'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-slate-500',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:outline-rose-600',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-emerald-600',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-500',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['secondary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </button>
@endif
