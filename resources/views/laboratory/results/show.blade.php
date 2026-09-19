@php
    use App\Enums\Interpretation;
    use App\Enums\ParameterDataType;

    $requisition = $result->requisition;
    $canEdit = auth()->user()->can('update', $result);
    $entered = $result->enteredParameterCount();
    $total = $result->parameters->count();
@endphp

<x-layouts.admin
    :title="$result->result_number"
    :breadcrumbs="['Laboratory' => null, 'Results' => route('laboratory.results.index'), $result->result_number => null]"
>

    <x-page-header
        :title="$result->investigationLabel()"
        :subtitle="$requisition->patient_name.' · '.$requisition->patient_identifier"
        :back="route('laboratory.results.index')"
        back-label="Back to results"
    >
        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $result->result_number }}</x-badge>
            <x-status-badge :status="$result->status" />
            <x-status-badge :status="$result->validation_status" :icon="$result->isValidated() ? 'check-circle' : 'clock'" />
            @if ($result->revision > 1)
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="undo">
                    Revision {{ $result->revision }}
                </x-badge>
            @endif
            @if ($result->hasCriticalValues())
                <x-badge classes="bg-rose-100 text-rose-800 ring-rose-600/20" icon="warning">Critical value</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            @can('validate', $result)
                <x-confirm-action
                    :action="route('laboratory.results.validate', $result)"
                    size="md"
                    variant="success"
                    title="Validate this result?"
                    message="Validating releases the result for reporting and closes it to ordinary editing. A correction afterwards requires an explicit unvalidation."
                    confirm="Validate and release"
                    icon="check-circle"
                >Validate</x-confirm-action>
            @endcan

            @can('unvalidate', $result)
                <x-confirm-action
                    :action="route('laboratory.results.unvalidate', $result)"
                    size="md"
                    variant="secondary"
                    title="Unvalidate for correction?"
                    message="The result reopens for editing and its revision number increases. The current validation is recorded in the audit trail."
                    confirm="Unvalidate"
                    icon="undo"
                    :reason="true"
                    reason-label="Reason for correction"
                >Unvalidate</x-confirm-action>
            @endcan

            @can('print', $result)
                <x-button :href="route('laboratory.results.print', $result)" target="_blank" icon="print">Print report</x-button>
            @endcan

            @can('delete', $result)
                <x-confirm-action
                    :action="route('laboratory.results.destroy', $result)"
                    method="DELETE"
                    size="md"
                    title="Delete this result?"
                    message="The result and its entered values will be removed. The investigation returns to the laboratory queue."
                    confirm="Delete result"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($result->isValidated())
        <div class="flex items-start gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-600/20 ring-inset">
            <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">This result is validated and finalised</p>
                <p class="mt-0.5">
                    Validated by {{ $result->validatedBy?->name ?? 'a former user' }} on
                    {{ $result->validated_at?->format('d M Y H:i') }}. Ordinary editing is closed; a correction
                    requires an explicit unvalidation, which is recorded.
                </p>
            </div>
        </div>
    @elseif ($requisition->isCancelled())
        <div class="flex items-start gap-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-600/20 ring-inset">
            <x-icon name="ban" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">The requisition for this result has been cancelled</p>
                <p class="mt-0.5">No further laboratory work can be recorded against it.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Patient and requisition context --}}
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-card title="Patient information" icon="profile">
                    <x-detail-list :columns="1">
                        <x-detail label="Patient name" :value="$requisition->patient_name" />
                        <x-detail label="Patient ID" :value="$requisition->patient_identifier" mono />
                        <x-detail label="Gender" :value="$requisition->patient_gender?->label()" />
                        <x-detail label="Age" :value="$requisition->ageLabel()" />
                    </x-detail-list>
                </x-card>

                <x-card title="Requisition information" icon="requisition">
                    <x-detail-list :columns="1">
                        <x-detail label="Requisition number">
                            @can('view', $requisition)
                                <a href="{{ route('laboratory.requisitions.show', $requisition) }}"
                                   class="font-mono text-brand-700 hover:underline">{{ $requisition->requisition_number }}</a>
                            @else
                                <span class="font-mono">{{ $requisition->requisition_number }}</span>
                            @endcan
                        </x-detail>
                        <x-detail label="Request date" :value="$requisition->requested_date?->format('d M Y')" />
                        <x-detail label="Requesting clinician" :value="$requisition->requesting_clinician" />
                        <x-detail label="Priority" :value="$requisition->priority->label()" />
                    </x-detail-list>
                </x-card>
            </div>

            {{-- Investigation --}}
            <x-card title="Investigation" icon="beaker">
                <x-detail-list :columns="4">
                    <x-detail label="Test" :value="$result->test_name" />
                    <x-detail label="Code" :value="$result->test_code" mono />
                    <x-detail label="Panel" :value="$result->panel_name" />
                    <x-detail label="Specimen" :value="$result->specimen_type" />
                </x-detail-list>
            </x-card>

            {{-- Result parameters: entry or read-only --}}
            <form method="POST" action="{{ route('laboratory.results.update', $result) }}">
                @csrf
                @method('PUT')

                <x-card
                    title="Result parameters"
                    :subtitle="$entered.' of '.$total.' values entered'"
                    icon="parameter"
                    :padded="false"
                >
                    <x-slot:actions>
                        @if ($canEdit)
                            <x-button type="submit" variant="primary" size="sm" icon="check">Save results</x-button>
                        @endif
                    </x-slot:actions>

                    @if ($result->parameters->isEmpty())
                        <x-empty-state
                            icon="parameter"
                            title="No reportable values"
                            description="This result has no parameters, which usually means the test lost its active parameters after the result was opened."
                        />
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($result->parameters as $parameter)
                                @php
                                    $low = $parameter->reference_low !== null ? (float) $parameter->reference_low : null;
                                    $high = $parameter->reference_high !== null ? (float) $parameter->reference_high : null;
                                    $criticalLow = $parameter->critical_low !== null ? (float) $parameter->critical_low : null;
                                    $criticalHigh = $parameter->critical_high !== null ? (float) $parameter->critical_high : null;
                                    $choices = $parameter->selectableValues();
                                    $field = 'values.'.$parameter->id.'.value';
                                @endphp

                                <div
                                    class="px-4 py-4 sm:px-5"
                                    x-data="{
                                        value: @js((string) ($parameter->result_value ?? '')),
                                        low: @js($low),
                                        high: @js($high),
                                        criticalLow: @js($criticalLow),
                                        criticalHigh: @js($criticalHigh),
                                        numeric: @js($parameter->data_type === ParameterDataType::Numeric),
                                        get suggestion() {
                                            if (! this.numeric || this.value.trim() === '') return null;
                                            const parsed = parseFloat(this.value.replace(/^[<>]=?/, '').replace(',', '.'));
                                            if (Number.isNaN(parsed)) return null;
                                            if (this.criticalLow !== null && parsed < this.criticalLow) return ['Critical low', 'bg-rose-100 text-rose-800 ring-rose-600/20'];
                                            if (this.criticalHigh !== null && parsed > this.criticalHigh) return ['Critical high', 'bg-rose-100 text-rose-800 ring-rose-600/20'];
                                            if (this.low !== null && parsed < this.low) return ['Low', 'bg-amber-100 text-amber-900 ring-amber-600/30'];
                                            if (this.high !== null && parsed > this.high) return ['High', 'bg-amber-100 text-amber-900 ring-amber-600/30'];
                                            if (this.low === null && this.high === null) return null;
                                            return ['Normal', 'bg-emerald-100 text-emerald-800 ring-emerald-600/20'];
                                        }
                                    }"
                                >
                                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

                                        <div class="lg:col-span-3">
                                            <p class="text-sm font-semibold text-slate-900">{{ $parameter->parameter_name }}</p>
                                            <p class="font-mono text-xs text-slate-500">{{ $parameter->parameter_code }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $parameter->data_type->label() }}</p>
                                        </div>

                                        {{-- Value --}}
                                        <div class="lg:col-span-3">
                                            <label for="{{ $field }}" class="field-label">Result</label>

                                            @if ($canEdit)
                                                @if ($choices !== [])
                                                    <select
                                                        name="values[{{ $parameter->id }}][value]"
                                                        id="{{ $field }}"
                                                        x-model="value"
                                                        class="field-control mt-1 {{ $errors->has($field) ? 'field-control-invalid' : '' }}"
                                                    >
                                                        <option value="">Not entered</option>
                                                        @foreach ($choices as $choiceValue => $choiceLabel)
                                                            <option value="{{ $choiceValue }}"
                                                                @selected(old($field, $parameter->result_value) === (string) $choiceValue)>
                                                                {{ $choiceLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($parameter->data_type === ParameterDataType::Text)
                                                    <textarea
                                                        name="values[{{ $parameter->id }}][value]"
                                                        id="{{ $field }}"
                                                        rows="3"
                                                        x-model="value"
                                                        class="field-control mt-1 {{ $errors->has($field) ? 'field-control-invalid' : '' }}"
                                                    >{{ old($field, $parameter->result_value) }}</textarea>
                                                @else
                                                    <input
                                                        type="text"
                                                        inputmode="decimal"
                                                        name="values[{{ $parameter->id }}][value]"
                                                        id="{{ $field }}"
                                                        value="{{ old($field, $parameter->result_value) }}"
                                                        x-model="value"
                                                        placeholder="{{ $parameter->decimal_precision > 0 ? '0.'.str_repeat('0', $parameter->decimal_precision) : '0' }}"
                                                        class="field-control mt-1 font-mono {{ $errors->has($field) ? 'field-control-invalid' : '' }}"
                                                    />
                                                @endif

                                                @error($field)
                                                    <p class="field-error">
                                                        <x-icon name="warning" class="mt-px size-3.5 shrink-0" />
                                                        <span>{{ $message }}</span>
                                                    </p>
                                                @enderror
                                            @else
                                                <p class="mt-1 font-mono text-sm text-slate-900">
                                                    {{ $parameter->displayValue() ?: 'Not entered' }}
                                                </p>
                                            @endif
                                        </div>

                                        {{-- Unit and reference --}}
                                        <div class="lg:col-span-3">
                                            <p class="field-label">Unit and reference</p>
                                            <p class="mt-1 text-sm text-slate-700">{{ $parameter->unit ?: '—' }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $parameter->referenceSummary() ?: 'No reference range configured' }}
                                            </p>
                                        </div>

                                        {{-- Interpretation --}}
                                        <div class="lg:col-span-3">
                                            <label for="values.{{ $parameter->id }}.interpretation" class="field-label">
                                                Interpretation
                                            </label>

                                            @if ($canEdit)
                                                <select
                                                    name="values[{{ $parameter->id }}][interpretation]"
                                                    id="values.{{ $parameter->id }}.interpretation"
                                                    class="field-control mt-1"
                                                >
                                                    <option value="">Use the suggested flag</option>
                                                    @foreach (Interpretation::options() as $value => $label)
                                                        <option value="{{ $value }}"
                                                            @selected(old('values.'.$parameter->id.'.interpretation', $parameter->interpretation?->value) === $value)>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                {{-- Live suggestion from the reference range. Advisory only. --}}
                                                <template x-if="suggestion">
                                                    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500">
                                                        <span>Suggested:</span>
                                                        <span class="badge" :class="suggestion[1]" x-text="suggestion[0]"></span>
                                                    </p>
                                                </template>
                                            @else
                                                <div class="mt-1">
                                                    @if ($parameter->interpretation)
                                                        <x-status-badge
                                                            :status="$parameter->interpretation"
                                                            :icon="$parameter->interpretation->isCritical() ? 'warning' : null"
                                                        />
                                                    @else
                                                        <span class="text-sm text-slate-500">—</span>
                                                    @endif
                                                </div>
                                            @endif

                                            @if ($parameter->divergesFromSuggestion())
                                                <p class="mt-1.5 text-xs text-amber-700">
                                                    The laboratory reported
                                                    {{ $parameter->interpretation?->label() }}; the reference range
                                                    suggests {{ $parameter->auto_interpretation?->label() }}.
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Per parameter comment --}}
                                    <div class="mt-3">
                                        <label for="values.{{ $parameter->id }}.comment" class="field-label">Comment</label>
                                        @if ($canEdit)
                                            <input
                                                type="text"
                                                name="values[{{ $parameter->id }}][comment]"
                                                id="values.{{ $parameter->id }}.comment"
                                                value="{{ old('values.'.$parameter->id.'.comment', $parameter->comment) }}"
                                                maxlength="500"
                                                class="field-control mt-1"
                                                placeholder="Optional note about this value"
                                            />
                                        @else
                                            <p class="mt-1 text-sm text-slate-700">{{ $parameter->comment ?: '—' }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>

                {{-- Interpretation and comments for the result as a whole --}}
                <x-card class="mt-6" title="Interpretation and comments" icon="document">
                    <div class="space-y-4">
                        <x-form.field name="interpretation" label="Overall interpretation"
                                      hint="The laboratory conclusion for this investigation, printed on the report.">
                            @if ($canEdit)
                                <x-form.textarea name="interpretation" :value="$result->interpretation" rows="3" />
                            @else
                                <p class="text-sm whitespace-pre-line text-slate-800">{{ $result->interpretation ?: '—' }}</p>
                            @endif
                        </x-form.field>

                        <x-form.field name="comments" label="Laboratory comments"
                                      hint="Notes about the specimen or the analysis, printed on the report.">
                            @if ($canEdit)
                                <x-form.textarea name="comments" :value="$result->comments" rows="3" />
                            @else
                                <p class="text-sm whitespace-pre-line text-slate-800">{{ $result->comments ?: '—' }}</p>
                            @endif
                        </x-form.field>
                    </div>
                </x-card>

                @if ($canEdit)
                    <div class="mt-4 flex justify-end gap-2">
                        <x-button :href="route('laboratory.results.show', $result)">Discard changes</x-button>
                        <x-button type="submit" variant="primary" icon="check">Save results</x-button>
                    </div>
                @endif
            </form>
        </div>

        <div class="space-y-6">
            {{-- Validation --}}
            <x-card title="Validation" subtitle="Review and release of the result" icon="shield">
                <div class="flex items-center gap-2">
                    <x-status-badge :status="$result->validation_status" :icon="$result->isValidated() ? 'check-circle' : 'clock'" />
                    @if ($result->revision > 1)
                        <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30">Revision {{ $result->revision }}</x-badge>
                    @endif
                </div>

                <dl class="mt-3 space-y-1 border-t border-slate-100 pt-3">
                    <x-detail label="Performed by" :value="$result->performedBy?->name" />
                    <x-detail label="Entry completed" :value="$result->performed_at?->format('d M Y H:i')" />
                    <x-detail label="Validated by" :value="$result->validatedBy?->name" />
                    <x-detail label="Validated at" :value="$result->validated_at?->format('d M Y H:i')" />
                    @if ($result->unvalidated_at)
                        <x-detail label="Last unvalidated by" :value="$result->unvalidatedBy?->name" />
                        <x-detail label="Last unvalidated at" :value="$result->unvalidated_at?->format('d M Y H:i')" />
                        <x-detail label="Reason" :value="$result->unvalidation_reason" />
                    @endif
                    <x-detail label="Times printed" :value="(string) $result->print_count" />
                    <x-detail label="Last printed" :value="$result->last_printed_at?->format('d M Y H:i')" />
                </dl>

                @unless ($result->isValidated())
                    <div class="mt-3 border-t border-slate-100 pt-3">
                        @if ($entered < $total)
                            <p class="flex items-start gap-2 text-sm text-amber-800">
                                <x-icon name="warning" class="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {{ $total - $entered }} of {{ $total }} values are still missing. A result cannot
                                    be validated until every parameter has been reported.
                                </span>
                            </p>
                        @else
                            <p class="flex items-start gap-2 text-sm text-slate-600">
                                <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                <span>Every value is entered. This result is ready for validation.</span>
                            </p>
                        @endif
                    </div>
                @endunless
            </x-card>

            {{-- History --}}
            <x-card title="Result history" subtitle="Entry, validation and corrections" icon="clock">
                <x-activity-feed :entries="$activity" />
            </x-card>
        </div>
    </div>

</x-layouts.admin>
