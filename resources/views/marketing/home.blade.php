@php
    $product = config('app.name', 'RadiantDx');
    $phone = trim((string) config('laboratory.organisation.phone', ''));
    $email = trim((string) (config('laboratory.organisation.email') ?: config('laboratory.footer.support_email', '')));

    $pillars = [
        ['icon' => 'cloud', 'title' => 'Nothing to install', 'body' => 'Runs in the browser on any workstation, tablet or phone. Add it to a home screen and it opens like a native app.'],
        ['icon' => 'shield-check', 'title' => 'Permission on every action', 'body' => 'Each role gets exactly the actions it needs. The server enforces them, so hiding a button is never the only safeguard.'],
        ['icon' => 'clipboard-document-check', 'title' => 'Two people behind every result', 'body' => 'One person enters the result and another validates it. Nothing is released until it has been validated.'],
        ['icon' => 'clock', 'title' => 'A complete audit trail', 'body' => 'Sign-ins, orders, result entries, validations, reprints and access changes are all logged with the person and the time.'],
    ];
@endphp

<x-layouts.marketing
    title="Laboratory software for modern diagnostic centres"
    description="{{ $product }} is a premium, web-based laboratory management system: requisitions, result entry with automatic flagging, two-step validation and printed reports, all in the browser."
>

    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-brand-950">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_40rem_at_85%_-10%,var(--color-brand-600),transparent),radial-gradient(40rem_30rem_at_0%_100%,var(--color-brand-800),transparent)] opacity-80"></div>
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(to_right,rgb(255_255_255/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.04)_1px,transparent_1px)] bg-[size:3.5rem_3.5rem] [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 pt-16 pb-20 sm:px-6 sm:pt-24 sm:pb-28 lg:grid-cols-[1.05fr_1fr] lg:px-8">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-brand-100">
                    <x-icon name="sparkles" class="size-3.5 text-emerald-300" />
                    Premium laboratory management system
                </span>
                <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance text-white sm:text-5xl lg:text-6xl">
                    From requisition to validated report, <span class="bg-gradient-to-r from-emerald-300 to-brand-300 bg-clip-text text-transparent">in one place.</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-brand-100/80">
                    {{ $product }} runs your laboratory in the browser. Orders are placed, samples are tracked, results are flagged against reference ranges and validated, and reports are printed with your letterhead. Every step records who did it.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a href="{{ route('marketing.products.laboratory') }}" class="btn-lg inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-base font-semibold text-brand-900 shadow-sm transition hover:bg-brand-50">
                        Explore the LIS
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                    <a href="#contact" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-base font-semibold text-white ring-1 ring-white/25 transition ring-inset hover:bg-white/10">
                        Request a demo
                    </a>
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-8">
                    <div>
                        <dt class="text-xs text-brand-200/70">Priorities</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">Routine · Urgent · STAT</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-brand-200/70">Result types</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">5 data types</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-brand-200/70">Runs on</dt>
                        <dd class="mt-1 text-sm font-semibold text-white">Any browser</dd>
                    </div>
                </dl>
            </div>

            @include('marketing.partials.app-preview')
        </div>
    </section>

    {{-- Pillars --}}
    <section class="bg-slate-50 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">Why {{ $product }}</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl">
                    Built for clinical accountability first
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-slate-600">
                    A laboratory system has to show who ordered, performed, validated and printed each result. {{ $product }} records all of it as the work happens.
                </p>
            </div>

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($pillars as $pillar)
                    <div class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-card transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-dropdown">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100 transition group-hover:bg-brand-700 group-hover:text-white">
                            <x-icon :name="$pillar['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-slate-900">{{ $pillar['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $pillar['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Product spotlight --}}
    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 px-6 py-12 shadow-modal sm:px-12 sm:py-16">
                <div class="absolute -top-24 -right-24 size-80 rounded-full bg-emerald-400/10 blur-3xl"></div>
                <div class="relative grid items-center gap-10 lg:grid-cols-[1.4fr_1fr]">
                    <div>
                        <span class="badge border-0 bg-amber-400/15 text-amber-200 ring-amber-300/30">Flagship product</span>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl">
                            {{ $product }} Laboratory Management System
                        </h2>
                        <p class="mt-4 max-w-2xl text-lg leading-relaxed text-brand-100/80">
                            Manage the test catalogue and panels, place and track requisitions, enter parameterised results, validate them and print the report. It also covers your staff, physicians and access roles.
                        </p>
                        <ul class="mt-8 grid gap-3 text-sm text-brand-50 sm:grid-cols-2">
                            @foreach (['Automatic Normal / Low / High / Critical flags', 'Reference and critical ranges per parameter', 'A4 reports with your own letterhead', 'Staff bulk import and export'] as $point)
                                <li class="flex items-start gap-2.5">
                                    <x-icon name="check-circle" class="mt-0.5 size-4.5 shrink-0 text-emerald-300" />
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex flex-col gap-3 lg:items-end">
                        <a href="{{ route('marketing.products.laboratory') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-900 shadow-sm transition hover:bg-brand-50">
                            See the full product
                            <x-icon name="arrow-right" class="size-4" />
                        </a>
                        <a href="{{ route('marketing.products.laboratory') }}#workflow" class="text-sm font-medium text-brand-200 transition hover:text-white">
                            How the workflow runs &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="scroll-mt-20 border-t border-slate-200 bg-slate-50 py-20 sm:py-24">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl">See it running on your own tests</h2>
            <p class="mt-4 text-lg leading-relaxed text-slate-600">
                We will walk you through the system with your own test menu, and set it up with your letterhead, your roles and your numbering.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                @if ($email !== '')
                    <a href="mailto:{{ $email }}?subject={{ rawurlencode($product.' LIS demo request') }}" class="btn-primary btn-lg">
                        <x-icon name="mail" class="size-5" />
                        Email {{ $email }}
                    </a>
                @endif
                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" @class(['btn-lg', 'btn-primary' => $email === '', 'btn-secondary' => $email !== ''])>
                        <x-icon name="phone" class="size-5" />
                        Call {{ $phone }}
                    </a>
                @endif
                @if ($email === '' && $phone === '')
                    <a href="{{ route('marketing.products.laboratory') }}" class="btn-primary btn-lg">Explore the product</a>
                @endif
            </div>
        </div>
    </section>

</x-layouts.marketing>
