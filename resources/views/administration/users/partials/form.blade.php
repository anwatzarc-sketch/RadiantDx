@props([
    'user' => null,
    'roles',
    'staff' => null,
    'canChangeRole' => true,
])

{{-- Shared by create and edit. A blank password on edit keeps the existing one. --}}

{{--
    Kept as one raw PHP block. Blade pulls those blocks out with a non-greedy
    match before anything else runs, so the single-expression form of the same
    directive, placed above a block, is swallowed into it and its assignment is
    silently lost. That extraction also ignores comments, which is why this
    note spells out none of the directives involved.
--}}
@php
    $isNew = $user === null || ! $user->exists;

    $eligibleStaff = collect($staff ?? []);

    $staffOptions = $eligibleStaff
        ->mapWithKeys(fn ($member) => [
            $member->id => $member->staff_code.' — '.$member->full_name
                .($member->position ? ' ('.$member->position.')' : ''),
        ])
        ->all();

    // Drives the read-only name mirror below.
    $staffNames = $eligibleStaff->mapWithKeys(fn ($member) => [$member->id => $member->full_name])->all();
@endphp

@if ($isNew)
    {{--
        Every account hangs off a staff record: users.staff_id is NOT NULL, so
        the choice made here is what makes the row writable. Only Active staff
        with no account appear, and the same two rules are enforced again in
        UserRequest and UserService -- this list narrows the choice, it does
        not secure it.
    --}}
    @if ($eligibleStaff->isEmpty())
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">No staff member is waiting for an account.</p>
            <p class="mt-1">
                An account can only be created for a staff record whose standing is Active and
                that has no account yet. Add a staff record first, then come back.
            </p>
            @can('create', App\Models\Staff::class)
                <a href="{{ route('administration.staff.create') }}" class="mt-2 inline-flex font-semibold underline">
                    Add a staff record
                </a>
            @endcan
        </div>
    @else
        <div x-data="{ staffId: @js((string) old('staff_id', '')), names: @js($staffNames) }" class="space-y-4">
            <x-form.field
                name="staff_id"
                label="Staff member"
                required
                hint="Only Active staff without an account are listed. This cannot be changed later."
            >
                <x-form.select
                    name="staff_id"
                    :options="$staffOptions"
                    placeholder="Select a staff member..."
                    x-model="staffId"
                    required
                />
            </x-form.field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.field name="name" label="Full name" hint="Taken from the staff record, so the two cannot disagree.">
                    <x-form.input
                        name="name"
                        readonly
                        x-bind:value="names[staffId] ?? ''"
                        class="bg-slate-50 text-slate-500"
                    />
                </x-form.field>

                <x-form.field name="email" label="Email address" required hint="Used to sign in.">
                    <x-form.input name="email" type="email" :value="$user?->email" required autocomplete="off" />
                </x-form.field>
            </div>
        </div>
    @endif
@else
    <x-form.field name="staff_display" label="Staff member" hint="An account cannot be moved to a different staff record.">
        <div class="field-control bg-slate-50 text-slate-500">
            {{ $user->staff?->staff_code }} — {{ $user->staff?->full_name ?? 'Not linked' }}
        </div>
    </x-form.field>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="name" label="Full name" required>
            <x-form.input name="name" :value="$user?->name" required autocomplete="name" />
        </x-form.field>

        <x-form.field name="email" label="Email address" required hint="Used to sign in.">
            <x-form.input name="email" type="email" :value="$user?->email" required autocomplete="off" />
        </x-form.field>
    </div>
@endif

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

<x-form.password-generator />

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
