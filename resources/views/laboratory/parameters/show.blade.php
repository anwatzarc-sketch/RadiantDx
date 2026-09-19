<x-layouts.admin :title="$parameter->name" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => route('laboratory.parameters.index'), $parameter->name => null]">

    <x-page-header
        :title="$parameter->name"
        :subtitle="$parameter->description"
        :back="route('laboratory.tests.show', $parameter->laboratory_test_id)"
        :back-label="'Back to '.$parameter->test->name"
    >
        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $parameter->code }}</x-badge>
            <x-badge classes="bg-sky-100 text-sky-800 ring-sky-600/20">{{ $parameter->data_type->label() }}</x-badge>
            <x-badge :classes="$parameter->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                     :icon="$parameter->is_active ? 'check-circle' : 'ban'">
                {{ $parameter->is_active ? 'Active' : 'Inactive' }}
            </x-badge>
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $parameter)
                <x-button :href="route('laboratory.parameters.edit', $parameter)" icon="pencil">Edit</x-button>
            @endcan
            @can('activate', $parameter)
                <form method="POST" action="{{ route('laboratory.parameters.activate', $parameter) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="success" icon="check">Activate</x-button>
                </form>
            @endcan
            @can('deactivate', $parameter)
                <x-confirm-action
                    :action="route('laboratory.parameters.deactivate', $parameter)"
                    method="PATCH"
                    size="md"
                    variant="secondary"
                    title="Deactivate this parameter?"
                    :message="$parameter->name.' will no longer be reported on new results. Existing results keep it.'"
                    confirm="Deactivate"
                    icon="ban"
                >Deactivate</x-confirm-action>
            @endcan
            @can('delete', $parameter)
                <x-confirm-action
                    :action="route('laboratory.parameters.destroy', $parameter)"
                    method="DELETE"
                    size="md"
                    title="Delete this parameter?"
                    message="It has never been reported on, so removing it destroys no history."
                    confirm="Delete parameter"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Parameter" icon="parameter">
            <x-detail-list :columns="3">
                <x-detail label="Name" :value="$parameter->name" />
                <x-detail label="Code" :value="$parameter->code" mono />
                <x-detail label="Laboratory test" :value="$parameter->test->name" />
                <x-detail label="Data type" :value="$parameter->data_type->label()" />
                <x-detail label="Unit" :value="$parameter->unit" />
                <x-detail label="Display order" :value="(string) $parameter->display_order" />

                @if ($parameter->data_type->isNumeric())
                    <x-detail label="Reference range" :value="$parameter->referenceSummary() ?: null" />
                    <x-detail label="Critical low" :value="$parameter->critical_low" />
                    <x-detail label="Critical high" :value="$parameter->critical_high" />
                    <x-detail label="Decimal places" :value="(string) $parameter->decimal_precision" />
                @endif

                @if ($parameter->abnormal_when)
                    <x-detail label="Flagged abnormal when" :value="Str::headline($parameter->abnormal_when)" />
                @endif
            </x-detail-list>
        </x-card>

        <x-card title="Selectable values" subtitle="Offered during result entry" icon="list">
            @php($values = $parameter->selectableValues())

            @if ($values === [])
                <p class="text-sm text-slate-600">
                    @if ($parameter->data_type === App\Enums\ParameterDataType::Numeric)
                        A numeric parameter is typed in freely and checked against its reference range.
                    @elseif ($parameter->data_type === App\Enums\ParameterDataType::Text)
                        A text parameter accepts free text.
                    @else
                        No values are configured yet.
                    @endif
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($values as $value => $label)
                        @php($option = $parameter->options->firstWhere('value', $value))
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
                                <span class="block font-mono text-xs text-slate-500">{{ $value }}</span>
                            </span>
                            @if ($option?->is_abnormal)
                                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30">Abnormal</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

</x-layouts.admin>
