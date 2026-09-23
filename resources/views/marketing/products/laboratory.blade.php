{{--
    Product page for the laboratory management system.

    Every claim on this page describes behaviour the application has today, so
    the copy is anchored to the code: the workflow states are RequisitionStatus,
    the flags are Interpretation, the parameter kinds are ParameterDataType.
    Capabilities the system does not yet have (a patient registry, specimen
    barcoding, FHIR exchange — see docs/fhir/READINESS-REPORT.md) are left
    unclaimed on purpose. Update this page when they ship, not before.
--}}

@php
    $product = config('app.name', 'RadiantDx');
    $email = trim((string) (config('laboratory.organisation.email') ?: config('laboratory.footer.support_email', '')));
    $phone = trim((string) config('laboratory.organisation.phone', ''));
    $demoHref = $email !== ''
        ? 'mailto:'.$email.'?subject='.rawurlencode($product.' LIS demo request')
        : route('home').'#contact';

    $workflow = [
        ['state' => 'Draft', 'body' => 'Reception or a clinician builds the order from tests and panels, and sets the priority to Routine, Urgent or STAT.'],
        ['state' => 'Submitted', 'body' => 'The order is sent to the laboratory and appears in the worklist.'],
        ['state' => 'Collected', 'body' => 'Sample collection is recorded with the time it happened.'],
        ['state' => 'Processing', 'body' => 'Technologists enter results. Each value is flagged against its reference and critical ranges as it is typed.'],
        ['state' => 'Completed', 'body' => 'A second authorised person validates the results. Only then can the report be printed.'],
    ];

    $features = [
        ['icon' => 'catalogue', 'title' => 'Test catalogue and panels', 'body' => 'Define every test your laboratory runs and group tests into panels that can be ordered in one click. Retire a test by deactivating it, without losing its history.'],
        ['icon' => 'sliders', 'title' => 'Parameters with real ranges', 'body' => 'Each parameter has units, decimal precision, reference low and high, and critical low and high. Values can be numeric, text, yes/no, positive/negative or chosen from a list.'],
        ['icon' => 'warning', 'title' => 'Automatic interpretation', 'body' => 'Results are flagged Normal, Low, High, Critical low, Critical high, Abnormal, Positive or Negative as they are entered. Critical values stand out on screen and in print.'],
        ['icon' => 'validation', 'title' => 'Two-step validation', 'body' => 'Entering a result and validating it are separate permissions. Un-validating is also a separate permission, and it is logged with the reason.'],
        ['icon' => 'print', 'title' => 'Professional reports', 'body' => 'Print A4 reports with your logo, address, licence number and signature block. Abnormal highlights keep their colour on paper, and every print is logged.'],
        ['icon' => 'users', 'title' => 'Staff and physicians', 'body' => 'Keep staff records, qualifications and a directory of attending physicians. Onboard a whole team with bulk spreadsheet import, and export the directory at any time.'],
        ['icon' => 'role', 'title' => 'Roles and a security matrix', 'body' => 'Create roles, tick the permissions each one needs, and assign them to users. Deactivate an account and access stops immediately.'],
        ['icon' => 'device-phone', 'title' => 'Installable on any device', 'body' => 'Use it on bench tablets, reception PCs and phones. It installs to the home screen and fits notched displays and touch screens.'],
        ['icon' => 'dashboard', 'title' => 'Live dashboard', 'body' => 'See today\'s workload at a glance: what is waiting, what is in process and what is completed. It opens as soon as you sign in.'],
    ];

    $security = [
        ['title' => 'Checked on the server, on every request', 'body' => 'Each protected route names the permission it needs. A request without that permission is refused, whatever the screen showed.'],
        ['title' => 'Identity you cannot type over', 'body' => 'The ordering, performing, validating and printing staff are recorded from the signed-in account, never from a form field.'],
        ['title' => 'Snapshots at the moment of work', 'body' => 'Test names, parameter ranges and staff names are copied onto the record when the work happens, so later catalogue edits do not change old reports.'],
        ['title' => 'No patient data left in the browser', 'body' => 'Only the look and feel is stored on the device. Pages with patient details are never kept offline, so nothing is left on a shared bench PC after sign-out.'],
        ['title' => 'Forced password change', 'body' => 'New accounts start with a temporary password that has to be changed before any clinical screen will open.'],
        ['title' => 'Rate-limited sign in', 'body' => 'Repeated failed sign-ins are throttled, and sign-ins, sign-outs and password changes are all recorded in the audit log.'],
    ];

    $faqs = [
        ['q' => 'Do we need to install anything?', 'a' => 'No. '.$product.' runs in a modern web browser. Staff can add it to a tablet or phone home screen, where it opens full screen like a native app.'],
        ['q' => 'Can the reports carry our own branding?', 'a' => 'Yes. Your organisation name, department, address, contact details, licence number, logo and report footer are all configuration. No code changes are needed.'],
        ['q' => 'Who can release a result?', 'a' => 'Only people whose role includes the validate permission. Entering a result, validating it, un-validating it and printing it are four separate permissions.'],
        ['q' => 'What happens if the network drops?', 'a' => 'The app tells the user straight away that they are offline and that changes cannot be saved. It shows an offline page instead of a stale copy of someone\'s results. Offline data entry is left out on purpose, because replaying a result after its validation state has changed is a patient-safety risk.'],
        ['q' => 'Where is it hosted?', 'a' => 'It runs on standard PHP and MySQL hosting over HTTPS. You can use your own server or a managed host.'],
    ];
