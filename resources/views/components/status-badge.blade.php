@props([
    'status',
    'icon' => null,
])

@php
    $label = null;
    $classes = 'bg-slate-100 text-slate-700 border-slate-200';

    if ($status) {
        if (is_object($status)) {
            // Workflow Enums or Objects with label() and badgeClasses()
            $label = method_exists($status, 'label') ? $status->label() : ($status->value ?? (string) $status);
            $classes = method_exists($status, 'badgeClasses') ? $status->badgeClasses() : $classes;
        } elseif (is_string($status)) {
            // String fallback for raw database string statuses
            $label = ucfirst(str_replace(['_', '-'], ' ', $status));
            $classes = match (strtolower($status)) {
                'pending', 'processing', 'in_progress' => 'bg-amber-100 text-amber-800 border-amber-200',
                'completed', 'validated', 'approved', 'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'failed', 'rejected', 'cancelled', 'draft' => 'bg-rose-100 text-rose-800 border-rose-200',
                default => 'bg-slate-100 text-slate-700 border-slate-200',
            };
        }
    }
@endphp

@if ($status && $label)
    <x-badge :classes="$classes" :icon="$icon" {{ $attributes }}>
        {{ $label }}
    </x-badge>
@endif