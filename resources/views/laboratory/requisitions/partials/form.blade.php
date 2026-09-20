@props(['requisition', 'availableTests', 'availablePanels', 'selected' => []])

{{--
    The requisition form follows the order of the request itself: who the
    patient is, who is asking and why, and what the laboratory should perform.
--}}

@php
    $catalogue = collect()
        ->concat($availablePanels->map(fn ($panel) => [
            'key' => 'panel:'.$panel->id,
            'type' => 'Panel',
            'name' => $panel->name,
            'code' => $panel->code,
            'category' => $panel->category,
            'detail' => $panel->activeTests->pluck('name')->implode(', '),
            'count' => $panel->activeTests->count(),
        ]))
        ->concat($availableTests->map(fn ($test) => [
            'key' => 'test:'.$test->id,
            'type' => 'Test',
            'name' => $test->name,
            'code' => $test->code,
            'category' => $test->category,
            'detail' => $test->specimen_type,
            'count' => 1,
        ]))
        ->values();

    $initial = array_values((array) old('investigations', $selected));
@endphp

<div class="space-y-6">

    {{-- 1. Patient --}}
    <x-card title="Patient" subtitle="Who the specimen belongs to" icon="profile">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-form.field name="patient_identifier" label="Patient ID" required
                          hint="Hospital or clinic number for this patient.">
                <x-form.input name="patient_identifier" :value="$requisition->patient_identifier" required />
            </x-form.field>

            <x-form.field name="patient_name" label="Patient name" required>
                <x-form.input name="patient_name" :value="$requisition->patient_name" required />
            </x-form.field>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-form.field name="patient_gender" label="Gender">
                <x-form.select
                    name="patient_gender"
                    :value="$requisition->patient_gender?->value"
                    placeholder="Not recorded"
                    :options="App\Enums\Gender::options()"
                />
            </x-form.field>

            <x-form.field name="patient_date_of_birth" label="Date of birth">
                <x-form.input name="patient_date_of_birth" type="date"
                              :value="$requisition->patient_date_of_birth?->format('Y-m-d')"
                              max="{{ today()->format('Y-m-d') }}" />
            </x-form.field>

            <x-form.field name="patient_age_years" label="Age (years)"
                          hint="Use this when the date of birth is unknown.">
                <x-form.input name="patient_age_years" type="number" min="0" max="130"
                              :value="$requisition->patient_age_years" />
            </x-form.field>
        </div>
    </x-card>

    {{-- 2. Request information --}}
    <x-card title="Request information" subtitle="Who is asking, and how urgently" icon="requisition">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.field name="requested_date" label="Request date" required>
                <x-form.input name="requested_date" type="date"
                              :value="($requisition->requested_date ?? today())->format('Y-m-d')"
                              max="{{ today()->format('Y-m-d') }}" required />
            </x-form.field>

            {{--
                The requesting clinician is no longer typed. It is resolved from
                the signed-in account by RequisitionService and frozen onto the
                requisition, so the report names whoever actually raised it.

                This block is informational: the server resolves identity
                independently and ignores anything sent for it.
            --}}
            <div>
                <span class="field-label">Requesting clinician</span>
                <div class="mt-1 flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-200 ring-inset">
                    @if ($requisition->exists && $requisition->actorDisplayName('requested_by'))
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">
                                {{ $requisition->actorDisplayName('requested_by') }}
                            </p>
                            @if ($requisition->actorSpecialityLabel('requested_by'))
                                <p class="truncate text-xs text-slate-500">
                                    {{ $requisition->actorSpecialityLabel('requested_by') }}
                                </p>
                            @endif
                        </div>
                    @elseif (auth()->user()?->staff)
                        <x-staff-avatar :staff="auth()->user()->staff" size="xs" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">
                                {{ auth()->user()->staff->displayName() }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                Automatically identified from your account.
                            </p>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Identified from your account when saved.</p>
                    @endif
                </div>
            </div>

            <x-form.field name="requesting_department" label="Department">
                <x-form.input name="requesting_department" :value="$requisition->requesting_department" />
            </x-form.field>

            <x-form.field name="priority" label="Priority" required>
                <x-form.select
                    name="priority"
                    :value="$requisition->priority?->value ?? App\Enums\RequisitionPriority::Routine->value"
                    :options="App\Enums\RequisitionPriority::options()"
                    required
                />
            </x-form.field>
        </div>
    </x-card>

    {{-- 3. Laboratory request --}}
    <x-card
        title="Laboratory request"
        subtitle="Search the catalogue and build the list of investigations"
        icon="beaker"
    >
        @if ($catalogue->isEmpty())
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/30 ring-inset">
                <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">The catalogue has nothing available to request</p>
                    <p class="mt-0.5">
                        Add active laboratory tests, and give multi-parameter tests at least one active parameter,
                        before creating requisitions.
                    </p>
                </div>
            </div>
        @else
            <div x-data="investigationPicker(@js($catalogue), @js($initial))">
                @error('investigations')
                    <p class="field-error mb-3">{{ $message }}</p>
                @enderror

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                    {{-- Catalogue search --}}
                    <div class="rounded-lg border border-slate-200">
                        <div class="border-b border-slate-200 p-3">
                            <label for="investigation-search" class="sr-only">Search tests and panels</label>
                            <div class="relative">
                                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                                <input type="search" id="investigation-search" x-model="query" autocomplete="off"
                                       placeholder="Search tests and panels" class="field-control pl-9" />
                            </div>
                        </div>

                        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            <template x-for="entry in results" :key="entry.key">
                                <li>
                                    <button type="button" x-on:click="add(entry.key)"
                                            class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left hover:bg-brand-50">
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1.5">
                                                <span class="badge"
                                                      :class="entry.type === 'Panel'
                                                          ? 'bg-indigo-100 text-indigo-800 ring-indigo-600/20'
                                                          : 'bg-slate-100 text-slate-700 ring-slate-500/20'"
                                                      x-text="entry.type"></span>
                                                <span class="truncate text-sm font-medium text-slate-800" x-text="entry.name"></span>
                                            </span>
                                            <span class="mt-0.5 block truncate font-mono text-xs text-slate-500" x-text="entry.code"></span>
                                            <template x-if="entry.detail">
                                                <span class="mt-0.5 block truncate text-xs text-slate-500" x-text="entry.detail"></span>
                                            </template>
                                        </span>
                                        <x-icon name="plus" class="mt-1 size-4 shrink-0 text-brand-600" />
                                    </button>
                                </li>
                            </template>
                            <template x-if="results.length === 0">
                                <li class="px-3 py-8 text-center text-sm text-slate-500">
                                    Nothing else matches this search.
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Selected investigations --}}
                    <div class="rounded-lg border border-slate-200">
                        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5">
                            <p class="text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                Selected investigations
                            </p>
                            <p class="text-xs text-slate-500">
                                <span x-text="selected.length" class="font-semibold text-slate-700"></span> selected
                            </p>
                        </div>

                        <ol class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            <template x-for="(entry, index) in selectedEntries" :key="entry.key">
                                <li class="flex items-start gap-2 px-3 py-2.5">
                                    <input type="hidden" name="investigations[]" :value="entry.key" />
                                    <span class="w-5 shrink-0 pt-0.5 text-xs font-semibold text-slate-400 tabular-nums"
                                          x-text="index + 1"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1.5">
                                            <span class="badge"
                                                  :class="entry.type === 'Panel'
                                                      ? 'bg-indigo-100 text-indigo-800 ring-indigo-600/20'
                                                      : 'bg-slate-100 text-slate-700 ring-slate-500/20'"
                                                  x-text="entry.type"></span>
                                            <span class="truncate text-sm font-medium text-slate-800" x-text="entry.name"></span>
                                        </span>
                                        <template x-if="entry.type === 'Panel'">
                                            <span class="mt-0.5 block text-xs text-slate-500">
                                                Expands to <span x-text="entry.count"></span> tests:
                                                <span x-text="entry.detail"></span>
                                            </span>
                                        </template>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-0.5">
                                        <button type="button" x-on:click="move(entry.key, -1)" :disabled="index === 0"
                                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30">
                                            <span class="sr-only">Move up</span>
                                            <x-icon name="arrow-up" class="size-3.5" />
                                        </button>
                                        <button type="button" x-on:click="move(entry.key, 1)" :disabled="index === selected.length - 1"
                                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30">
                                            <span class="sr-only">Move down</span>
                                            <x-icon name="arrow-down" class="size-3.5" />
                                        </button>
                                        <button type="button" x-on:click="remove(entry.key)"
                                                class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                                            <span class="sr-only">Remove</span>
                                            <x-icon name="close" class="size-3.5" />
                                        </button>
                                    </span>
                                </li>
                            </template>
                            <template x-if="selected.length === 0">
                                <li class="px-3 py-12 text-center text-sm text-slate-500">
                                    Nothing selected yet. A draft can be saved empty, but a requisition must carry at
                                    least one investigation before it is submitted.
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>
            </div>
        @endif
    </x-card>

    {{-- 4. Clinical information --}}
    <x-card title="Clinical information" subtitle="Why the investigation was requested" icon="document">
        <div class="space-y-4">
            <x-form.field name="clinical_indication" label="Clinical indication"
                          hint="The clinical question this request is meant to answer.">
                <x-form.textarea name="clinical_indication" :value="$requisition->clinical_indication" rows="2" />
            </x-form.field>

            <x-form.field name="clinical_notes" label="Clinical notes"
                          hint="Anything the laboratory should know, such as current treatment.">
                <x-form.textarea name="clinical_notes" :value="$requisition->clinical_notes" rows="3" />
            </x-form.field>
        </div>
    </x-card>
</div>
