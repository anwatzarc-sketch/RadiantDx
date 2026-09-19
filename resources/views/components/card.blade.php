@props(['title' => null, 'subtitle' => null, 'icon' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:px-5">
            <div class="flex items-start gap-2.5">
                @if ($icon)
                    <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                @endif
                <div>
                    @if ($title)
                        <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
                    @endif
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padded ? 'px-4 py-4 sm:px-5' : '' }}">
        {{ $slot }}
    </div>
</section>
