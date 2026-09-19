@props([
    'steps' => [],
    'current' => null,
    'cancelled' => false,
    'cancelledLabel' => 'Cancelled',
])

<ol {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-1 gap-y-2']) }} aria-label="Workflow progress">
    @if ($cancelled)
        <li class="flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800 ring-1 ring-inset ring-rose-600/20">
            <x-icon name="ban" class="size-3.5 shrink-0" aria-hidden="true" />
            <span>{{ $cancelledLabel }}</span>
        </li>
    @else
        @foreach ($steps as $step)
            @php
                // Pre-computed step state or derived state from $current prop
                $label = is_array($step) ? ($step['label'] ?? '') : (string) $step;
                $key = is_array($step) ? ($step['key'] ?? $label) : $step;
                
                $state = is_array($step) && isset($step['state']) 
                    ? $step['state'] 
                    : ($current !== null && $key === $current ? 'current' : 'todo');

                $isDone = $state === 'done';
                $isCurrent = $state === 'current';
            @endphp

            <li class="flex items-center gap-1">
                <span
                    @class([
                        'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset transition-colors',
                        'bg-emerald-100 text-emerald-800 ring-emerald-600/20' => $isDone,
                        'bg-brand-700 text-white ring-brand-800' => $isCurrent,
                        'bg-white text-slate-500 ring-slate-300' => !$isDone && !$isCurrent,
                    ])
                    @if ($isCurrent) aria-current="step" @endif
                >
                    @if ($isDone)
                        <x-icon name="check" class="size-3.5 shrink-0" aria-hidden="true" />
                    @elseif ($isCurrent)
                        <x-icon name="clock" class="size-3.5 shrink-0" aria-hidden="true" />
                    @endif

                    <span>{{ $label }}</span>

                    @if ($isCurrent)
                        <span class="sr-only">(current step)</span>
                    @endif
                </span>

                @unless ($loop->last)
                    <x-icon name="chevron-right" class="size-3.5 text-slate-300" aria-hidden="true" />
                @endunless
            </li>
        @endforeach
    @endif
</ol>