@endphp

<x-layouts.marketing
    title="Laboratory Management System"
    description="{{ $product }} LIS: a premium, web-based laboratory management system with requisition tracking, automatic result flagging, two-step validation, branded A4 reports and a full audit trail."
>

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <div class="absolute inset-x-0 top-0 -z-10 h-[36rem] bg-[radial-gradient(45rem_25rem_at_70%_0%,var(--color-brand-200),transparent)] opacity-70"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 pt-14 pb-20 sm:px-6 sm:pt-20 lg:grid-cols-[1fr_1.05fr] lg:px-8">
            <div>
                <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
                    <a href="{{ route('home') }}" class="hover:text-brand-700">Home</a>
                    <span class="mx-1.5 text-slate-300">/</span>
                    <span class="text-slate-700">Laboratory Management System</span>
                </nav>

                <span class="badge badge-premium mt-6">
                    <x-icon name="sparkles" class="size-3.5" />
                    Premium edition
                </span>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight text-balance text-slate-900 sm:text-5xl">
                    The web-based laboratory management system for laboratories that sign their results.
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-slate-600">
                    {{ $product }} LIS covers the whole analytical cycle: ordering, collection, result entry with automatic flagging, independent validation and branded reports. Every step is recorded with who did it and when.
                </p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ $demoHref }}" class="btn-primary btn-lg">
                        Request a demo
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                    <a href="#features" class="btn-secondary btn-lg">See the features</a>
                </div>
                <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-600">
                    @foreach (['Runs in the browser', 'Role-based access', 'Full audit trail'] as $tick)
                        <li class="flex items-center gap-2">
                            <x-icon name="check-circle" class="size-4.5 text-emerald-600" />
                            {{ $tick }}
                        </li>
                    @endforeach
                </ul>
            </div>

            @include('marketing.partials.app-preview')
        </div>
    </section>

    {{-- Workflow --}}
    <section id="workflow" class="scroll-mt-20 bg-brand-950 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold tracking-wide text-emerald-300 uppercase">The workflow</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl">Five states. Every change recorded.</h2>
                <p class="mt-4 text-lg leading-relaxed text-brand-100/75">
                    Each requisition moves through the same path, and the tracker shows exactly where it is. A cancellation records who cancelled it and why.
                </p>
            </div>

            <ol class="mt-14 grid gap-4 md:grid-cols-5">
                @foreach ($workflow as $i => $step)
                    <li class="relative rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <span class="flex size-8 items-center justify-center rounded-full bg-emerald-400/15 font-mono text-sm font-semibold text-emerald-300 ring-1 ring-emerald-300/30">
                            {{ $i + 1 }}
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-white">{{ $step['state'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-brand-100/70">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="scroll-mt-20 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">Features</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl">Everything the bench needs, nothing it does not</h2>
            </div>

            <div class="mt-14 grid gap-px overflow-hidden rounded-3xl border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="bg-white p-7 transition hover:bg-brand-50/40">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-700 text-white shadow-sm">
                            <x-icon :name="$feature['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-slate-900">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Security --}}
    <section id="security" class="scroll-mt-20 bg-slate-50 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-[1fr_1.6fr] lg:px-8">
            <div>
                <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-700 text-white shadow-sm">
                    <x-icon name="shield-check" class="size-6" />
                </span>
                <h2 class="mt-6 text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl">Security designed for clinical data</h2>
                <p class="mt-4 text-lg leading-relaxed text-slate-600">
                    These protections are built into how every screen and every record works. They are not settings you have to remember to switch on.
                </p>
            </div>

            <dl class="grid gap-x-8 gap-y-10 sm:grid-cols-2">
                @foreach ($security as $item)
                    <div class="border-l-2 border-brand-500 pl-5">
                        <dt class="text-base font-semibold text-slate-900">{{ $item['title'] }}</dt>
                        <dd class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['body'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-20 py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Questions laboratories ask</h2>

            <div class="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white shadow-card">
                @foreach ($faqs as $faq)
                    <details class="group px-6 py-5 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-slate-900">
                            {{ $faq['q'] }}
                            <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing call to action --}}
    <section class="px-4 pb-20 sm:px-6 sm:pb-28 lg:px-8">
        <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950 px-6 py-14 text-center shadow-modal sm:px-12 sm:py-20">
            <div class="absolute -bottom-32 left-1/2 size-96 -translate-x-1/2 rounded-full bg-emerald-400/15 blur-3xl"></div>
            <div class="relative">
                <h2 class="text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl">Put your laboratory on {{ $product }}</h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-brand-100/80">
                    We will demonstrate it with your own test menu and set it up with your branding.
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ $demoHref }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-900 shadow-sm transition hover:bg-brand-50">
                        Request a demo
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                    @if ($phone !== '')
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="inline-flex items-center gap-2 rounded-xl px-6 py-3 text-base font-semibold text-white ring-1 ring-white/30 transition ring-inset hover:bg-white/10">
                            <x-icon name="phone" class="size-4" />
                            {{ $phone }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

</x-layouts.marketing>
