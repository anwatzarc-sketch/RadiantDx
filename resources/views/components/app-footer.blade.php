{{--
    The application footer, shown on every page of the administration shell.

    Three things this deliberately does rather than the obvious:

      * Its navigation is filtered by permission, exactly the way the sidebar
        is. A footer is navigation, and offering a receptionist a "Roles and
        security matrix" link that answers 403 is worse than not offering it.
        The declarative list below describes the whole product; what an
        individual sees is the part of it they can reach.

      * Its identity, support desk, accreditations and policy links come from
        config/laboratory.php. Another site deploying this application gets its
        own footer without editing a template, and anything left unconfigured
        is omitted rather than rendered empty or pointed at a dead anchor.

      * Its connection indicator reports something true. The browser's own
        online/offline state is what decides whether a result entered on a
        tablet in the specimen room will actually save, and it is the same
        signal that puts the service worker's offline page on screen — so it
        earns a permanent spot. A hard-coded "all systems operational" badge
        would be decoration that asserts something nothing here has checked.

    Print needs no handling: app.css already hides any <footer> outside <main>,
    so the laboratory report carries its own signature block and not this.
--}}

@php
    $user = auth()->user();

    $organisation = (string) config('laboratory.organisation.name', config('app.name'));
    $department = (string) config('laboratory.organisation.department', '');
    $tagline = trim((string) config('laboratory.footer.tagline', ''));

    $hotline = trim((string) config('laboratory.footer.support_hotline', ''));
    $supportEmail = trim((string) config('laboratory.footer.support_email', ''));

    $accreditations = collect(explode(',', (string) config('laboratory.footer.accreditations', '')))
        ->map(fn (string $tag): string => trim($tag))
        ->filter()
        ->values();

    $policies = collect([
        'Privacy policy' => (string) config('laboratory.footer.privacy_url', ''),
        'Terms of service' => (string) config('laboratory.footer.terms_url', ''),
        'Data protection' => (string) config('laboratory.footer.data_protection_url', ''),
    ])->filter(fn (string $url): bool => trim($url) !== '');

    /*
     * Mirrors the sidebar's structure so the two cannot disagree about what
     * the product contains — a module added to one belongs in the other.
     */
    $columns = [
        'Laboratory catalogue' => [
            ['label' => 'Requisitions and specimens', 'href' => route('laboratory.requisitions.index'), 'permission' => 'laboratory.requisition.view'],
            ['label' => 'Results entry and validation', 'href' => route('laboratory.results.index'), 'permission' => 'laboratory.result.view'],
            ['label' => 'Laboratory tests', 'href' => route('laboratory.tests.index'), 'permission' => 'laboratory.test.view'],
            ['label' => 'Diagnostic panels', 'href' => route('laboratory.panels.index'), 'permission' => 'laboratory.panel.view'],
            ['label' => 'Test parameters and references', 'href' => route('laboratory.parameters.index'), 'permission' => 'laboratory.parameter.view'],
        ],
        'Administration' => [
            ['label' => 'Staff directory', 'href' => route('administration.staff.index'), 'permission' => 'staff.view'],
            ['label' => 'Attending physicians', 'href' => route('administration.physicians.index'), 'permission' => 'staff.view'],
            ['label' => 'System users and access', 'href' => route('administration.users.index'), 'permission' => 'users.view'],
            ['label' => 'Roles and security matrix', 'href' => route('administration.roles.index'), 'permission' => 'roles.view'],
        ],
    ];

    $visibleColumns = collect($columns)
        ->map(fn (array $items): array => array_values(array_filter(
            $items,
            fn (array $item): bool => $user !== null && $user->hasPermission($item['permission']),
        )))
        ->filter(fn (array $items): bool => $items !== []);
@endphp

