@props(['action', 'active' => false])

{{-- Server side filtering: a plain GET form, so every view is a shareable URL. --}}

<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
    {{ $slot }}

    <div class="flex items-center gap-2">
        <x-button type="submit" variant="primary" icon="search">Filter</x-button>
        @if ($active)
            <x-button :href="$action" variant="ghost" size="md">Clear</x-button>
        @endif
    </div>
</form>
