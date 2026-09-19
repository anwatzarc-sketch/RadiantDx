@props([
    'action',
    'method' => 'POST',
    'title',
    'message',
    'confirm' => 'Confirm',
    'variant' => 'danger',
    'icon' => null,
    'size' => 'sm',
    'reason' => false,
    'reasonLabel' => 'Reason',
    'reasonHint' => null,
])

{{--
    A destructive or workflow-changing action behind an explicit confirmation.
    Where the operation has to be justified, the dialog also collects the reason
    that will be written to the audit trail.
--}}

@php
    $dialogId = 'confirm-'.Str::random(8);
@endphp

<div x-data="{ open: false }" class="inline-flex">
    <x-button type="button" :variant="$variant" :size="$size" :icon="$icon" x-on:click="open = true">
        {{ $slot }}
    </x-button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $dialogId }}-title"
            x-on:keydown.escape.window="open = false"
        >
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50" x-on:click="open = false"></div>

            <div
                x-show="open"
                x-transition
                class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <form method="POST" action="{{ $action }}" class="flex flex-col">
                    @csrf
                    @if (! in_array($method, ['GET', 'POST'], true))
                        @method($method)
                    @endif

                    <div class="flex gap-3 px-5 pt-5">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full
                            {{ $variant === 'danger' ? 'bg-rose-100 text-rose-700' : 'bg-brand-100 text-brand-800' }}">
                            <x-icon :name="$variant === 'danger' ? 'warning' : 'info'" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="{{ $dialogId }}-title" class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $message }}</p>
                        </div>
                    </div>

                    @if ($reason)
                        <div class="px-5 pt-4">
                            <label for="{{ $dialogId }}-reason" class="field-label">{{ $reasonLabel }}</label>
                            <textarea
                                id="{{ $dialogId }}-reason"
                                name="reason"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="255"
                                class="field-control mt-1"
                                placeholder="This is recorded in the audit trail."
                            ></textarea>
                            @if ($reasonHint)
                                <p class="field-hint">{{ $reasonHint }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-5 flex justify-end gap-2 bg-slate-50 px-5 py-3">
                        <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                        <x-button type="submit" :variant="$variant">{{ $confirm }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
