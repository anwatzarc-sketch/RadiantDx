@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])

<div class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
    <span class="flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if ($description)
        <p class="max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