<footer class="mt-auto border-t-[3px] border-brand-500 bg-brand-950 text-sm text-brand-100/80">

    <div class="mx-auto max-w-[90rem] px-4 pt-10 pb-8 sm:px-6 lg:px-8">
        <div class="grid gap-x-10 gap-y-9 sm:grid-cols-2 xl:grid-cols-[2.2fr_1fr_1fr_1.3fr]">

            {{-- Identity --}}
            <div class="sm:col-span-2 xl:col-span-1">
                <div class="flex items-center gap-3">
                    <x-org-logo size="nav">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-700 text-white">
                            <x-icon name="beaker" class="size-5" />
                        </span>
                    </x-org-logo>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-extrabold tracking-wide text-white uppercase">
                            {{ $organisation }}
                        </span>
                        <span class="block truncate text-[0.7rem] font-semibold tracking-widest text-brand-300 uppercase">
                            Laboratory Management
                        </span>
                    </span>
                </div>

                @if ($tagline !== '')
                    <p class="mt-4 max-w-md text-[0.8125rem] leading-relaxed text-brand-200/70">{{ $tagline }}</p>
                @endif

                {{--
                    Reports the browser's own connectivity, which is what
                    actually decides whether work in progress can be saved.
                    Rendered online-first so the badge is never wrong in the
                    moment before Alpine starts, and never blank without it.
                --}}
                <div
                    x-data="{ online: true }"
                    x-init="online = navigator.onLine"
                    x-on:online.window="online = true"
                    x-on:offline.window="online = false"
                    class="mt-5 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs"
                    x-bind:class="online
                        ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
                        : 'border-amber-400/40 bg-amber-400/10 text-amber-100'"
                    role="status"
                >
                    <span
                        class="size-2 shrink-0 rounded-full bg-emerald-400 shadow-[0_0_8px_var(--color-emerald-400)]"
                        x-bind:class="online
                            ? 'bg-emerald-400 shadow-[0_0_8px_var(--color-emerald-400)]'
                            : 'bg-amber-400 shadow-[0_0_8px_var(--color-amber-400)]'"
                    ></span>
                    <span x-show="online">Connected — <strong class="font-semibold text-white">results save normally</strong></span>
                    <span x-show="! online" x-cloak>Offline — <strong class="font-semibold text-white">changes cannot be saved</strong></span>
                </div>
            </div>

            {{-- Navigation, filtered to what this person can actually open --}}
            @foreach ($visibleColumns as $heading => $items)
                <nav aria-label="{{ $heading }}">
                    <h2 class="mb-4 text-xs font-bold tracking-wider text-white uppercase">{{ $heading }}</h2>
                    <ul class="space-y-2.5">
                        @foreach ($items as $item)
                            <li>
                                <a
                                    href="{{ $item['href'] }}"
                                    class="inline-block text-[0.8125rem] text-brand-200/80 transition-all hover:pl-1 hover:text-emerald-300"
                                >{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach

            {{-- Support and accreditation --}}
            <div>
                <h2 class="mb-4 text-xs font-bold tracking-wider text-white uppercase">Support and security</h2>

                <div class="space-y-3 text-[0.8125rem] text-brand-200/80">
                    @if ($hotline !== '')
                        <p class="flex items-center gap-2.5">
                            <x-icon name="phone" class="size-4 shrink-0 text-brand-400" />
                            <span>Laboratory hotline: <strong class="font-semibold text-white">{{ $hotline }}</strong></span>
                        </p>
                    @endif

                    @if ($supportEmail !== '')
                        <p class="flex items-center gap-2.5">
                            <x-icon name="mail" class="size-4 shrink-0 text-brand-400" />
                            <a href="mailto:{{ $supportEmail }}" class="min-w-0 break-all transition hover:text-emerald-300">{{ $supportEmail }}</a>
                        </p>
                    @endif

                    @if ($department !== '')
                        <p class="flex items-center gap-2.5">
                            <x-icon name="building" class="size-4 shrink-0 text-brand-400" />
                            <span>{{ $department }}</span>
                        </p>
                    @endif
                </div>

                @if ($accreditations->isNotEmpty())
                    <ul class="mt-5 flex flex-wrap gap-2">
                        @foreach ($accreditations as $tag)
                            <li class="rounded border border-brand-500/25 bg-brand-800 px-2 py-1 text-[0.6875rem] font-semibold text-emerald-200">
                                {{ $tag }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>

    {{-- Copyright bar. app-bottom-safe keeps it clear of the home indicator
         when the application is running installed on a phone. --}}
    <div class="app-bottom-safe border-t border-white/10 bg-black/25">
        <div class="mx-auto flex max-w-[90rem] flex-col gap-3 px-4 py-4 text-xs text-brand-200/60 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>
                &copy; {{ now()->year }} <strong class="font-semibold text-brand-100">{{ $organisation }}</strong>.
                All rights reserved. {{ config('app.name') }}
                <span class="whitespace-nowrap">v{{ config('laboratory.version', '1.0.0') }}</span>
            </p>

            @if ($policies->isNotEmpty())
                <nav aria-label="Policies">
                    <ul class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @foreach ($policies as $label => $url)
                            @unless ($loop->first)
                                <li aria-hidden="true" class="text-brand-700">&bull;</li>
                            @endunless
                            <li><a href="{{ $url }}" class="transition hover:text-white">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>

</footer>
