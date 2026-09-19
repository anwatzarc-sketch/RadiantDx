@props(['parameter' => null, 'tests', 'selectedTestId' => null])

{{--
    The fields that apply depend on the data type: reference bounds belong to
    numeric parameters, the abnormal outcome rule to the yes/no shapes, and the
    value list to dropdowns.
--}}

@php
    $dataType = old('data_type', $parameter?->data_type?->value ?? App\Enums\ParameterDataType::Numeric->value);

    $optionRows = old('options', $parameter?->options
        ->map(fn ($option) => [
            'value' => $option->value,
            'label' => $option->label,
            'is_abnormal' => (bool) $option->is_abnormal,
        ])->values()->all() ?? []);
@endphp

<div x-data="{ dataType: '{{ $dataType }}' }" class="space-y-6">

    <x-form.field name="laboratory_test_id" label="Laboratory test" required
                  hint="The test this parameter is reported under.">
        <x-form.select
            name="laboratory_test_id"
            :value="$parameter?->laboratory_test_id ?? $selectedTestId"
            placeholder="Select a test"
            :options="$tests->mapWithKeys(fn ($test) => [$test->id => $test->name.' ('.$test->code.')'])->all()"
            required
        />
    </x-form.field>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="name" label="Parameter name" required>
            <x-form.input name="name" :value="$parameter?->name" required placeholder="Hemoglobin" />
        </x-form.field>

        <x-form.field name="code" label="Parameter code" required hint="Unique within the test.">
            <x-form.input name="code" :value="$parameter?->code" required placeholder="HGB" class="field-control font-mono" />
        </x-form.field>
    </div>

    <x-form.field name="description" label="Description">
        <x-form.textarea name="description" :value="$parameter?->description" rows="2" />
    </x-form.field>

    <x-form.field name="data_type" label="Data type" required hint="Decides how the value is entered and validated.">
        <select name="data_type" id="data_type" x-model="dataType" class="field-control" required>
            @foreach (App\Enums\ParameterDataType::options() as $value => $label)
                <option value="{{ $value }}" @selected($dataType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-form.field>

    {{-- Numeric: reference and critical bounds --}}
    <div x-show="dataType === 'numeric'" x-cloak class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-sm font-medium text-slate-700">Reference range</p>
        <p class="text-xs text-slate-500">
            Used to suggest an interpretation when a value is entered. The suggestion never replaces the value the
            laboratory records.
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-form.field name="unit" label="Unit">
                <x-form.input name="unit" :value="$parameter?->unit" placeholder="g/dL" />
            </x-form.field>

            <x-form.field name="reference_low" label="Lower reference value">
                <x-form.input name="reference_low" type="number" step="any" :value="$parameter?->reference_low" />
            </x-form.field>

            <x-form.field name="reference_high" label="Upper reference value">
                <x-form.input name="reference_high" type="number" step="any" :value="$parameter?->reference_high" />
            </x-form.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-form.field name="critical_low" label="Critical low" hint="Below this the value is flagged critical.">
                <x-form.input name="critical_low" type="number" step="any" :value="$parameter?->critical_low" />
            </x-form.field>

            <x-form.field name="critical_high" label="Critical high" hint="Above this the value is flagged critical.">
                <x-form.input name="critical_high" type="number" step="any" :value="$parameter?->critical_high" />
            </x-form.field>

            <x-form.field name="decimal_precision" label="Decimal places">
                <x-form.input name="decimal_precision" type="number" min="0" max="6"
                              :value="$parameter?->decimal_precision ?? 2" required />
            </x-form.field>
        </div>

        <x-form.field name="reference_range" label="Reference range (as printed)"
                      hint="Overrides the bounds above on screen and on the report.">
            <x-form.input name="reference_range" :value="$parameter?->reference_range" placeholder="12 - 16" />
        </x-form.field>
    </div>

    {{-- Fixed outcome types: which outcome counts as abnormal --}}
    <div x-show="dataType === 'boolean' || dataType === 'positive_negative'" x-cloak
         class="space-y-4 rounded-lg bg-slate-50 p-4">
        <p class="text-sm font-medium text-slate-700">Interpretation rule</p>

        <x-form.field name="abnormal_when" label="Flag as abnormal when the value is"
                      hint="Leave blank to report the outcome without an abnormal flag.">
            <select name="abnormal_when" id="abnormal_when" class="field-control">
                <option value="">No rule</option>
                <optgroup label="Boolean" x-show="dataType === 'boolean'">
                    <option value="yes" @selected(old('abnormal_when', $parameter?->abnormal_when) === 'yes')>Yes</option>
                    <option value="no" @selected(old('abnormal_when', $parameter?->abnormal_when) === 'no')>No</option>
                </optgroup>
                <optgroup label="Positive / Negative" x-show="dataType === 'positive_negative'">
                    <option value="positive" @selected(old('abnormal_when', $parameter?->abnormal_when) === 'positive')>Positive</option>
                    <option value="negative" @selected(old('abnormal_when', $parameter?->abnormal_when) === 'negative')>Negative</option>
                </optgroup>
            </select>
        </x-form.field>
    </div>

    {{-- Dropdown: the catalogue of selectable values --}}
    <div x-show="dataType === 'dropdown'" x-cloak class="space-y-3 rounded-lg bg-slate-50 p-4"
         x-data="optionRows(@js($optionRows))">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-700">Selectable values</p>
                <p class="text-xs text-slate-500">Offered to the laboratory when the result is entered.</p>
            </div>
            <x-button type="button" size="sm" icon="plus" x-on:click="add()">Add value</x-button>
        </div>

        @error('options')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <template x-for="(row, index) in rows" :key="index">
            <div class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-3 ring-1 ring-slate-200 ring-inset">
                <div class="min-w-32 flex-1">
                    <label class="field-label" :for="'option-value-' + index">Stored value</label>
                    <input type="text" class="field-control mt-1 font-mono" maxlength="255"
                           :id="'option-value-' + index" :name="'options[' + index + '][value]'" x-model="row.value" />
                </div>
                <div class="min-w-32 flex-1">
                    <label class="field-label" :for="'option-label-' + index">Shown as</label>
                    <input type="text" class="field-control mt-1" maxlength="255"
                           :id="'option-label-' + index" :name="'options[' + index + '][label]'" x-model="row.label" />
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input type="hidden" :name="'options[' + index + '][is_abnormal]'" value="0" />
                    <input type="checkbox" value="1" class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-600"
                           :name="'options[' + index + '][is_abnormal]'" x-model="row.is_abnormal" />
                    Abnormal
                </label>
                <button type="button" class="mb-1.5 rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        x-on:click="remove(index)">
                    <span class="sr-only">Remove value</span>
                    <x-icon name="trash" class="size-4" />
                </button>
            </div>
        </template>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="display_order" label="Display order" hint="Order in which the value is reported.">
            <x-form.input name="display_order" type="number" min="0" :value="$parameter?->display_order ?? 0" />
        </x-form.field>

        <div class="flex items-end pb-2">
            <x-form.checkbox
                name="is_active"
                label="Parameter is active"
                hint="Only active parameters are reported on new results."
                :checked="$parameter?->is_active ?? true"
            />
        </div>
    </div>
</div>
