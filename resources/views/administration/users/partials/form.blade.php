@props([
    'user' => null,
    'roles',
    'canChangeRole' => true,
])

{{-- Shared by create and edit. A blank password on edit keeps the existing one. --}}

@php($isNew = $user === null || ! $user->exists)

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form.field name="name" label="Full name" required>
        <x-form.input name="name" :value="$user?->name" required autocomplete="name" />
    </x-form.field>

    <x-form.field name="email" label="Email address" required hint="Used to sign in.">
        <x-form.input name="email" type="email" :value="$user?->email" required autocomplete="off" />
    </x-form.field>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form.field
        name="password"
        :label="$isNew ? 'Temporary password' : 'Reset password'"
        :required="$isNew"
        :hint="$isNew
            ? 'At least 10 characters with upper and lower case, a number and a symbol. The user must change it at first sign in.'
            : 'Leave blank to keep the current password.'"
    >
        <x-form.input name="password" type="password" autocomplete="new-password" :required="$isNew" />
    </x-form.field>

    <x-form.field name="password_confirmation" label="Confirm password" :required="$isNew">
        <x-form.input name="password_confirmation" type="password" autocomplete="new-password" :required="$isNew" />
    </x-form.field>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form.field name="role_id" label="Role" hint="Determines everything this account may see and do.">
        @if ($canChangeRole)
            <x-form.select
                name="role_id"
                :value="$user?->role_id"
                placeholder="No role (no access)"
                :options="$roles->pluck('name', 'id')->all()"
            />
        @else
            <div class="field-control bg-slate-50 text-slate-500">{{ $user?->roleName() }}</div>
            <p class="field-hint">
                This is the last active administrator. Assign another administrator before changing this role.
            </p>
        @endif
    </x-form.field>

    <div class="flex items-end pb-2">
        <x-form.checkbox
            name="is_active"
            label="Account is active"
            hint="An inactive account cannot sign in."
            :checked="$user?->is_active ?? true"
        />
    </div>
</div>
