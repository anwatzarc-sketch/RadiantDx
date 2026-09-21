@php
    use App\Enums\StaffStatus;
@endphp

<x-layouts.admin
    :title="$staff->full_name"
    :breadcrumbs="['Administration' => null, 'Staff' => route('administration.staff.index'), $staff->staff_code => null]"
    :back="route('administration.staff.index')"
    back-label="Back to staff"
>
    <x-page-header
        :title="$staff->displayName()"
        :subtitle="$staff->professionalSummary()"
    >
        {{-- The person's face, before their name and title. --}}
        <x-slot:leading>
            <x-staff-avatar :staff="$staff" size="lg" />
        </x-slot:leading>

        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $staff->staff_code }}</x-badge>

            @if ($staff->profession)
                <x-badge classes="bg-brand-100 text-brand-800 ring-brand-600/20" icon="role">
                    {{ $staff->profession->label() }}
                </x-badge>
            @endif

            <x-badge :classes="$staff->status->badgeClasses()">{{ $staff->status->label() }}</x-badge>

            @if ($staff->license_status && $staff->license_status !== \App\Enums\LicenseStatus::NotProvided)
                <x-badge :classes="$staff->license_status->badgeClasses()" icon="shield-check">
                    Licence {{ $staff->license_status->label() }}
                </x-badge>
            @endif

            @if ($staff->needs_review)
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="warning">Needs review</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $staff)
                <x-button :href="route('administration.staff.edit', $staff)" variant="secondary" icon="edit">Edit</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($staff->needs_review)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This record was created without full professional details — by the migration that gave every
            existing account a staff record, or by an import. Nothing has been invented on its behalf.
            Complete the professional section so reports show the right identity.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="space-y-6 xl:col-span-2">
            <x-card title="Professional">
                <x-detail-list :columns="2">
                    <x-detail label="Profession" :value="$staff->profession?->label()" />
                    <x-detail label="Position" :value="$staff->position?->label()" />
                    <x-detail label="Speciality" :value="$staff->speciality?->label()" />
                    <x-detail label="Sub-speciality" :value="$staff->sub_speciality?->label()" />
                    <x-detail label="Department" :value="$staff->department?->name" />
                    <x-detail label="Unit" :value="$staff->unit?->name" />
                    <x-detail label="Licence number" :value="$staff->professional_license" />
                    <x-detail label="Licence expiry" :value="$staff->license_expiry?->format('d M Y')" />
                </x-detail-list>
            </x-card>

            <x-card title="Employment">
                <x-detail-list :columns="2">
                    <x-detail label="Employee ID" :value="$staff->employee_id" />
                    <x-detail label="Employment type" :value="$staff->employment_type?->label()" />
                    <x-detail label="Status" :value="$staff->status->label()" />
                    <x-detail label="Joined" :value="$staff->joined_on?->format('d M Y')" />
                    <x-detail label="Supervisor" :value="$staff->supervisor?->full_name" />
                </x-detail-list>
            </x-card>

            <x-card title="Contact">
                <x-detail-list :columns="2">
                    <x-detail label="Phone" :value="$staff->phone" />
                    <x-detail label="Email" :value="$staff->email" />
                    <x-detail label="Address" :value="$staff->address" />
                    <x-detail label="Gender" :value="$staff->gender?->label()" />
                    <x-detail label="Date of birth" :value="$staff->date_of_birth?->format('d M Y')" />
                </x-detail-list>
            </x-card>

            @can('viewActivity', $staff)
                <x-card title="Activity" subtitle="Administrative and laboratory history for this person.">
                    @if ($activity->isEmpty())
                        <p class="py-6 text-center text-sm text-slate-500">Nothing recorded yet.</p>
                    @else
                        <x-activity-feed :entries="$activity" />
                    @endif
                </x-card>
            @endcan
        </div>

        <div class="space-y-6">
            <x-card title="Photo">
                @can('managePhoto', $staff)
                    <x-staff-photo-upload :staff="$staff" />
                @else
                    <div class="flex flex-col items-center gap-3">
                        <x-staff-avatar :staff="$staff" size="xl" />
                    </div>
                @endcan
            </x-card>

            <x-card title="System access">
                @if ($staff->user === null)
                    <p class="text-sm text-slate-600">
                        This person has no system account and cannot sign in.
                    </p>

                    @can('manageAccount', $staff)
                        @if ($staff->permitsSystemAccess())
                            {{--
                                No staff field anywhere in this form: which person the
                                account belongs to comes from the URL, so an administrator
                                is never asked to type or choose a staff identifier.
                            --}}
                            <form method="POST" action="{{ route('administration.staff.account.store', $staff) }}"
                                  class="mt-4 space-y-4">
                                @csrf

                                <x-form.field name="email" label="Sign-in email" required>
                                    <x-form.input type="email" name="email" :value="$staff->email" required />
                                </x-form.field>

                                <x-form.field name="role_id" label="Role">
                                    <x-form.select name="role_id" :options="$roles->pluck('name', 'id')->all()" placeholder="No role" />
                                </x-form.field>

                                <x-form.field name="password" label="Temporary password" required
                                              hint="They will be asked to choose a new one at first sign in.">
                                    <x-form.input type="password" name="password" required autocomplete="new-password" />
                                </x-form.field>

                                <x-form.field name="password_confirmation" label="Confirm password" required>
                                    <x-form.input type="password" name="password_confirmation" required autocomplete="new-password" />
                                </x-form.field>

                                <x-form.password-generator />

                                <x-form.checkbox name="is_active" label="Account active" :checked="true" />

                                <x-button type="submit" variant="primary" icon="plus" class="w-full">
                                    Create account
                                </x-button>
                            </form>
                        @else
                            <p class="mt-3 text-sm text-amber-700">
                                An account cannot be created while this person is {{ $staff->status->label() }}.
                            </p>
                        @endif
                    @endcan
                @else
                    <x-detail-list :columns="1">
                        <x-detail label="Sign-in email" :value="$staff->user->email" />
                        <x-detail label="Role" :value="$staff->user->roleName()" />
                        <x-detail label="Account" :value="$staff->user->is_active ? 'Active' : 'Disabled'" />
                        <x-detail label="Last sign in" :value="$staff->user->last_login_at?->format('d M Y H:i') ?? 'Never'" />
                    </x-detail-list>

                    @can('view', $staff->user)
                        <div class="mt-4">
                            <x-button :href="route('administration.users.show', $staff->user)" size="sm" variant="secondary">
                                Manage account
                            </x-button>
                        </div>
                    @endcan
                @endif
            </x-card>

            @can('changeStatus', $staff)
                <x-card title="Status">
                    <form method="POST" action="{{ route('administration.staff.status', $staff) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <x-form.field name="status" label="Staff status"
                                      hint="Moving away from Active also disables the system account.">
                            <x-form.select name="status" :options="StaffStatus::options()" :value="$staff->status->value" />
                        </x-form.field>

                        <x-button type="submit" variant="secondary" class="w-full">Update status</x-button>
                    </form>
                </x-card>
            @endcan

            @can('delete', $staff)
                <x-card title="Delete">
                    <p class="text-sm text-slate-600">
                        This record has no laboratory activity, so it can be removed. Once somebody has
                        acted on a laboratory record they are retired instead, which keeps historical
                        reports resolving.
                    </p>

                    <form method="POST" action="{{ route('administration.staff.destroy', $staff) }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash" class="w-full">Delete staff record</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.admin>
