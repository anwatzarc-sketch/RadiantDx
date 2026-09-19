<x-layouts.admin :title="$panel->name" :breadcrumbs="['Laboratory catalogue' => null, 'Panels' => route('laboratory.panels.index'), $panel->name => null]">

    <x-page-header
        :title="$panel->name"
        :subtitle="$panel->description"
        :back="route('laboratory.panels.index')"
        back-label="Back to panels"
    >
        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $panel->code }}</x-badge>
            <x-badge :classes="$panel->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                     :icon="$panel->is_active ? 'check-circle' : 'ban'">
                {{ $panel->is_active ? 'Active' : 'Inactive' }}
            </x-badge>
            @unless ($panel->isReadyForRequisition())
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="warning">
                    Not available for requisition
                </x-badge>
            @endunless
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $panel)
                <x-button :href="route('laboratory.panels.edit', $panel)" icon="pencil">Edit</x-button>
            @endcan
            @can('activate', $panel)
                <form method="POST" action="{{ route('laboratory.panels.activate', $panel) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="success" icon="check">Activate</x-button>
                </form>
            @endcan
            @can('deactivate', $panel)
                <x-confirm-action
                    :action="route('laboratory.panels.deactivate', $panel)"
                    method="PATCH"
                    size="md"
                    variant="secondary"
                    title="Deactivate this panel?"
                    :message="$panel->name.' will be withdrawn from new requisitions. Existing requisitions are unaffected.'"
                    confirm="Deactivate"
                    icon="ban"
                >Deactivate</x-confirm-action>
            @endcan
            @can('delete', $panel)
                <x-confirm-action
                    :action="route('laboratory.panels.destroy', $panel)"
                    method="DELETE"
                    size="md"
                    title="Delete this panel?"
                    message="The panel will be removed. Its tests stay in the catalogue."
                    confirm="Delete panel"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Panel information" icon="layers">
            <x-detail-list :columns="1">
                <x-detail label="Name" :value="$panel->name" />
                <x-detail label="Code" :value="$panel->code" mono />
                <x-detail label="Category" :value="$panel->category" />
                <x-detail label="Tests included" :value="(string) $panel->tests->count()" />
                <x-detail label="Display order" :value="(string) $panel->display_order" />
            </x-detail-list>
        </x-card>

        <x-card
            class="lg:col-span-2"
            title="Included tests"
            subtitle="Requesting this panel creates one laboratory item per test, in this order."
            icon="catalogue"
            :padded="false"
        >
            @if ($panel->tests->isEmpty())
                <x-empty-state
                    icon="catalogue"
                    title="This panel is empty"
                    description="An empty panel cannot be requested. Add the tests it should cover."
                >
                    @can('update', $panel)
                        <x-button :href="route('laboratory.panels.edit', $panel)" variant="primary" size="sm" icon="plus">
                            Add tests
                        </x-button>
                    @endcan
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12">#</th>
                                <th scope="col">Test</th>
                                <th scope="col">Specimen</th>
                                <th scope="col">Reports</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($panel->tests as $test)
                                <tr>
                                    <td class="text-xs text-slate-400 tabular-nums">{{ $loop->iteration }}</td>
                                    <td>
                                        @can('view', $test)
                                            <a href="{{ route('laboratory.tests.show', $test) }}"
                                               class="font-medium text-brand-700 hover:underline">{{ $test->name }}</a>
                                        @else
                                            <span class="font-medium text-slate-800">{{ $test->name }}</span>
                                        @endcan
                                        <span class="block font-mono text-xs text-slate-500">{{ $test->code }}</span>
                                    </td>
                                    <td>{{ $test->specimen_type ?: '—' }}</td>
                                    <td>
                                        {{ $test->isParameterised()
                                            ? $test->parameters->count().' parameters'
                                            : 'Single value' }}
                                    </td>
                                    <td>
                                        <x-badge :classes="$test->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'">
                                            {{ $test->is_active ? 'Active' : 'Inactive' }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

</x-layouts.admin>
