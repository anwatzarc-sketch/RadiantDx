@props(['panel' => null, 'availableTests', 'selectedTests', 'categories' => []])

@php
    $catalogue = $availableTests->map(fn ($test) => [
        'id' => $test->id,
        'name' => $test->name,
        'code' => $test->code,
        'active' => (bool) $test->is_active,
    ])->values();

    $initial = old('tests', $selectedTests->pluck('id')->all());
    $initial = array_values(array_map('intval', (array) $initial));
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="name" label="Panel name" required>
            <x-form.input name="name" :value="$panel?->name" required placeholder="Routine Health Panel" />
        </x-form.field>

        <x-form.field name="code" label="Panel code" required hint="Unique across the catalogue.">
            <x-form.input name="code" :value="$panel?->code" required placeholder="RHP" class="field-control font-mono" />
        </x-form.field>
    </div>

    <x-form.field name="description" label="Description">
        <x-form.textarea name="description" :value="$panel?->description" rows="2" />
    </x-form.field>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-form.field name="category" label="Category">
            <x-form.input name="category" :value="$panel?->category" list="panel-categories" />
            <datalist id="panel-categories">
                @foreach ($categories as $category)
                    <option value="{{ $category }}"></option>
                @endforeach
            </datalist>
        </x-form.field>

        <x-form.field name="display_order" label="Display order">
            <x-form.input name="display_order" type="number" min="0" :value="$panel?->display_order ?? 0" />
        </x-form.field>

        <div class="flex items-end pb-2">
            <x-form.checkbox
                name="is_active"
                label="Panel is active"
                hint="A panel needs at least one active test to be usable."
                :checked="$panel?->is_active ?? true"
            />
        </div>
    </div>
</div>

{{-- Ordered membership: the order chosen here is the order tests are processed --}}
<div class="mt-6 border-t border-slate-200 pt-6" x-data="testPicker(@js($catalogue), @js($initial))">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-slate-800">Included tests</p>
            <p class="text-xs text-slate-500">
                Requesting this panel expands into one laboratory item per test, in this order.
            </p>
        </div>
        <p class="shrink-0 text-xs text-slate-500">
            <span x-text="selected.length" class="font-semibold text-slate-700"></span> selected
        </p>
    </div>

    @error('tests')
        <p class="field-error mt-2">{{ $message }}</p>
    @enderror

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Search --}}
        <div class="rounded-lg border border-slate-200">
            <div class="border-b border-slate-200 p-3">
                <label for="test-search" class="sr-only">Search tests</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                    <input type="search" id="test-search" x-model="query" placeholder="Search by name or code"
                           class="field-control pl-9" autocomplete="off" />
                </div>
            </div>

            <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                <template x-for="entry in results" :key="entry.id">
                    <li>
                        <button type="button" x-on:click="add(entry.id)"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-brand-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800" x-text="entry.name"></span>
                                <span class="block font-mono text-xs text-slate-500" x-text="entry.code"></span>
                            </span>
                            <span class="flex items-center gap-2">
                                <template x-if="! entry.active">
                                    <span class="badge bg-slate-100 text-slate-600 ring-slate-500/20">Inactive</span>
                                </template>
                                <x-icon name="plus" class="size-4 shrink-0 text-brand-600" />
                            </span>
                        </button>
                    </li>
                </template>
                <template x-if="results.length === 0">
                    <li class="px-3 py-6 text-center text-sm text-slate-500">No tests match this search.</li>
                </template>
            </ul>
        </div>

        {{-- Chosen, in order --}}
        <div class="rounded-lg border border-slate-200">
            <p class="border-b border-slate-200 px-3 py-2.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                Panel contents
            </p>

            <ol class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                <template x-for="(entry, index) in selectedEntries" :key="entry.id">
                    <li class="flex items-center gap-2 px-3 py-2.5">
                        <input type="hidden" name="tests[]" :value="entry.id" />
                        <span class="w-5 shrink-0 text-xs font-semibold text-slate-400 tabular-nums" x-text="index + 1"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-800" x-text="entry.name"></span>
                            <span class="block font-mono text-xs text-slate-500" x-text="entry.code"></span>
                        </span>
                        <span class="flex shrink-0 items-center gap-0.5">
                            <button type="button" x-on:click="move(entry.id, -1)" :disabled="index === 0"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30">
                                <span class="sr-only">Move up</span>
                                <x-icon name="arrow-up" class="size-3.5" />
                            </button>
                            <button type="button" x-on:click="move(entry.id, 1)" :disabled="index === selected.length - 1"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30">
                                <span class="sr-only">Move down</span>
                                <x-icon name="arrow-down" class="size-3.5" />
                            </button>
                            <button type="button" x-on:click="remove(entry.id)"
                                    class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                                <span class="sr-only">Remove from panel</span>
                                <x-icon name="close" class="size-3.5" />
                            </button>
                        </span>
                    </li>
                </template>
                <template x-if="selected.length === 0">
                    <li class="px-3 py-10 text-center text-sm text-slate-500">
                        No tests added yet. Pick them from the list on the left.
                    </li>
                </template>
            </ol>
        </div>
    </div>
</div>
