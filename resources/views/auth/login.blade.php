<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <x-pwa-head />
    <title>Sign in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-brand-900">
<div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6">
    <div class="mx-auto w-full max-w-md">

        <div class="mb-6 flex flex-col items-center text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-white/15 text-white">
                <x-icon name="beaker" class="size-6" />
            </span>
            <h1 class="mt-3 text-lg font-semibold text-white">{{ config('laboratory.organisation.name') }}</h1>
            <p class="text-sm text-brand-200/80">{{ config('app.name') }}</p>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-xl sm:p-8">
            <h2 class="text-base font-semibold text-slate-900">Sign in</h2>
            <p class="mt-1 text-sm text-slate-500">Use the account issued by your administrator.</p>

            @if (session('status'))
                <div class="mt-4 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-900 ring-1 ring-sky-600/20 ring-inset">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 flex items-start gap-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-rose-600/20 ring-inset"
                     role="alert">
                    <x-icon name="warning" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
                @csrf

                <x-form.field name="email" label="Email address" required>
                    <x-form.input name="email" type="email" autocomplete="username" required autofocus />
                </x-form.field>

                <x-form.field name="password" label="Password" required>
                    <x-form.input name="password" type="password" autocomplete="current-password" required />
                </x-form.field>

                <x-form.checkbox name="remember" label="Remember me on this device" />

                <x-button type="submit" variant="primary" class="w-full">Sign in</x-button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-brand-200/70">
            Access is monitored. Sign in only with credentials issued to you.
        </p>
    </div>
</div>
</body>
</html>
