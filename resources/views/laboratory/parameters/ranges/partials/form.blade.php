@props(['parameter', 'range' => null, 'sexes', 'ageUnits', 'rules', 'ageCategories'])

{{--
    One reference range. Age bounds are [from, to): "from" is included, "to"
    is not, so an infant range of 28 d to 1 a hands over to the child range on
    the first birthday without a gap or an overlap. Either bound may be left
    blank for "no limit".
--}}

@php
    $unitSuffix = $parameter->unit ? ' ('.$parameter->unit.')' : '';
    $number = fn ($value) => $value === null ? null : App\Models\LaboratoryReferenceRange::number($value);
@endphp

<div class="space-y-6">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-form.field name="sex" label="Applies to" required hint="Any sex matches every patient; M or F only that sex.">
            <x-form.select name="sex" :options="$sexes" :value="$range?->sex?->value ?? 'any'" required />
        </x-form.field>

        <x-form.field name="age_category" label="Age band" hint="A label only. The ages below decide who matches.">
            <x-form.select name="age_category" :options="$ageCategories" :value="$range?->age_category" placeholder="None" />
        </x-form.field>

        <x-form.field name="display_order" label="Display order">
            <x-form.input name="display_order" type="number" min="0" :value="$range?->display_order" />
        </x-form.field>
    </div>

    <fieldset class="space-y-3 rounded-lg bg-slate-50 p-4">
        <legend class="sr-only">Age interval</legend>
        <p class="text-sm font-medium text-slate-700">Age interval</p>
        <p class="text-xs text-slate-500">
            From is included, to is not. Leave a side blank for no limit, or both blank for every age.
        </p>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-form.field name="age_min" label="From">
                <x-form.input name="age_min" type="number" step="any" min="0" :value="$number($range?->age_min)" />
            </x-form.field>
            <x-form.field name="age_min_unit" label="Unit">
                <x-form.select name="age_min_unit" :options="$ageUnits" :value="$range?->age_min_unit?->value" placeholder="—" />
            </x-form.field>
            <x-form.field name="age_max" label="To (not included)">
                <x-form.input name="age_max" type="number" step="any" min="0" :value="$number($range?->age_max)" />
            </x-form.field>
            <x-form.field name="age_max_unit" label="Unit">
                <x-form.select name="age_max_unit" :options="$ageUnits" :value="$range?->age_max_unit?->value" placeholder="—" />
            </x-form.field>
        </div>
    </fieldset>

    <fieldset class="space-y-3 rounded-lg bg-slate-50 p-4">
        <legend class="sr-only">Limits</legend>
        <p class="text-sm font-medium text-slate-700">Limits{{ $unitSuffix }}</p>
        <p class="text-xs text-slate-500">
            A blank limit is not checked, so an upper limit alone suits a value that is only abnormal when high.
            Critical limits must sit outside the reference range.
        </p>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-form.field name="reference_low" label="Reference low">
                <x-form.input name="reference_low" type="number" step="any" :value="$number($range?->reference_low)" />
            </x-form.field>
            <x-form.field name="reference_high" label="Reference high">
                <x-form.input name="reference_high" type="number" step="any" :value="$number($range?->reference_high)" />
            </x-form.field>
            <x-form.field name="critical_low" label="Critical low">
                <x-form.input name="critical_low" type="number" step="any" :value="$number($range?->critical_low)" />
            </x-form.field>
            <x-form.field name="critical_high" label="Critical high">
                <x-form.input name="critical_high" type="number" step="any" :value="$number($range?->critical_high)" />
            </x-form.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-form.field name="abnormal_when" label="Flag a value when" required
                          hint="Applies to the critical limits too: “above the upper limit only” never raises critical low.">
                <x-form.select name="abnormal_when" :options="$rules" :value="$range?->abnormal_when?->value ?? 'outside_range'" required />
            </x-form.field>
            <x-form.field name="reference_range_text" label="Printed range text"
                          hint="Optional. Replaces the numbers on the report, e.g. “< 200”.">
                <x-form.input name="reference_range_text" :value="$range?->reference_range_text" maxlength="255" />
            </x-form.field>
        </div>
    </fieldset>

    <x-form.field name="notes" label="Notes" hint="Source, method or population the range was validated for.">
        <x-form.textarea name="notes" :value="$range?->notes" rows="2" />
    </x-form.field>

    <div class="flex items-start gap-3 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-900 ring-1 ring-brand-600/20 ring-inset">
        <x-icon name="shield-check" class="mt-0.5 size-5 shrink-0" />
        <p>
            Saving approves this range as it stands. It becomes active, stops being a placeholder, and is recorded as
            verified by you today. Results already reported keep the range they were issued with.
        </p>
    </div>
</div>
