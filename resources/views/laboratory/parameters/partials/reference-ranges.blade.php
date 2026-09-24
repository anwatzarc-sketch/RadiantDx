@props(['parameter'])

{{--
    A numeric parameter's reference ranges, on its show and edit pages.

    Seeded ranges arrive as placeholders: textbook values nobody here has
    approved, which production also seeds inactive. The laboratory director
    verifies them one at a time, a ticked selection at once, or all of a
    parameter's placeholders together. Verifying clears the placeholder,
    activates the range and records who approved it.

    The checkboxes live in the table but belong to the bulk form through the
    form attribute, so each row's own action forms are never nested inside it.
--}}

@php
    $ranges = $parameter->referenceRanges;
    $awaiting = $ranges->where('is_placeholder', true)->count();
    $bulkFormId = 'range-bulk-verify-'.$parameter->id;
    $canVerify = auth()->user()?->can('create', App\Models\LaboratoryReferenceRange::class);
@endphp

<x-card title="Reference ranges" subtitle="By sex and age; the most specific match is used" icon="sliders" :padded="false">
    <x-slot:actions>
        @if ($awaiting > 0)
            <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="warning">
                {{ trans_choice('{1} 1 range awaiting verification|[2,*] :count ranges awaiting verification', $awaiting, ['count' => $awaiting]) }}
            </x-badge>
        @endif
        @can('create', App\Models\LaboratoryReferenceRange::class)
            <x-button size="sm" icon="plus" :href="route('laboratory.parameters.ranges.create', $parameter)">Add range</x-button>
        @endcan
    </x-slot:actions>

    @if ($ranges->isEmpty())
        <x-empty-state
            icon="sliders"
            title="No age- or sex-specific ranges"
            description="Every patient is judged against the adult default range above, except a child, who gets no range until one is added here."
        />
    @else
        <div class="data-table-container rounded-none border-0 shadow-none">
            <table class="data-table">
                <thead>
                    <tr>
                        @if ($canVerify)
                            <th scope="col" class="w-8"><span class="sr-only">Select</span></th>
                        @endif
                        <th scope="col">Sex</th>
                        <th scope="col">Age</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Critical</th>
                        <th scope="col">Flag when</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranges as $range)
                        <tr @class(['opacity-60' => ! $range->is_active])>
                            @if ($canVerify)
                                <td>
                                    @if ($range->is_placeholder)
                                        <input type="checkbox" name="ranges[]" value="{{ $range->id }}" form="{{ $bulkFormId }}"
                                               class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-600"
                                               aria-label="Select {{ $range->resultLabel() }}">
                                    @endif
                                </td>
                            @endif
                            <td>{{ $range->sex->label() }}</td>
                            <td class="whitespace-nowrap">
                                {{ $range->ageLabel() }}
                                @if ($range->age_category)
                                    <span class="block text-xs text-slate-500">{{ Str::headline($range->age_category) }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap font-mono">{{ $range->valuesLabel() }}</td>
                            <td class="whitespace-nowrap font-mono text-slate-600">
                                @if ($range->critical_low !== null || $range->critical_high !== null)
                                    {{ $range->critical_low !== null ? '< '.$range::number($range->critical_low) : '' }}
                                    {{ $range->critical_low !== null && $range->critical_high !== null ? '·' : '' }}
                                    {{ $range->critical_high !== null ? '> '.$range::number($range->critical_high) : '' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-slate-600">{{ $range->abnormal_when?->label() ?? 'Outside the range' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ($range->is_placeholder)
                                        <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30">Placeholder</x-badge>
                                    @else
                                        <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20" icon="check-circle">Verified</x-badge>
                                    @endif
                                    @unless ($range->is_active)
                                        <x-badge>Inactive</x-badge>
                                    @endunless
                                </div>
                                @if ($range->verified_at)
                                    <span class="mt-1 block text-xs text-slate-500">
                                        {{ $range->actorDisplayName('verified_by') }}, {{ $range->verified_at->format('d M Y') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    @can('verify', $range)
                                        <form method="POST" action="{{ route('laboratory.parameters.ranges.verify', [$parameter, $range]) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="success" icon="check">Verify</x-button>
                                        </form>
                                    @endcan
                                    @can('update', $range)
                                        <x-button size="sm" icon="pencil" :href="route('laboratory.parameters.ranges.edit', [$parameter, $range])">
                                            <span class="sr-only sm:not-sr-only">Edit</span>
                                        </x-button>
                                    @endcan
                                    @can('activate', $range)
                                        <form method="POST" action="{{ route('laboratory.parameters.ranges.activate', [$parameter, $range]) }}">
                                            @csrf @method('PATCH')
                                            <x-button type="submit" size="sm">Activate</x-button>
                                        </form>
                                    @endcan
                                    @can('deactivate', $range)
                                        <x-confirm-action
                                            :action="route('laboratory.parameters.ranges.deactivate', [$parameter, $range])"
                                            method="PATCH"
                                            variant="secondary"
                                            title="Deactivate this range?"
                                            :message="$range->resultLabel().' will not be used for new results. Results already reported keep their own copy.'"
                                            confirm="Deactivate"
                                        >Deactivate</x-confirm-action>
                                    @endcan
                                    @can('delete', $range)
                                        <x-confirm-action
                                            :action="route('laboratory.parameters.ranges.destroy', [$parameter, $range])"
                                            method="DELETE"
                                            title="Delete this range?"
                                            :message="$range->resultLabel().' will be removed. Results already reported keep their own copy of its values.'"
                                            confirm="Delete range"
                                            icon="trash"
                                        ><span class="sr-only">Delete</span></x-confirm-action>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($canVerify && $awaiting > 0)
            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-600">
                    Verifying a range approves its values as they stand, activates it, and records you as its verifier.
                </p>
                <div class="flex flex-wrap gap-2">
                    <form id="{{ $bulkFormId }}" method="POST" action="{{ route('laboratory.parameters.ranges.verify-many', $parameter) }}">
                        @csrf
                        <input type="hidden" name="scope" value="selected">
                        <x-button type="submit" size="sm" icon="check">Verify selected</x-button>
                    </form>
                    <x-confirm-action
                        :action="route('laboratory.parameters.ranges.verify-many', [$parameter, 'scope' => 'all'])"
                        variant="primary"
                        icon="check-circle"
                        :title="'Verify all '.$awaiting.' placeholder ranges?'"
                        message="Each one is approved as it stands, activated, and recorded as verified by you. Check the values first: they are textbook placeholders."
                        confirm="Verify all"
                    >Verify all for this parameter</x-confirm-action>
                </div>
            </div>
        @endif
    @endif
</x-card>
