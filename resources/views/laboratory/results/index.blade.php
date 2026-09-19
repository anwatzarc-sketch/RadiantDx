<x-layouts.admin title="Results" :breadcrumbs="['Laboratory' => null, 'Results' => null]">

    <x-page-header
        title="Laboratory results"
        subtitle="The bench work queue: entry, review and validation."
    />

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('laboratory.results.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Result no., requisition no. or patient" class="field-control mt-1" />
                </div>
                <div class="w-44">
                    <label for="status" class="field-label">Result status</label>
                    <select name="status" id="status" class="field-control mt-1">
                        <option value="">Any status</option>
                        @foreach (App\Enums\ResultStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-44">
                    <label for="validation_status" class="field-label">Validation</label>
                    <select name="validation_status" id="validation_status" class="field-control mt-1">
                        <option value="">Any</option>
                        @foreach (App\Enums\ValidationStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['validation_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-48">
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
                    <label for="panel" class="field-label">Panel</label>
                    <select name="panel" id="panel" class="field-control mt-1">
                        <option value="">All panels</option>
                        @foreach ($panels as $panel)
                            <option value="{{ $panel->id }}" @selected((string) ($filters['panel'] ?? '') === (string) $panel->id)>
                                {{ $panel->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-40">
                    <label for="from" class="field-label">Opened from</label>
                    <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="field-control mt-1" />
                </div>
                <div class="w-40">
                    <label for="to" class="field-label">Opened to</label>
                    <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="field-control mt-1" />
                </div>
            </x-filter-bar>
        </div>

        @if ($results->isEmpty())
            <x-empty-state
                icon="beaker"
                title="No results match"
                description="Results are opened once a requisition reaches the collected stage. Adjust the filters, or work through the requisition queue."
            >
                @can('laboratory.requisition.view')
                    <x-button :href="route('laboratory.requisitions.index')" size="sm">Go to requisitions</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Result no.</th>
                            <th scope="col">Requisition</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Investigation</th>
                            <th scope="col">Entry</th>
                            <th scope="col">Validation</th>
                            <th scope="col">Opened</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            <tr>
                                <td>
                                    <a href="{{ route('laboratory.results.show', $result) }}"
                                       class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                        {{ $result->result_number }}
                                    </a>
                                    @if ($result->revision > 1)
                                        <span class="mt-0.5 block text-xs text-amber-700">Revision {{ $result->revision }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('view', $result->requisition)
                                        <a href="{{ route('laboratory.requisitions.show', $result->requisition) }}"
                                           class="font-mono text-xs text-slate-600 hover:text-brand-700">
                                            {{ $result->requisition->requisition_number }}
                                        </a>
                                    @else
                                        <span class="font-mono text-xs text-slate-600">{{ $result->requisition->requisition_number }}</span>
                                    @endcan
                                </td>
                                <td>
                                    <span class="block font-medium text-slate-800">{{ $result->requisition->patient_name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $result->requisition->patient_identifier }}</span>
                                </td>
                                <td>
                                    <span class="block text-slate-800">{{ $result->test_name }}</span>
                                    @if ($result->panel_name)
                                        <span class="block text-xs text-slate-500">{{ $result->panel_name }}</span>
                                    @endif
                                </td>
                                <td><x-status-badge :status="$result->status" /></td>
                                <td>
                                    <x-status-badge
                                        :status="$result->validation_status"
                                        :icon="$result->isValidated() ? 'check-circle' : 'clock'"
                                    />
                                </td>
                                <td class="text-xs whitespace-nowrap text-slate-500">
                                    {{ $result->created_at?->format('d M Y') }}
                                </td>
                                <td class="text-right">
                                    <x-button :href="route('laboratory.results.show', $result)" size="sm" icon="eye">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $results->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
