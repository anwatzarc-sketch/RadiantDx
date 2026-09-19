<x-layouts.admin :title="$test->name" :breadcrumbs="['Laboratory catalogue' => null, 'Laboratory tests' => route('laboratory.tests.index'), $test->name => null]">

    <x-page-header
        :title="$test->name"
        :subtitle="$test->description"
        :back="route('laboratory.tests.index')"
        back-label="Back to tests"
    >
        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $test->code }}</x-badge>
            <x-badge :classes="$test->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                     :icon="$test->is_active ? 'check-circle' : 'ban'">
                {{ $test->is_active ? 'Active' : 'Inactive' }}
            </x-badge>
            <x-badge classes="bg-sky-100 text-sky-800 ring-sky-600/20">{{ $test->result_type->label() }}</x-badge>
            @unless ($test->isReadyForRequisition())
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="warning">
                    Not available for requisition
                </x-badge>
            @endunless
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $test)
                <x-button :href="route('laboratory.tests.edit', $test)" icon="pencil">Edit</x-button>
            @endcan
            @can('activate', $test)
                <form method="POST" action="{{ route('laboratory.tests.activate', $test) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="success" icon="check">Activate</x-button>
                </form>
            @endcan
            @can('deactivate', $test)
                <x-confirm-action
                    :action="route('laboratory.tests.deactivate', $test)"
                    method="PATCH"
                    size="md"
                    variant="secondary"
                    title="Deactivate this test?"
                    :message="$test->name.' will be withdrawn from new requisitions. Existing requisitions and results are unaffected.'"
                    confirm="Deactivate"
                    icon="ban"
                >Deactivate</x-confirm-action>
            @endcan
            @can('delete', $test)
                <x-confirm-action
                    :action="route('laboratory.tests.destroy', $test)"
                    method="DELETE"
                    size="md"
                    title="Delete this test?"
                    :message="'The test and its parameters will be removed. This is only possible because no requisition references it.'"
                    confirm="Delete test"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($test->isReadyForRequisition())
        <div class="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/30 ring-inset">
            <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">This test cannot be requested yet</p>
                <p class="mt-0.5">
                    @if (! $test->is_active)
                        It is inactive. Activate it once the configuration is complete.
                    @else
                        A multi-parameter test needs at least one active parameter before it can produce a result.
                    @endif
                </p>
            </div>
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            <x-card title="Test information" icon="catalogue">
                <x-detail-list :columns="3">
                    <x-detail label="Name" :value="$test->name" />
                    <x-detail label="Code" :value="$test->code" mono />
                    <x-detail label="Category" :value="$test->category" />
                    <x-detail label="Specimen type" :value="$test->specimen_type" />
                    <x-detail label="Result shape" :value="$test->result_type->label()" />
                    <x-detail label="Turnaround time"
                              :value="$test->turnaround_time_hours ? $test->turnaround_time_hours.' hours' : null" />
                    @unless ($test->isParameterised())
                        <x-detail label="Unit" :value="$test->unit" />
                        <x-detail label="Reference range" :value="$test->referenceSummary() ?: null" />
                        <x-detail label="Decimal places" :value="(string) $test->decimal_precision" />
                    @endunless
                    <x-detail label="Display order" :value="(string) $test->display_order" />
                </x-detail-list>
            </x-card>

            {{-- Parameters: the reportable components of this test --}}
            <x-card
                title="Parameters"
                :subtitle="$test->isParameterised()
                    ? 'Each parameter is one reported value with its own unit and reference range.'
                    : 'This test reports a single value, so it has no separate parameters.'"
                icon="parameter"
                :padded="false"
            >
                <x-slot:actions>
                    @can('create', App\Models\LaboratoryTestParameter::class)
                        @if ($test->isParameterised())
                            <x-button :href="route('laboratory.parameters.create', ['test' => $test->id])" size="sm" icon="plus">
                                Add parameter
                            </x-button>
                        @endif
                    @endcan
                </x-slot:actions>

                @if (! $test->isParameterised())
                    <div class="px-4 py-4 sm:px-5">
                        <p class="text-sm text-slate-600">
                            Change the result shape to
                            <span class="font-medium">{{ App\Enums\TestResultType::Parameterised->label() }}</span>
                            if this test should report several values.
                        </p>
                    </div>
                @elseif ($test->parameters->isEmpty())
                    <x-empty-state
                        icon="parameter"
                        title="No parameters configured"
                        description="A multi-parameter test reports one value per parameter. Add the first one."
                    >
                        @can('create', App\Models\LaboratoryTestParameter::class)
                            <x-button :href="route('laboratory.parameters.create', ['test' => $test->id])" variant="primary" size="sm" icon="plus">
                                Add parameter
                            </x-button>
                        @endcan
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Unit</th>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($test->parameters as $parameter)
                                    <tr>
                                        <td>
                                            <a href="{{ route('laboratory.parameters.show', $parameter) }}"
                                               class="font-medium text-brand-700 hover:underline">{{ $parameter->name }}</a>
                                            <span class="block font-mono text-xs text-slate-500">{{ $parameter->code }}</span>
                                        </td>
                                        <td>{{ $parameter->data_type->label() }}</td>
                                        <td>{{ $parameter->unit ?: '—' }}</td>
                                        <td>{{ $parameter->referenceSummary() ?: '—' }}</td>
                                        <td>
                                            <x-badge :classes="$parameter->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'">
                                                {{ $parameter->is_active ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        </td>
                                        <td class="text-right">
                                            @can('update', $parameter)
                                                <x-button :href="route('laboratory.parameters.edit', $parameter)" size="sm" icon="pencil">Edit</x-button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <x-card title="Used by panels" subtitle="Panels that include this test" icon="layers">
            @if ($test->panels->isEmpty())
                <p class="text-sm text-slate-600">This test is not part of any panel.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($test->panels as $panel)
                        <li class="py-2.5">
                            @can('view', $panel)
                                <a href="{{ route('laboratory.panels.show', $panel) }}"
                                   class="text-sm font-medium text-brand-700 hover:underline">{{ $panel->name }}</a>
                            @else
                                <span class="text-sm font-medium text-slate-800">{{ $panel->name }}</span>
                            @endcan
                            <span class="block font-mono text-xs text-slate-500">{{ $panel->code }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

</x-layouts.admin>
