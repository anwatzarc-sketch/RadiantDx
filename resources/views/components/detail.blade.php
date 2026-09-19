@props(['label', 'value' => null, 'mono' => false])

{{-- One labelled fact inside a definition list. --}}

<div class="py-2">
    <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $label }}</dt>
    <dd class="mt-0.5 text-sm {{ $mono ? 'font-mono' : '' }} text-slate-900">
        @if ($slot->isEmpty())
            {{ filled($value) ? $value : '—' }}
        @else
            {{ $slot }}
        @endif
    </dd>
</div>
