@props(['test' => null, 'categories' => []])

{{--
    A test either reports one value of its own, or reports through configured
    parameters. The single-value fields collapse away when the second shape is
    chosen, because the parameters then carry that information instead.
--}}

@php($resultType = old('result_type', $test?->result_type?->value ?? App\Enums\TestResultType::Single->value))

<div x-data="{ resultType: '{{ $resultType }}' }" class="space-y-6">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="name" label="Test name" required>
            <x-form.input name="name" :value="$test?->name" required placeholder="Complete Blood Count" />
        </x-form.field>

        <x-form.field name="code" label="Test code" required hint="Unique across the catalogue.">
            <x-form.input name="code" :value="$test?->code" required placeholder="CBC" class="field-control font-mono" />
        </x-form.field>
    </div>

    <x-form.field name="description" label="Description">
        <x-form.textarea name="description" :value="$test?->description" rows="2"
                         placeholder="What this investigation covers." />
    </x-form.field>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-form.field name="category" label="Category" hint="Haematology, Chemistry, Microbiology…">
            <x-form.input name="category" :value="$test?->category" list="test-categories" />
            <datalist id="test-categories">
                @foreach ($categories as $category)
                    <option value="{{ $category }}"></option>
                @endforeach
            </datalist>
        </x-form.field>

        <x-form.field name="specimen_type" label="Specimen type" hint="Whole blood, Serum, Urine…">
            <x-form.input name="specimen_type" :value="$test?->specimen_type" />
        </x-form.field>

        <x-form.field name="turnaround_time_hours" label="Turnaround time (hours)">
            <x-form.input name="turnaround_time_hours" type="number" min="0" max="8760"
                          :value="$test?->turnaround_time_hours" />
        </x-form.field>
    </div>

    <fieldset class="rounded-lg border border-slate-200 p-4">
        <legend class="px-1 text-sm font-semibold text-slate-800">How this test reports</legend>

        <div class="mt-2 space-y-2">
            @foreach (App\Enums\TestResultType::cases() as $case)
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input
                        type="radio"
                        name="result_type"
                        value="{{ $case->value }}"
                        x-model="resultType"
                        @checked($resultType === $case->value)
                        class="mt-0.5 size-4 shrink-0 border-slate-300 text-brand-700 focus:ring-brand-600"
                    />
                    <span class="text-sm">
                        <span class="block font-medium text-slate-700">{{ $case->label() }}</span>
                        <span class="block text-xs text-slate-500">{{ $case->description() }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        @error('result_type')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </fieldset>

    {{-- Only meaningful for a single valued test --}}
    <div x-show="resultType === 'single'" x-cloak class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-sm font-medium text-slate-700">Reference information for the single reported value</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <x-form.field name="unit" label="Unit">
                <x-form.input name="unit" :value="$test?->unit" placeholder="mg/dL" />
            </x-form.field>

            <x-form.field name="reference_low" label="Lower reference value">
                <x-form.input name="reference_low" type="number" step="any" :value="$test?->reference_low" />
            </x-form.field>

            <x-form.field name="reference_high" label="Upper reference value">
                <x-form.input name="reference_high" type="number" step="any" :value="$test?->reference_high" />
            </x-form.field>

            <x-form.field name="decimal_precision" label="Decimal places">
                <x-form.input name="decimal_precision" type="number" min="0" max="6"
                              :value="$test?->decimal_precision ?? 2" required />
            </x-form.field>
        </div>

        <x-form.field name="reference_range" label="Reference range (as printed)"
                      hint="Overrides the lower and upper values on screen and on the report. Leave blank to build it from the values above.">
            <x-form.input name="reference_range" :value="$test?->reference_range" placeholder="70 - 110" />
        </x-form.field>
    </div>

    <div x-show="resultType === 'parameterised'" x-cloak
         class="flex items-start gap-3 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-600/20 ring-inset">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
        <p>
            Unit and reference range are configured on each parameter. After saving, add the parameters this test
            reports — it cannot be requested until it has at least one active parameter.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="display_order" label="Display order" hint="Lower numbers appear first in lists.">
            <x-form.input name="display_order" type="number" min="0" :value="$test?->display_order ?? 0" />
        </x-form.field>

        <div class="flex items-end pb-2">
            <x-form.checkbox
                name="is_active"
                label="Test is active"
                hint="Only active tests can be added to a requisition."
                :checked="$test?->is_active ?? true"
            />
        </div>
    </div>
</div>
