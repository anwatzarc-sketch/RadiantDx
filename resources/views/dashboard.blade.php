<x-layouts.admin title="Dashboard" :breadcrumbs="['Dashboard' => null]">

    <!-- PAGE HEADER -->
    <x-page-header
        title="Laboratory Overview"
        :subtitle="'Operational workflow & system metrics at ' . config('laboratory.organisation.name', 'Harme Medical Center') . ' on ' . now()->format('d M Y') . '.'"
    >
        <x-slot:actions>
            @can('laboratory.requisition.create')
                <x-button :href="route('laboratory.requisitions.create')" variant="primary" icon="plus">
                    New Requisition
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <!-- METRICS & SYSTEM COUNTERS GRID -->
    <div class="mb-8">
        <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">System Metrics & Quick Navigation</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {{--
                Rendered from the cards DashboardController assembles, which is
                already where the counts and the permission filtering live.

                This grid previously hard-coded nine cards reading a $counts
                variable the controller never passes, so every figure on the
                dashboard printed as zero.
            --}}
            @foreach ($cards ?? [] as $card)
                <x-stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :hint="$card['hint'] ?? null"
                    :href="$card['href'] ?? null"
                    :tone="$card['tone'] ?? 'slate'"
                    :icon="$card['icon'] ?? 'inbox'"
                />
            @endforeach
        </div>
    </div>

    <!-- WORKFLOW PIPELINE BANNER -->
    @php
        $pipelineSteps = [
            ['code' => '01. CATALOG', 'title' => 'Tests & Panels', 'accent' => false],
            ['code' => '02. REQUEST', 'title' => 'Requisition', 'accent' => false],
            ['code' => '03. SAMPLE', 'title' => 'Processing', 'accent' => false],
            ['code' => '04. TESTING', 'title' => 'Result Entry', 'accent' => false],
            ['code' => '05. EVALUATION', 'title' => 'Interpretation', 'accent' => false],
            ['code' => '06. APPROVAL', 'title' => 'Validation', 'accent' => false],
            ['code' => '07. FINAL', 'title' => 'Release Report', 'accent' => true],
        ];
    @endphp

    <div class="mb-8 rounded-xl bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-6 text-white shadow-md">
        <h2 class="mb-4 flex items-center text-xs font-bold uppercase tracking-wider text-emerald-400">
            <x-icon name="squares-plus" class="mr-2 size-4" /> Standard Laboratory Workflow Pipeline
        </h2>
        <div class="grid grid-cols-2 gap-3 text-center md:grid-cols-4 lg:grid-cols-7">
            @foreach ($pipelineSteps as $step)
                <div class="rounded-lg border border-white/10 bg-white/10 p-3">
                    <span class="block font-mono text-[10px] text-slate-400">{{ $step['code'] }}</span>
                    <span class="text-xs font-bold {{ $step['accent'] ? 'text-emerald-400' : 'text-white' }}">
                        {{ $step['title'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- QUEUES AND TABLES CONTAINER -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Validation workload table --}}
        @can('laboratory.result.view')
            <x-card
                class="xl:col-span-2"
                title="Pending Validation"
                subtitle="Entered results waiting for laboratory review"
                icon="shield"
                :padded="false"
            >
                <x-slot:actions>
                    <x-button
                        :href="route('laboratory.results.index', ['validation_status' => 'pending_validation', 'status' => 'completed'])"
                        size="sm"
                    >View Queue</x-button>
                </x-slot:actions>

                @if ($pendingValidation->isEmpty())
                    <x-empty-state
                        icon="check-circle"
                        title="Nothing awaiting validation"
                        description="Every entered result has been reviewed."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table w-full text-left text-xs">
                            <thead class="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Result</th>
                                    <th scope="col" class="px-4 py-3">Patient</th>
                                    <th scope="col" class="px-4 py-3">Investigation</th>
                                    <th scope="col" class="px-4 py-3">Entered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($pendingValidation as $result)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('laboratory.results.show', $result) }}"
                                               class="font-mono text-xs font-semibold text-emerald-700 hover:underline">
                                                {{ $result->result_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="block font-medium text-slate-800">{{ $result->requisition?->patient_name ?? '—' }}</span>
                                            <span class="block text-[11px] text-slate-500">{{ $result->requisition?->requisition_number ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-slate-700">{{ $result->investigationLabel() }}</td>
                                        <td class="px-4 py-3 text-[11px] text-slate-500">
                                            {{ $result->performed_at?->diffForHumans() ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endcan

        {{-- Recent laboratory activity feed --}}
        <x-card title="Recent Activity" subtitle="Latest workflow events" icon="clock">
            <x-activity-feed :entries="$recentActivity" />
        </x-card>

        {{-- Recent Requisitions Table --}}
        @can('laboratory.requisition.view')
            <x-card
                class="xl:col-span-2"
                title="Recent Requisitions"
                subtitle="Most recently created requests"
                icon="requisition"
                :padded="false"
            >
                <x-slot:actions>
                    <x-button :href="route('laboratory.requisitions.index')" size="sm">All Requisitions</x-button>
                </x-slot:actions>

                @if ($recentRequisitions->isEmpty())
                    <x-empty-state
                        icon="requisition"
                        title="No requisitions yet"
                        description="Once the catalogue is configured, laboratory requests will appear here."
                    >
                        @can('laboratory.requisition.create')
                            <x-button :href="route('laboratory.requisitions.create')" variant="primary" size="sm" icon="plus">
                                Create the first requisition
                            </x-button>
                        @endcan
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table w-full text-left text-xs">
                            <thead class="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Requisition</th>
                                    <th scope="col" class="px-4 py-3">Patient</th>
                                    <th scope="col" class="px-4 py-3">Priority</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recentRequisitions as $requisition)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('laboratory.requisitions.show', $requisition) }}"
                                               class="font-mono text-xs font-semibold text-emerald-700 hover:underline">
                                                {{ $requisition->requisition_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="block font-medium text-slate-800">{{ $requisition->patient_name }}</span>
                                            <span class="block text-[11px] text-slate-500">{{ $requisition->patient_identifier }}</span>
                                        </td>
                                        <td class="px-4 py-3"><x-status-badge :status="$requisition->priority" /></td>
                                        <td class="px-4 py-3"><x-status-badge :status="$requisition->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endcan

        {{-- Recently Validated Results List --}}
        @can('laboratory.result.view')
            <x-card title="Recently Validated" subtitle="Reports released" icon="check-circle">
                @if ($recentResults->isEmpty())
                    <p class="py-2 text-sm text-slate-500">No results have been validated yet.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recentResults as $result)
                            <li class="flex items-start justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <a href="{{ route('laboratory.results.show', $result) }}"
                                       class="block truncate text-sm font-medium text-slate-800 hover:text-emerald-700">
                                        {{ $result->investigationLabel() }}
                                    </a>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $result->requisition?->patient_name ?? 'N/A' }} &middot; {{ $result->result_number }}
                                    </p>
                                </div>
                                <time class="shrink-0 text-xs text-slate-400" datetime="{{ $result->validated_at?->toIso8601String() }}">
                                    {{ $result->validated_at?->format('d M H:i') }}
                                </time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endcan

    </div>

</x-layouts.admin>