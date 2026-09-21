@props([
    'title' => null,
    'breadcrumbs' => [],
    'back' => null,
    'backLabel' => 'Back',
])

{{--
    The administration shell: branding, breadcrumb, account menu, and a sidebar
    that collapses into a drawer below the lg breakpoint.

    The top bar spans the full width and carries the branding, so the sidebar
    below it is navigation only. The breadcrumb sits with the page content
    rather than in the bar, which keeps the bar to two things — who the site is
    and who you are signed in as.

    $breadcrumbs is an ordered map of label => url, where a null url marks the
    current page.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8" />
    <x-pwa-head />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ isset($title) && $title ? $title . ' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-900 antialiased">
<div
    x-data="{ sidebar: false }"
    x-effect="document.body.classList.toggle('drawer-open', sidebar)"
    class="app-shell flex min-h-full flex-col"
>

    {{-- Top bar --}}
    <header class="app-topbar sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex items-center gap-3 px-4 py-2.5 sm:px-6">
            <button
                type="button"
                class="tap-target inline-flex items-center justify-center rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-100 lg:hidden"
                x-on:click="sidebar = true"
            >
                <span class="sr-only">Open navigation</span>
                <x-icon name="menu" class="size-5" />
            </button>

            {{-- Branding. The mark falls back to an icon tile when the
                 deployment has no artwork of its own. --}}
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                <x-org-logo size="nav">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-700 text-white">
                        <x-icon name="beaker" class="size-5" />
                    </span>
                </x-org-logo>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold tracking-tight text-slate-900 uppercase">
                        {{ config('laboratory.organisation.name', config('app.name')) }}
                    </span>
                    <span class="block truncate text-[0.7rem] font-medium tracking-wider text-slate-500 uppercase">
                        Laboratory Management
                    </span>
                </span>
            </a>

            {{-- Account menu --}}
            @auth
                <div x-data="{ open: false }" class="relative ml-auto shrink-0">
                    <button
                        type="button"
                        class="tap-target flex items-center gap-2.5 rounded-xl px-2 py-1.5 text-left transition hover:bg-slate-100"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-haspopup="menu"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 ring-1 ring-slate-200 ring-inset">
                            {{ method_exists(auth()->user(), 'initials') ? auth()->user()->initials() : strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                        </span>
                        <span class="hidden min-w-0 sm:block">
                            <span class="block truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs text-slate-500">
                                {{ method_exists(auth()->user(), 'roleName') ? auth()->user()->roleName() : 'User' }}
                            </span>
                        </span>
                        <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-400" />
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        x-on:click.outside="open = false"
                        x-on:keydown.escape.window="open = false"
                        class="absolute right-0 z-30 mt-1 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        role="menu"
                    >
                        <div class="border-b border-slate-100 px-3 py-2">
                            <p class="truncate text-sm font-medium text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                            <x-icon name="profile" class="size-4 text-slate-400" />
                            Profile and security
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                                <x-icon name="logout" class="size-4 text-slate-400" />
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </header>

    <div class="flex min-w-0 flex-1">

        {{-- Mobile drawer backdrop --}}
        <div
            x-show="sidebar"
            x-cloak
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm lg:hidden"
            x-on:click="sidebar = false"
            aria-hidden="true"
        ></div>

        {{-- Sidebar: a fixed drawer below lg, a static column above it --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-brand-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
            x-bind:class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            x-on:keydown.escape.window="sidebar = false"
        >
            {{-- The branding lives in the top bar, which the drawer covers on
                 mobile, so the drawer carries its own heading and dismiss. --}}
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3.5 lg:hidden">
                <span class="text-xs font-semibold tracking-wider text-brand-200/80 uppercase">Navigation</span>
                <button
                    type="button"
                    class="tap-target inline-flex items-center justify-center rounded-lg p-1 text-brand-100 transition hover:bg-white/10"
                    x-on:click="sidebar = false"
                >
                    <span class="sr-only">Close navigation</span>
                    <x-icon name="close" class="size-5" />
                </button>
            </div>

            @include('layouts.partials.sidebar')

            <div class="app-bottom-safe mt-auto border-t border-white/10 px-4 py-3 text-xs text-brand-200/70">
                <p>{{ config('laboratory.organisation.department', 'Department of Laboratory Services') }}</p>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    {{--
                        The breadcrumb row also carries the page's "back" link
                        when one is given, so the two navigation affordances sit
                        together and the page title starts a line higher.
                    --}}
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <nav aria-label="Breadcrumb">
                        <ol class="flex items-center gap-1.5 text-sm text-slate-500">
                            <li class="hidden sm:block">
                                <a href="{{ route('dashboard') }}" class="transition hover:text-brand-700">Home</a>
                            </li>
                            @foreach ($breadcrumbs as $label => $url)
                                <li class="hidden items-center gap-1.5 sm:flex">
                                    <x-icon name="chevron-right" class="size-3.5 shrink-0 text-slate-300" aria-hidden="true" />
                                    @if ($url)
                                        <a href="{{ $url }}" class="transition hover:text-brand-700">{{ $label }}</a>
                                    @else
                                        <span class="font-medium text-slate-800">{{ $label }}</span>
                                    @endif
                                </li>
                            @endforeach
                            @if ($title)
                                <li class="truncate font-medium text-slate-800 sm:hidden">{{ $title }}</li>
                            @endif
                        </ol>
                    </nav>

                    @if ($back)
                        <a href="{{ $back }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition hover:text-brand-700">
                            <x-icon name="arrow-left" class="size-3.5" />
                            {{ $backLabel }}
                        </a>
                    @endif
                    </div>

                    <div class="space-y-6">
                        <x-flash />
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <x-app-footer />
        </div>
    </div>
</div>
</body>
</html>
