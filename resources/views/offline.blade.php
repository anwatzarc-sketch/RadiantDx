{{--
    Served by the service worker when a navigation is attempted with no
    network. Deliberately generic: it never shows cached patient data, because
    nothing patient identifying is ever written to the cache.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8" />
    <title>Offline · {{ config('app.name') }}</title>
    <x-pwa-head />
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center px-4 font-sans text-slate-900 antialiased">
    <main class="w-full max-w-sm text-center">
        <x-org-logo size="lg" class="mx-auto">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-brand-700 text-white">
                <x-icon name="beaker" class="size-8" />
            </span>
        </x-org-logo>

        <h1 class="mt-6 text-lg font-semibold tracking-tight">No network connection</h1>

        <p class="mt-2 text-sm text-slate-600">
            This page needs to reach the laboratory server. Patient and result data is
            never stored on this device, so it cannot be shown while offline.
        </p>

        <button type="button" onclick="window.location.reload()" class="btn-primary mt-6">
            Try again
        </button>

        <p class="mt-4 text-xs text-slate-500">
            {{ config('laboratory.organisation.name', config('app.name')) }}
        </p>
    </main>
</body>
</html>
