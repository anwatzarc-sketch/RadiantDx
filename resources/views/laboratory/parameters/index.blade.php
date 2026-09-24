<x-layouts.admin title="Test parameters" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => null]">

    <x-page-header
        title="Test parameters"
        subtitle="Every reportable value across the catalogue, with the test it belongs to."
    >
        <x-slot:actions>
            @can('create', App\Models\LaboratoryTestParameter::class)
                <x-button :href="route('laboratory.parameters.create')" variant="primary" icon="plus">New parameter</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('laboratory.parameters.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Parameter or test" class="field-control mt-1" />
                </div>
                <div class="w-56">
                    <label for="test" class="field-label">Test</label>
                    <select name="test" id="test" class="field-control mt-1">
                        <option value="">All tests</option>
                        @foreach ($tests as $test)
                            <option value="{{ $test->id }}" @selected((string) ($filters['test'] ?? '') === (string) $test->id)>
                                {{ $test->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-48">
                    <label for="data_type" class="field-label">Data type</label>
                    <select name="data_type" id="data_type" class="field-control mt-1">
                        <option value="">Any type</option>
                        @foreach (App\Enums\ParameterDataType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['data_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-40">
                    <label for="status" class="field-label">Status</label>
                    <select name="status" id="status" class="field-control mt-1">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </x-filter-bar>
        </div>

        @if ($parameters->isEmpty())
            <x-empty-state
                icon="parameter"
                title="No parameters yet"
                description="Parameters belong to a multi-parameter test. Create a test first, then add its reportable values."
            />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Parameter</th>
                            <th scope="col">Test</th>
                            <th scope="col">Type</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Reference</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parameters as $parameter)
                            <tr>
                                <td>
                                    <a href="{{ route('laboratory.parameters.show', $parameter) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $parameter->name }}</a>
                                    <span class="block font-mono text-xs text-slate-500">{{ $parameter->code }}</span>
                                </td>
                                <td>
                                    @can('view', $parameter->test)
                                        <a href="{{ route('laboratory.tests.show', $parameter->test) }}"
                                           class="hover:text-brand-700">{{ $parameter->test->name }}</a>
                                    @else
                                        {{ $parameter->test->name }}
                                    @endcan
                                </td>
                                <td>{{ $parameter->data_type->label() }}</td>
                                <td>{{ $parameter->unit ?: '—' }}</td>
                                <td>{{ $parameter->referenceSummary() ?: '—' }}</td>
                                <td>
                                    <x-badge :classes="$parameter->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                                             :icon="$parameter->is_active ? 'check-circle' : 'ban'">
                                        {{ $parameter->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                    @if ($parameter->placeholder_ranges_count > 0)
                                        <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" class="mt-1">
                                            {{ $parameter->placeholder_ranges_count }} placeholder {{ Str::plural('range', $parameter->placeholder_ranges_count) }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $parameter)
                                            <x-button :href="route('laboratory.parameters.edit', $parameter)" size="sm" icon="pencil">Edit</x-button>
                                        @endcan
                                        @can('activate', $parameter)
                                            <form method="POST" action="{{ route('laboratory.parameters.activate', $parameter) }}">
                                                @csrf @method('PATCH')
                                                <x-button type="submit" size="sm" variant="ghost" icon="check">Activate</x-button>
                                            </form>
                                        @endcan
                                        @can('deactivate', $parameter)
                                            <x-confirm-action
                                                :action="route('laboratory.parameters.deactivate', $parameter)"
                                                method="PATCH"
                                                variant="secondary"
                                                title="Deactivate this parameter?"
                                                :message="$parameter->name.' will no longer be reported on new results. Existing results keep it.'"
                                                confirm="Deactivate"
                                                icon="ban"
                                            >Deactivate</x-confirm-action>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $parameters->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
