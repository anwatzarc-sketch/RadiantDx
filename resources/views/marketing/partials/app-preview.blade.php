{{--
    A drawn preview of the result-entry screen, standing in for a screenshot.

    Built from markup rather than an image so it stays sharp at every width and
    can never leak a real record: every value below is illustrative. The states,
    flags and priority shown are the application's own (RequisitionStatus,
    Interpretation, RequisitionPriority), so the picture does not promise
    anything the product does not do.
--}}

@php
    $rows = [
        ['name' => 'Haemoglobin', 'value' => '9.8', 'unit' => 'g/dL', 'range' => '12.0 – 16.0', 'flag' => 'Low', 'tone' => 'badge-warning'],
        ['name' => 'WBC count', 'value' => '7.2', 'unit' => '×10³/µL', 'range' => '4.0 – 11.0', 'flag' => 'Normal', 'tone' => 'badge-success'],
        ['name' => 'Platelets', 'value' => '38', 'unit' => '×10³/µL', 'range' => '150 – 400', 'flag' => 'Critical low', 'tone' => 'badge-danger'],
        ['name' => 'Neutrophils', 'value' => '64', 'unit' => '%', 'range' => '40 – 75', 'flag' => 'Normal', 'tone' => 'badge-success'],
    ];

    $steps = ['Submitted', 'Collected', 'Processing', 'Completed'];
@endphp

<div class="relative" aria-hidden="true">
    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-brand-400/30 via-emerald-300/20 to-sky-300/30 blur-2xl"></div>

    <div class="relative overflow-hidden rounded-2xl border border-white/60 bg-white shadow-modal ring-1 ring-slate-900/5">
        {{-- Window chrome --}}
        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5">
            <span class="size-2.5 rounded-full bg-rose-300"></span>
            <span class="size-2.5 rounded-full bg-amber-300"></span>
            <span class="size-2.5 rounded-full bg-emerald-300"></span>
            <span class="ml-3 truncate rounded-md bg-white px-3 py-0.5 text-[0.7rem] text-slate-400 ring-1 ring-slate-200">
                /admin/laboratory/results
            </span>
        </div>

        <div class="space-y-4 p-4 sm:p-5">
            {{-- Requisition header --}}
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-xs text-slate-400">{{ config('laboratory.numbering.requisition_prefix', 'REQ') }}-20260924-00142</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-900">Complete Blood Count</p>
                </div>
                <div class="flex gap-1.5">
                    <span class="badge badge-danger"><span class="status-dot bg-rose-500"></span>STAT</span>
                    <span class="badge badge-info">Processing</span>
                </div>
            </div>

            {{-- Workflow tracker --}}
            <ol class="grid grid-cols-4 gap-1.5">
                @foreach ($steps as $i => $step)
                    <li>
                        <span @class(['block h-1.5 rounded-full', 'bg-brand-600' => $i < 3, 'bg-slate-200' => $i >= 3])></span>
                        <span @class(['mt-1.5 block truncate text-[0.65rem] font-medium', 'text-brand-800' => $i < 3, 'text-slate-400' => $i >= 3])>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- Results --}}
            <div class="overflow-hidden rounded-xl border border-slate-200/80">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[0.65rem] tracking-wider text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Parameter</th>
                            <th class="px-3 py-2 font-semibold">Result</th>
                            <th class="hidden px-3 py-2 font-semibold sm:table-cell">Reference</th>
                            <th class="px-3 py-2 text-right font-semibold">Flag</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2.5 font-medium text-slate-800">{{ $row['name'] }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-900">
                                    <span class="font-semibold">{{ $row['value'] }}</span>
                                    <span class="text-slate-400">{{ $row['unit'] }}</span>
                                </td>
                                <td class="hidden px-3 py-2.5 whitespace-nowrap text-slate-500 sm:table-cell">{{ $row['range'] }}</td>
                                <td class="px-3 py-2.5 text-right"><span class="badge {{ $row['tone'] }}">{{ $row['flag'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Validation footer --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50/70 px-3.5 py-3 ring-1 ring-brand-100">
                <p class="flex items-center gap-2 text-xs text-brand-900">
                    <x-icon name="clipboard-document-check" class="size-4 text-brand-700" />
                    Entered by lab technologist · awaiting validation
                </p>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">
                    <x-icon name="check" class="size-3.5" />
                    Validate
                </span>
            </div>
        </div>
    </div>
</div>
