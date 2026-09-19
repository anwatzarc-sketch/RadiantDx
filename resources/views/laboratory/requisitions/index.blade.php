<x-layouts.admin title="Requisitions" :breadcrumbs="['Laboratory' => null, 'Requisitions' => null]">

    <x-page-header
        title="Requisitions"
        subtitle="The laboratory work queue. Urgent requests appear first."
    >
        <x-slot:actions>
            @can('create', App\Models\LaboratoryRequisition::class)
                <x-button :href="route('laboratory.requisitions.create')" variant="primary" icon="plus">
                    New requisition
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('laboratory.requisitions.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Requisition no., patient name or ID" class="field-control mt-1" />
                </div>
                <div class="w-44">
                    <label for="status" class="field-label">Status</label>
                    <select name="status" id="status" class="field-control mt-1">
                        <option value="">Any status</option>
                        @foreach (App\Enums\RequisitionStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-36">
                    <label for="priority" class="field-label">Priority</label>
                    <select name="priority" id="priority" class="field-control mt-1">
                        <option value="">Any priority</option>
                        @foreach (App\Enums\RequisitionPriority::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-40">
                    <label for="from" class="field-label">Requested from</label>
                    <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="field-control mt-1" />
                </div>
                <div class="w-40">
                    <label for="to" class="field-label">Requested to</label>
                    <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="field-control mt-1" />
                </div>
            </x-filter-bar>
        </div>

        @if ($requisitions->isEmpty())
            <x-empty-state
                icon="requisition"
                title="No requisitions match"
                description="Adjust the filters, or create a laboratory request for a patient."
            >
                @can('create', App\Models\LaboratoryRequisition::class)
                    <x-button :href="route('laboratory.requisitions.create')" variant="primary" size="sm" icon="plus">
                        New requisition
                    </x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Requisition no.</th>
                            <th scope="col">Patient</th>
                            <th scope="col">Requested</th>
                            <th scope="col">Clinician</th>
                            <th scope="col">Investigations</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisitions as $requisition)
                            <tr>
                                <td>
                                    <a href="{{ route('laboratory.requisitions.show', $requisition) }}"
                                       class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                        {{ $requisition->requisition_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="block font-medium text-slate-800">{{ $requisition->patient_name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $requisition->patient_identifier }} &middot;
                                        {{ $requisition->patient_gender?->label() ?? 'Gender not recorded' }} &middot;
                                        {{ $requisition->ageLabel() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap">{{ $requisition->requested_date?->format('d M Y') }}</td>
                                <td>{{ $requisition->requesting_clinician ?: '—' }}</td>
                                <td class="tabular-nums">{{ $requisition->items_count }}</td>
                                <td><x-status-badge :status="$requisition->priority" /></td>
                                <td><x-status-badge :status="$requisition->status" /></td>
                                <td class="text-right">
                                    <x-button :href="route('laboratory.requisitions.show', $requisition)" size="sm" icon="eye">
                                        Open
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $requisitions->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
