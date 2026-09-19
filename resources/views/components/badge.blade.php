@props(['classes' => 'bg-slate-100 text-slate-700 ring-slate-500/20', 'icon' => null])

{{-- Status marker. Always renders its label; the icon and colour are additions. --}}

<span {{ $attributes->merge(['class' => 'badge '.$classes]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="size-3.5 shrink-0" />
    @endif
    {{ $slot }}
</span>
