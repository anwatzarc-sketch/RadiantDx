{{--
    The printed laboratory report.

    Deliberately a standalone template: no navigation, no actions, and laid out
    for A4. Everything shown is read from the snapshot stored on the result, so
    a report reprinted later reproduces exactly what was issued.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <x-pwa-head />
    <title>Laboratory report {{ $requisition->requisition_number }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-200 print:bg-white">

<div class="no-print sticky top-0 z-10 border-b border-slate-300 bg-white px-4 py-3 shadow-sm">
    <div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-900">Laboratory report</p>
            <p class="text-xs text-slate-500">
                {{ $requisition->requisition_number }} &middot; {{ $requisition->patient_name }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url()->previous() }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-300 ring-inset hover:bg-slate-50">
                Back
            </a>
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                Print
            </button>
        </div>
    </div>
</div>

<main class="report-sheet mx-auto my-4 max-w-[210mm] bg-white p-5 text-slate-900 shadow-lg sm:my-6 sm:p-8 md:p-[14mm] print:my-0 print:p-[14mm] print:shadow-none">

    {{-- Letterhead --}}
    <header class="avoid-break border-b-2 border-slate-900 pb-3">
        <div class="flex items-start justify-between gap-6">
            {{-- Mark and identity. Both read from config, so neither the
                 artwork nor its absence changes how this block lays out. --}}
            <div class="flex min-w-0 items-start gap-4">
                <x-org-logo size="md" class="mt-0.5" />
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight uppercase">{{ $organisation['name'] }}</h1>
                    <p class="text-sm font-medium text-slate-700">{{ $organisation['department'] }}</p>
                    @if ($organisation['address'])
                        <p class="mt-0.5 text-xs text-slate-600">{{ $organisation['address'] }}</p>
                    @endif
                    <p class="text-xs text-slate-600">
                        @if ($organisation['phone']) Tel {{ $organisation['phone'] }} @endif
                        @if ($organisation['email']) &middot; {{ $organisation['email'] }} @endif
                        @if ($organisation['licence_number']) &middot; Licence {{ $organisation['licence_number'] }} @endif
                    </p>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">Laboratory report</p>
                <p class="mt-0.5 font-mono text-sm font-bold">{{ $requisition->requisition_number }}</p>
                <p class="mt-1 text-xs text-slate-600">
                    Printed {{ $generatedAt->format('d M Y H:i') }}
                </p>
            </div>
        </div>
    </header>

    @if ($isProvisional)
        <p class="avoid-break mt-3 border border-amber-600 bg-amber-50 px-3 py-2 text-xs font-bold tracking-wide text-amber-900 uppercase">
            Provisional report — contains results that have not been validated
        </p>
    @endif

    @if ($hasCriticalValues)
        <p class="avoid-break mt-3 border border-rose-600 bg-rose-50 px-3 py-2 text-xs font-bold tracking-wide text-rose-900 uppercase">
            Contains a critical value — clinical attention required
        </p>
    @endif

    {{-- Patient and request --}}
    <section class="avoid-break mt-4 grid grid-cols-2 gap-x-8 border-b border-slate-300 pb-3 text-sm">
        <div>
            <h2 class="mb-1 text-xs font-bold tracking-wider text-slate-500 uppercase">Patient</h2>
            <dl class="space-y-0.5">
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Name</dt>
                    <dd class="font-semibold">{{ $requisition->patient_name }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Patient ID</dt>
                    <dd class="font-mono">{{ $requisition->patient_identifier }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Gender</dt>
                    <dd>{{ $requisition->patient_gender?->label() ?? 'Not recorded' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Age</dt>
                    <dd>{{ $requisition->ageLabel() }}</dd>
                </div>
                @if ($requisition->patient_date_of_birth)
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-slate-500">Date of birth</dt>
                        <dd>{{ $requisition->patient_date_of_birth->format('d M Y') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div>
            <h2 class="mb-1 text-xs font-bold tracking-wider text-slate-500 uppercase">Request</h2>
            <dl class="space-y-0.5">
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Requisition</dt>
                    <dd class="font-mono">{{ $requisition->requisition_number }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Request date</dt>
                    <dd>{{ $requisition->requested_date?->format('d M Y') }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Clinician</dt>
                    <dd>{{ $requisition->requesting_clinician ?: '—' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Department</dt>
                    <dd>{{ $requisition->requesting_department ?: '—' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-28 shrink-0 text-slate-500">Priority</dt>
                    <dd>{{ $requisition->priority->label() }}</dd>
                </div>
            </dl>
        </div>
    </section>

    @if ($requisition->clinical_indication)
        <section class="avoid-break mt-3 text-sm">
            <h2 class="text-xs font-bold tracking-wider text-slate-500 uppercase">Clinical indication</h2>
            <p class="mt-0.5">{{ $requisition->clinical_indication }}</p>
        </section>
    @endif

    {{-- Investigations --}}
    @forelse ($results as $result)
        <section class="mt-5 {{ ! $loop->first ? 'avoid-break' : '' }}">
            <div class="flex items-end justify-between gap-4 border-b border-slate-400 pb-1">
                <h2 class="text-sm font-bold tracking-wide uppercase">
                    {{ $result->panel_name ? $result->panel_name.' — ' : '' }}{{ $result->test_name }}
                </h2>
                <p class="shrink-0 font-mono text-xs text-slate-600">
                    {{ $result->result_number }}@if ($result->revision > 1) · rev {{ $result->revision }} @endif
                </p>
            </div>

            {{-- A result only means anything read beside its unit, reference
                 range and flag, so this table is never stacked into cards the
                 way .data-table is on a phone. It scrolls sideways as a unit
                 instead, keeping every row intact. Print is untouched: on
                 paper the sheet is full width and the table fits. --}}
            {{-- Each distinct caveat about how a reference range was chosen is
                 numbered once per investigation and marked on its rows, so
                 "adult default used" or "no range for this age" is never left
                 for the reader to infer from a blank or a familiar number. --}}
            @php($rangeNotes = $result->parameters->map->rangeFootnote()->filter()->unique()->values())

            <div class="overflow-x-auto print:overflow-visible">
                <table class="mt-2 w-full min-w-[30rem] border-collapse text-sm print:min-w-0">
                    <thead>
                        <tr class="border-b border-slate-300">
                            <th scope="col" class="py-1 pr-2 text-left text-xs font-bold tracking-wide text-slate-600 uppercase">Parameter</th>
                            <th scope="col" class="py-1 pr-2 text-right text-xs font-bold tracking-wide text-slate-600 uppercase">Result</th>
                            <th scope="col" class="py-1 pr-2 text-left text-xs font-bold tracking-wide text-slate-600 uppercase">Unit</th>
                            <th scope="col" class="py-1 pr-2 text-left text-xs font-bold tracking-wide text-slate-600 uppercase">Reference</th>
                            <th scope="col" class="py-1 text-center text-xs font-bold tracking-wide text-slate-600 uppercase">Flag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->parameters as $parameter)
                            <tr class="border-b border-slate-100">
                                <td class="py-1 pr-2">
                                    {{ $parameter->parameter_name }}
                                    @if ($parameter->comment)
                                        <span class="block text-xs text-slate-500">{{ $parameter->comment }}</span>
                                    @endif
                                </td>
                                <td class="py-1 pr-2 text-right font-mono {{ $parameter->isOutsideReference() ? 'font-bold' : '' }}">
                                    {{ $parameter->displayValue() ?: 'Not reported' }}
                                </td>
                                <td class="py-1 pr-2 text-slate-600">{{ $parameter->unit ?: '' }}</td>
                                <td class="py-1 pr-2 text-slate-600">
                                    {{ $parameter->referenceSummary() ?: '' }}
                                    @if ($parameter->rangeFootnote())
                                        <sup class="font-semibold text-slate-700">{{ $rangeNotes->search($parameter->rangeFootnote()) + 1 }}</sup>
                                    @endif
                                </td>
                                <td class="py-1 text-center font-bold {{ $parameter->interpretation?->isCritical() ? 'text-rose-700' : ($parameter->isOutsideReference() ? 'text-amber-700' : 'text-slate-500') }}">
                                    {{ $parameter->interpretation?->reportFlag() ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rangeNotes->isNotEmpty())
                <ol class="avoid-break mt-1.5 space-y-0.5 text-xs text-slate-600">
                    @foreach ($rangeNotes as $note)
                        <li><sup class="font-semibold text-slate-700">{{ $loop->iteration }}</sup> {{ $note }}</li>
                    @endforeach
                </ol>
            @endif

            @if ($result->interpretation)
                <div class="avoid-break mt-2 text-sm">
                    <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Interpretation</p>
                    <p class="mt-0.5 whitespace-pre-line">{{ $result->interpretation }}</p>
                </div>
            @endif

            @if ($result->comments)
                <div class="avoid-break mt-2 text-sm">
                    <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Laboratory comments</p>
                    <p class="mt-0.5 whitespace-pre-line">{{ $result->comments }}</p>
                </div>
            @endif

            <p class="mt-2 text-xs text-slate-600">
                @if ($result->isValidated())
                    {{-- From the frozen snapshot, never a live join: a report
                         reprinted years from now must name whoever validated it
                         then, with the speciality they held at the time. --}}
                    Validated by {{ $result->actorDisplayName('validated_by') ?? 'the laboratory' }}
                    @if ($result->actorSpecialityLabel('validated_by'))
                        ({{ $result->actorSpecialityLabel('validated_by') }})
                    @endif
                    on {{ $result->validated_at?->format('d M Y H:i') }}.
                @else
                    <span class="font-semibold text-amber-800">
                        Not validated. This investigation is provisional and must not be used for clinical decisions.
                    </span>
                @endif
                @if ($result->actorDisplayName('performed_by'))
                    Performed by {{ $result->actorDisplayName('performed_by') }}.
                @endif
            </p>
        </section>
    @empty
        <section class="mt-6 border border-slate-300 px-4 py-8 text-center text-sm text-slate-600">
            <p class="font-semibold">No validated results are available for this requisition.</p>
            <p class="mt-1">A report is issued once at least one investigation has been validated.</p>
        </section>
    @endforelse

    {{-- Flag key: the report never relies on colour alone --}}
    @if ($results->isNotEmpty())
        <section class="avoid-break mt-5 border-t border-slate-300 pt-2 text-xs text-slate-600">
            <p>
                <span class="font-semibold">Flags:</span>
                N normal &middot; L low &middot; H high &middot; LL critical low &middot; HH critical high &middot;
                A abnormal &middot; POS positive &middot; NEG negative
            </p>
        </section>
    @endif

    {{-- Authorisation --}}
    <footer class="avoid-break mt-6 border-t-2 border-slate-900 pt-3 text-xs text-slate-700">
        <div class="flex items-end justify-between gap-8">
            <div class="flex min-w-0 items-start gap-3">
                <x-org-logo size="sm" />
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ $organisation['name'] }} — {{ $organisation['department'] }}</p>
                    <p class="mt-1 max-w-[110mm]">{{ $footer }}</p>
                </div>
            </div>
            <div class="w-52 shrink-0 text-center">
                <div class="h-10 border-b border-slate-500"></div>
                <p class="mt-1 font-semibold text-slate-900">Authorised signatory</p>
                <p>{{ $results->first()?->actorDisplayName('validated_by') ?? '' }}</p>
                @if ($results->first()?->actorSpecialityLabel('validated_by'))
                    <p class="text-[0.7rem] text-slate-600">
                        {{ $results->first()->actorSpecialityLabel('validated_by') }}
                    </p>
                @endif
            </div>
        </div>
        <p class="mt-3 text-center text-[0.65rem] text-slate-500">
            Report generated {{ $generatedAt->format('d M Y H:i') }} &middot;
            {{ $requisition->requisition_number }} &middot; {{ $requisition->patient_name }}
        </p>
    </footer>
</main>

</body>
</html>
