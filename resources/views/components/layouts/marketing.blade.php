@props([
    'title' => null,
    'description' => null,
])

{{--
    The public marketing shell: a light top bar, the page, and a footer that
    carries contact details.

    Deliberately separate from the administration shell. That shell's footer is
    filtered by permission and its head registers the installable app; neither
    belongs on a page written for someone who has never signed in. The web app
    manifest in particular is left out, so a visitor's browser does not offer to
    install the laboratory application from a brochure page.

    Contact details come from config/laboratory.php, and anything left
    unconfigured is omitted rather than rendered empty.
--}}

@php
    $product = (string) config('app.name', 'RadiantDx');

    $phone = trim((string) config('laboratory.organisation.phone', ''));
    $email = trim((string) (config('laboratory.organisation.email') ?: config('laboratory.footer.support_email', '')));

    $demoHref = $email !== ''
        ? 'mailto:'.$email.'?subject='.rawurlencode($product.' LIS demo request')
        : route('home').'#contact';

    $nav = [
        ['label' => 'Laboratory LIS', 'href' => route('marketing.products.laboratory'), 'route' => 'marketing.products.laboratory'],
        ['label' => 'Workflow', 'href' => route('marketing.products.laboratory').'#workflow', 'route' => null],
        ['label' => 'Security', 'href' => route('marketing.products.laboratory').'#security', 'route' => null],
        ['label' => 'Contact', 'href' => route('home').'#contact', 'route' => null],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth bg-white">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="{{ config('laboratory.pwa.theme_color', '#00303c') }}" />
    <meta name="color-scheme" content="light" />

    <title>{{ $title ? $title.' · ' : '' }}{{ $product }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}" />
        <meta property="og:description" content="{{ $description }}" />
    @endif
    <meta property="og:title" content="{{ $title ?? $product }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <link rel="canonical" href="{{ url()->current() }}" />

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-mark.svg') }}" />
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white font-sans text-slate-900 antialiased">
<div x-data="{ menu: false }" class="app-shell flex min-h-screen flex-col">

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-dropdown">
        Skip to content
    </a>

    {{-- Top bar --}}
    <header class="app-topbar sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 backdrop-blur-lg">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                <x-org-logo size="nav">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-700 text-white">
                        <x-icon name="beaker" class="size-5" />
                    </span>
                </x-org-logo>
                <span class="text-lg font-bold tracking-tight text-slate-900">{{ $product }}</span>
            </a>

            <nav aria-label="Main" class="hidden md:block">
                <ul class="flex items-center gap-1">
                    @foreach ($nav as $item)
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                @class([
                                    'rounded-lg px-3 py-2 text-sm font-medium transition',
                                    'text-brand-800 bg-brand-50' => $item['route'] && request()->routeIs($item['route']),
                                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! ($item['route'] && request()->routeIs($item['route'])),
                                ])
                            >{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-secondary hidden sm:inline-flex">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">Sign in</a>
                @endauth
                <a href="{{ $demoHref }}" class="btn-primary hidden sm:inline-flex">Request a demo</a>

                <button
                    type="button"
                    class="tap-target inline-flex items-center justify-center rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 md:hidden"
                    x-on:click="menu = ! menu"
                    x-bind:aria-expanded="menu.toString()"
                    aria-controls="marketing-menu"
                >
                    <span class="sr-only">Toggle navigation</span>
                    <x-icon name="menu" class="size-5" x-show="! menu" />
                    <x-icon name="close" class="size-5" x-show="menu" x-cloak />
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div id="marketing-menu" x-show="menu" x-cloak x-transition.opacity class="border-t border-slate-200 bg-white md:hidden">
            <nav aria-label="Main" class="space-y-1 px-4 py-3">
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}" x-on:click="menu = false" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <div class="grid grid-cols-2 gap-2 pt-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-secondary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary">Sign in</a>
                    @endauth
                    <a href="{{ $demoHref }}" class="btn-primary">Request a demo</a>
                </div>
            </nav>
        </div>
    </header>

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="border-t-[3px] border-brand-500 bg-brand-950 text-sm text-brand-100/80">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-[2fr_1fr_1fr] lg:px-8">
            <div>
                <div class="flex items-center gap-2.5">
                    <x-org-logo size="nav" :path="config('laboratory.organisation.logo_inverse') ?: config('laboratory.organisation.logo')">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-brand-700 text-white">
                            <x-icon name="beaker" class="size-5" />
                        </span>
                    </x-org-logo>
                    <span class="text-lg font-bold tracking-tight text-white">{{ $product }}</span>
                </div>
                <p class="mt-4 max-w-sm leading-relaxed text-brand-200/70">
                    Web-based laboratory software that takes every test from requisition to validated, printed report, with a record of who did what at every step.
                </p>
            </div>

            <nav aria-label="Product">
                <h2 class="mb-4 text-xs font-bold tracking-wider text-white uppercase">Product</h2>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('marketing.products.laboratory') }}" class="transition hover:text-emerald-300">Laboratory Management System</a></li>
                    <li><a href="{{ route('marketing.products.laboratory') }}#features" class="transition hover:text-emerald-300">Features</a></li>
                    <li><a href="{{ route('marketing.products.laboratory') }}#security" class="transition hover:text-emerald-300">Security and audit</a></li>
                    <li><a href="{{ route('marketing.products.laboratory') }}#faq" class="transition hover:text-emerald-300">Questions</a></li>
                </ul>
            </nav>

            <div>
                <h2 class="mb-4 text-xs font-bold tracking-wider text-white uppercase">Talk to us</h2>
                <ul class="space-y-3">
                    @if ($phone !== '')
                        <li class="flex items-center gap-2.5">
                            <x-icon name="phone" class="size-4 shrink-0 text-brand-400" />
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="transition hover:text-emerald-300">{{ $phone }}</a>
                        </li>
                    @endif
                    @if ($email !== '')
                        <li class="flex items-center gap-2.5">
                            <x-icon name="mail" class="size-4 shrink-0 text-brand-400" />
                            <a href="mailto:{{ $email }}" class="break-all transition hover:text-emerald-300">{{ $email }}</a>
                        </li>
                    @endif
                    <li class="flex items-center gap-2.5">
                        <x-icon name="lock" class="size-4 shrink-0 text-brand-400" />
                        @auth
                            <a href="{{ route('dashboard') }}" class="transition hover:text-emerald-300">Go to dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="transition hover:text-emerald-300">Staff sign in</a>
                        @endauth
                    </li>
                </ul>
            </div>
        </div>

        <div class="app-bottom-safe border-t border-white/10 bg-black/25">
            <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-brand-200/60 sm:px-6 lg:px-8">
                &copy; {{ now()->year }} <strong class="font-semibold text-brand-100">{{ $product }}</strong>. All rights reserved.
            </p>
        </div>
    </footer>
</div>
</body>
</html>
