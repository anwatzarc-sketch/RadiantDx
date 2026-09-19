{{-- Session feedback. Each tone states its kind in words as well as colour. --}}

@php
    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('status'),
    ]);

    $tones = [
        'success' => ['bg-emerald-50 text-emerald-900 ring-emerald-600/20', 'check-circle', 'Success'],
        'error' => ['bg-rose-50 text-rose-900 ring-rose-600/20', 'warning', 'Action refused'],
        'warning' => ['bg-amber-50 text-amber-900 ring-amber-600/30', 'warning', 'Attention'],
        'info' => ['bg-sky-50 text-sky-900 ring-sky-600/20', 'info', 'Notice'],
    ];
@endphp

@if ($messages !== [] || $errors->any())
    <div class="space-y-3" role="status" aria-live="polite">
        @foreach ($messages as $key => $message)
            @php([$classes, $icon, $heading] = $tones[$key])
            <div x-data="{ shown: true }" x-show="shown" class="flex items-start gap-3 rounded-lg px-4 py-3 text-sm ring-1 ring-inset {{ $classes }}">
                <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $heading }}</p>
                    <p class="mt-0.5">{{ $message }}</p>
                </div>
                <button type="button" x-on:click="shown = false" class="shrink-0 rounded p-1 hover:bg-black/5">
                    <span class="sr-only">Dismiss</span>
                    <x-icon name="close" class="size-4" />
                </button>
            </div>
        @endforeach

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-600/20 ring-inset">
                <div class="flex items-start gap-3">
                    <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ trans_choice('There is 1 problem with the form.|There are :count problems with the form.', $errors->count(), ['count' => $errors->count()]) }}
                        </p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
