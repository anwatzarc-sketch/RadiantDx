@props([
    'enum',
    'name',
    'value' => null,
    'placeholder' => 'Select…',
    'parent' => null,
    'parentField' => null,
    'nullable' => true,
    'disabled' => false,
    'id' => null,
])

{{--
    The one searchable selector for every shared controlled vocabulary.

    Options are never written into a view. They are fetched from the enum
    catalogue endpoint, so a vocabulary has a single definition and no view can
    drift from it.

    Two modes:
      * flat        — <x-form.shared-enum-select enum="Speciality" name="speciality" />
      * dependent   — pass parent-field to follow another select; the list
                      reloads when that field changes and clears if the chosen
                      value no longer belongs to the new parent.

    Alpine only, matching investigationPicker/testPicker in resources/js/app.js.
    The real value is carried by a hidden input, so this posts like any other
    form field and is validated server-side by App\Rules\SharedEnumValue — the
    search box is a convenience, never the authority.
--}}

@php
    $fieldId = $id ?? $name;
    $hasError = $errors->has($name);
@endphp

<div
    x-data="sharedEnumSelect({
        enumName: @js($enum),
        endpoint: @js(url('/enums')),
        initial: @js(old($name, $value)),
        parent: @js($parent),
        parentField: @js($parentField),
        nullable: @js((bool) $nullable),
    })"
    x-on:shared-enum-changed.window="onSiblingChanged($event)"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" x-model="selected" />

    <button
        type="button"
        id="{{ $fieldId }}"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="open ? move(1) : toggle()"
        x-on:keydown.escape="close()"
        :disabled="isDisabled"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        @class([
            'field-control flex w-full items-center justify-between gap-2 text-left',
            'field-control-invalid' => $hasError,
        ])
        @disabled($disabled)
    >
        <span class="truncate" x-text="selectedLabel || @js($placeholder)"
              :class="selectedLabel ? 'text-slate-900' : 'text-slate-400'"></span>

        <span class="flex shrink-0 items-center gap-1">
            <template x-if="selected && nullable && ! isDisabled">
                <span role="button" tabindex="-1" x-on:click.stop="clear()"
                      class="rounded p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                      aria-label="Clear selection">
                    <x-icon name="close" class="size-3.5" />
                </span>
            </template>
            <x-icon name="chevron-down" class="size-4 text-slate-400" />
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        x-on:click.outside="close()"
        class="absolute z-40 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        role="listbox"
    >
        <div class="border-b border-slate-100 p-2">
            <input
                type="text"
                x-ref="search"
                x-model="query"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose(results[highlighted])"
                x-on:keydown.escape.prevent="close()"
                class="field-control"
                placeholder="Search…"
                autocomplete="off"
            />
        </div>

        <template x-if="loading">
            <p class="px-3 py-6 text-center text-sm text-slate-500">Loading…</p>
        </template>

        <template x-if="! loading && failed">
            <p class="px-3 py-6 text-center text-sm text-rose-600">
                Could not load options.
                <button type="button" x-on:click="load()" class="font-semibold underline">Retry</button>
            </p>
        </template>

        <template x-if="! loading && ! failed && results.length === 0">
            <p class="px-3 py-6 text-center text-sm text-slate-500"
               x-text="waitingForParent ? 'Choose a speciality first.' : 'Nothing matches that search.'"></p>
        </template>

        <ul x-show="! loading && ! failed && results.length > 0" class="max-h-64 overflow-y-auto py-1">
            <template x-for="(option, index) in results" :key="option.value">
                <li>
                    <button
                        type="button"
                        x-on:click="choose(option)"
                        x-on:mousemove="highlighted = index"
                        :class="{
                            'bg-brand-50 text-brand-900': index === highlighted,
                            'font-semibold': option.value === selected,
                        }"
                        class="tap-target flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-slate-700"
                        role="option"
                        :aria-selected="(option.value === selected).toString()"
                    >
                        <span class="truncate" x-text="option.text"></span>
                        <template x-if="option.value === selected">
                            <x-icon name="check" class="size-4 shrink-0 text-brand-700" />
                        </template>
                    </button>
                </li>
            </template>
        </ul>
    </div>
</div>
