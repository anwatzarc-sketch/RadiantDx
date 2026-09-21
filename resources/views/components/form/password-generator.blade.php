@props([
    'fields' => ['password', 'password_confirmation'],
    'length' => 16,
])

{{--
    Offers a generated credential on the forms where one administrator sets a
    password for somebody else. It is deliberately absent from the profile
    page: a password you choose for yourself is one you have to remember, and a
    generated one there would only invite writing it down.

    Cloaked, because a button that cannot generate anything is worse than no
    button at all. Without Alpine the fields are simply typed into as before.
--}}

<div
    x-data="passwordGenerator({ fields: @js($fields), length: {{ (int) $length }} })"
    x-cloak
    {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-3 gap-y-2']) }}
>
    <x-button type="button" size="sm" icon="key" x-on:click="generate()">
        Generate strong password
    </x-button>

    <div x-show="generated !== ''" class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <code
            x-text="generated"
            class="rounded-md bg-slate-100 px-2 py-1 font-mono text-sm tracking-wide text-slate-900 select-all"
        ></code>

        <x-button type="button" size="sm" variant="ghost" x-on:click="copy()">
            <span x-text="copied ? 'Copied' : 'Copy'"></span>
        </x-button>

        <p x-show="copyFailed" class="field-hint text-amber-700">
            Copying was refused — select the password above instead.
        </p>
    </div>

    {{-- Announced rather than shown twice, so the readout stays uncluttered. --}}
    <p class="sr-only" aria-live="polite" x-text="copied ? 'Password copied to clipboard.' : ''"></p>
</div>
