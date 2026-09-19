@php
    use App\Enums\RequisitionStatus;

    $path = RequisitionStatus::workflowPath();
    $currentIndex = array_search($requisition->status, $path, true);

    $steps = collect($path)->map(fn (RequisitionStatus $status, int $index): array => [
        'label' => $status->label(),
        'state' => match (true) {
            $currentIndex === false => 'todo',
            $index < $currentIndex => 'done',
            $index === $currentIndex => 'current',
            default => 'todo',
        },
    ])->all();

    $progress = $requisition->resultProgress();
    $nextStatuses = collect($requisition->status->allowedTransitions())
        ->reject(fn (RequisitionStatus $status): bool => $status === RequisitionStatus::Cancelled);
@endphp

<x-layouts.admin
    :title="$requisition->requisition_number"
    :breadcrumbs="['Laboratory' => null, 'Requisitions' => route('laboratory.requisitions.index'), $requisition->requisition_number => null]"
>

    <x-page-header
        :title="$requisition->requisition_number"
        :subtitle="$requisition->patient_name.' · '.$requisition->patient_identifier"
        :back="route('laboratory.requisitions.index')"
        back-label="Back to requisitions"
    >
        <x-slot:meta>
            <x-status-badge :status="$requisition->status" />
            <x-status-badge :status="$requisition->priority" />
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20" icon="clock">
                Requested {{ $requisition->requested_date?->format('d M Y') }}
            </x-badge>
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $requisition)
                <x-button :href="route('laboratory.requisitions.edit', $requisition)" icon="pencil">Edit</x-button>
            @endcan

            @can('submit', $requisition)
                <form method="POST" action="{{ route('laboratory.requisitions.submit', $requisition) }}">
                    @csrf
                    <x-button type="submit" variant="primary" icon="check">Submit to laboratory</x-button>
                </form>
            @endcan

            @can('advance', $requisition)
                @foreach ($nextStatuses as $next)
                    <form method="POST" action="{{ route('laboratory.requisitions.transition', $requisition) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next->value }}" />
                        <x-button type="submit" variant="primary" icon="chevron-right">
                            Mark {{ mb_strtolower($next->label()) }}
                        </x-button>
                    </form>
                @endforeach
            @endcan

            @can('laboratory.result.print')
                @if ($requisition->results->where('validation_status', App\Enums\ValidationStatus::Validated)->isNotEmpty())
                    <x-button :href="route('laboratory.requisitions.report', $requisition)" target="_blank" icon="print">
                        Print report
                    </x-button>
                @endif
            @endcan

            @can('cancel', $requisition)
                <x-confirm-action
                    :action="route('laboratory.requisitions.cancel', $requisition)"
                    size="md"
                    title="Cancel this requisition?"
                    message="The request and all of its outstanding investigations will be withdrawn. This cannot be undone."
                    confirm="Cancel requisition"
                    icon="ban"
                    :reason="true"
                    reason-label="Reason for cancellation"
                    reason-hint="Recorded in the audit trail."
                >Cancel</x-confirm-action>
            @endcan

            @can('delete', $requisition)
                <x-confirm-action
                    :action="route('laboratory.requisitions.destroy', $requisition)"
                    method="DELETE"
                    size="md"
                    title="Delete this draft?"
                    message="This draft has never reached the laboratory and will be removed."
                    confirm="Delete draft"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Processing status --}}
    <x-card title="Processing status" subtitle="Where this request stands in the laboratory workflow" icon="clock">
        <x-workflow-tracker
            :steps="$steps"
            :current="$requisition->status"
            :cancelled="$requisition->isCancelled()"
            cancelled-label="Cancelled"
        />

        @if ($requisition->isCancelled())
            <div class="mt-4 flex items-start gap-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-600/20 ring-inset">
                <x-icon name="ban" class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        Cancelled on {{ $requisition->cancelled_at?->format('d M Y H:i') }}
                        by {{ $requisition->cancelledBy?->name ?? 'a former user' }}
                    </p>
                    @if ($requisition->cancellation_reason)
                        <p class="mt-0.5">{{ $requisition->cancellation_reason }}</p>
                    @endif
                </div>
            </div>
        @endif

        <dl class="mt-4 grid grid-cols-2 gap-x-6 border-t border-slate-100 pt-3 sm:grid-cols-4">
            <x-detail label="Submitted" :value="$requisition->submitted_at?->format('d M Y H:i')" />
            <x-detail label="Collected" :value="$requisition->collected_at?->format('d M Y H:i')" />
            <x-detail label="Processing" :value="$requisition->processing_at?->format('d M Y H:i')" />
            <x-detail label="Completed" :value="$requisition->completed_at?->format('d M Y H:i')" />
        </dl>
    </x-card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Patient --}}
            <x-card title="Patient information" icon="profile">
                <x-detail-list :columns="3">
                    <x-detail label="Patient name" :value="$requisition->patient_name" />
                    <x-detail label="Patient ID" :value="$requisition->patient_identifier" mono />
                    <x-detail label="Gender" :value="$requisition->patient_gender?->label()" />
                    <x-detail label="Date of birth" :value="$requisition->patient_date_of_birth?->format('d M Y')" />
                    <x-detail label="Age" :value="$requisition->ageLabel()" />
                </x-detail-list>
            </x-card>

            {{-- Requisition --}}
            <x-card title="Requisition information" icon="requisition">
                <x-detail-list :columns="3">
                    <x-detail label="Requisition number" :value="$requisition->requisition_number" mono />
                    <x-detail label="Request date" :value="$requisition->requested_date?->format('d M Y')" />
                    <x-detail label="Priority" :value="$requisition->priority->label()" />
                    <x-detail label="Requesting clinician" :value="$requisition->requesting_clinician" />
                    <x-detail label="Department" :value="$requisition->requesting_department" />
                    <x-detail label="Created by" :value="$requisition->createdBy?->name" />
                </x-detail-list>
            </x-card>

            {{-- Requested investigations, grouped as they were requested --}}
            <x-card
                title="Requested investigations"
                :subtitle="$requisition->items->count().' test(s) to perform'"
                icon="beaker"
                :padded="false"
            >
                @if ($requisition->items->isEmpty())
                    <x-empty-state
                        icon="beaker"
                        title="No investigations selected"
                        description="This draft carries no work yet. Add at least one test or panel before submitting."
                    >
                        @can('update', $requisition)
                            <x-button :href="route('laboratory.requisitions.edit', $requisition)" variant="primary" size="sm" icon="plus">
                                Add investigations
                            </x-button>
                        @endcan
                    </x-empty-state>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($requisition->groupedItems() as $group)
                            <div class="px-4 py-3 sm:px-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :classes="$group['type'] === 'Panel'
                                        ? 'bg-indigo-100 text-indigo-800 ring-indigo-600/20'
                                        : 'bg-slate-100 text-slate-700 ring-slate-500/20'">
                                        {{ $group['type'] }}
                                    </x-badge>
                                    <span class="text-sm font-semibold text-slate-800">{{ $group['label'] }}</span>
                                    @if ($group['code'])
                                        <span class="font-mono text-xs text-slate-500">{{ $group['code'] }}</span>
                                    @endif
                                </div>

                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($group['items'] as $item)
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm text-slate-800">{{ $item->test_name }}</span>
                                                <span class="block font-mono text-xs text-slate-500">
                                                    {{ $item->test_code }}{{ $item->specimen_type ? ' · '.$item->specimen_type : '' }}
                                                </span>
                                            </span>

                                            <span class="flex shrink-0 items-center gap-2">
                                                <x-status-badge :status="$item->status" />

                                                @if ($item->result)
                                                    @can('view', $item->result)
                                                        <x-button :href="route('laboratory.results.show', $item->result)" size="sm" icon="eye">
                                                            Result
                                                        </x-button>
                                                    @endcan
                                                @elseif ($requisition->status->acceptsResults() && ! $requisition->isCancelled())
                                                    @can('create', App\Models\LaboratoryResult::class)
                                                        <form method="POST" action="{{ route('laboratory.results.store', $item) }}">
                                                            @csrf
                                                            <x-button type="submit" size="sm" variant="primary" icon="plus">
                                                                Open result
                                                            </x-button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Clinical information --}}
            <x-card title="Clinical information" icon="document">
                <x-detail-list :columns="1">
                    <x-detail label="Clinical indication" :value="$requisition->clinical_indication" />
                    <x-detail label="Clinical notes" :value="$requisition->clinical_notes" />
                </x-detail-list>
            </x-card>
        </div>

        <div class="space-y-6">
            {{-- Result status --}}
            <x-card title="Result status" subtitle="Progress towards a complete report" icon="shield">
                <div class="flex items-baseline justify-between">
                    <p class="text-sm text-slate-600">Validated investigations</p>
                    <p class="text-sm font-semibold text-slate-900 tabular-nums">
                        {{ $progress['validated'] }} of {{ $progress['total'] }}
                    </p>
                </div>

                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200"
                     role="progressbar"
                     aria-valuenow="{{ $progress['percentage'] }}"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-label="Validated investigations">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ $progress['percentage'] }}% validated</p>

                <dl class="mt-4 space-y-1 border-t border-slate-100 pt-3">
                    <x-detail label="Results opened" :value="(string) $requisition->results->count()" />
                    <x-detail label="Entry complete" :value="(string) $progress['entered']" />
                    <x-detail label="Validated" :value="(string) $progress['validated']" />
                </dl>

                @if ($requisition->results->isNotEmpty())
                    <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100">
                        @foreach ($requisition->results as $result)
                            <li class="flex items-start justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    @can('view', $result)
                                        <a href="{{ route('laboratory.results.show', $result) }}"
                                           class="block truncate text-sm font-medium text-brand-700 hover:underline">
                                            {{ $result->test_name }}
                                        </a>
                                    @else
                                        <span class="block truncate text-sm font-medium text-slate-800">{{ $result->test_name }}</span>
                                    @endcan
                                    <span class="block font-mono text-xs text-slate-500">{{ $result->result_number }}</span>
                                </div>
                                <x-status-badge :status="$result->validation_status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- Activity history --}}
            <x-card title="Activity history" subtitle="Everything that happened to this request" icon="clock">
                <x-activity-feed :entries="$activity" />
            </x-card>
        </div>
    </div>

</x-layouts.admin>
