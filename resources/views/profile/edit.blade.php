<x-layouts.admin title="Profile" :breadcrumbs="['Profile' => null]">

    <x-page-header title="Profile and security" subtitle="Your account details, role and recent activity." />

    @if ($user->must_change_password)
        <div class="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/30 ring-inset">
            <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">A new password is required</p>
                <p class="mt-0.5">
                    This account is still using the password issued at setup. Choose a new one below to continue
                    to the rest of the application.
                </p>
            </div>
        </div>
    @endif

    @if ($user->staff)
        {{--
            Your professional identity, shown as the laboratory sees it. This is
            what appears on a report when you validate a result, so it is worth
            being able to check it without an administrator.

            Everything here is read-only: speciality, profession, department and
            status are administrative, and the form below carries only the
            fields you are the authority on about yourself.
        --}}
        <x-card title="Professional identity" subtitle="How you are identified on laboratory reports.">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="flex flex-col items-center gap-3">
                    <x-staff-avatar :staff="$user->staff" size="lg" />

                    @can('managePhoto', $user->staff)
                        <form method="POST" action="{{ route('administration.staff.photo.store', $user->staff) }}"
                              enctype="multipart/form-data" class="w-full space-y-2">
                            @csrf
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                                   required class="field-control text-xs" />
                            <x-button type="submit" size="sm" variant="secondary" class="w-full">
                                Update photo
                            </x-button>
                        </form>
                    @endcan
                </div>

                <div class="min-w-0 flex-1">
                    <x-detail-list :columns="2">
                        <x-detail label="Staff ID" :value="$user->staff->staff_id" />
                        <x-detail label="Name" :value="$user->staff->displayName()" />
                        <x-detail label="Profession" :value="$user->staff->profession?->label()" />
                        <x-detail label="Speciality" :value="$user->staff->speciality?->label()" />
                        <x-detail label="Sub-speciality" :value="$user->staff->sub_speciality?->label()" />
                        <x-detail label="Department" :value="$user->staff->department?->name" />
                        <x-detail label="Position" :value="$user->staff->position?->label()" />
                        <x-detail label="Role" :value="$user->roleName()" />
                    </x-detail-list>

                    <p class="mt-4 text-xs text-slate-500">
                        These details are maintained by an administrator. If something is wrong,
                        ask for it to be corrected rather than working around it — this is the
                        identity printed on results you validate.
                    </p>
                </div>
            </div>
        </x-card>

        <x-card title="Your contact details" subtitle="The only part of your staff record you can change yourself.">
            <form method="POST" action="{{ route('profile.staff.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.field name="phone" label="Phone">
                        <x-form.input name="phone" :value="$user->staff->phone" />
                    </x-form.field>

                    <x-form.field name="professional_phone" label="Professional phone">
                        <x-form.input name="professional_phone" :value="$user->staff->professional_phone" />
                    </x-form.field>

                    <x-form.field name="professional_email" label="Professional email">
                        <x-form.input type="email" name="professional_email" :value="$user->staff->professional_email" />
                    </x-form.field>

                    <x-form.field name="address" label="Address">
                        <x-form.input name="address" :value="$user->staff->address" />
                    </x-form.field>

                    <div class="sm:col-span-2">
                        <x-form.field name="professional_bio" label="Professional biography">
                            <x-form.textarea name="professional_bio" rows="3">{{ old('professional_bio', $user->staff->professional_bio) }}</x-form.textarea>
                        </x-form.field>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" variant="primary" icon="check">Save my details</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">
            <x-card title="Account details" icon="profile">
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-form.field name="name" label="Full name" required>
                            <x-form.input name="name" :value="$user->name" required autocomplete="name" />
                        </x-form.field>

                        <x-form.field name="email" label="Email address" required>
                            <x-form.input name="email" type="email" :value="$user->email" required autocomplete="email" />
                        </x-form.field>
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary">Save details</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Change password" subtitle="At least 10 characters, with upper and lower case, a number and a symbol." icon="lock">
                <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-form.field name="current_password" label="Current password" required>
                        <x-form.input name="current_password" type="password" required autocomplete="current-password" />
                    </x-form.field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-form.field name="password" label="New password" required>
                            <x-form.input name="password" type="password" required autocomplete="new-password" />
                        </x-form.field>

                        <x-form.field name="password_confirmation" label="Confirm new password" required>
                            <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
                        </x-form.field>
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary">Change password</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Access" subtitle="Role and permissions in effect" icon="shield">
                <x-detail-list :columns="1">
                    <x-detail label="Role" :value="$user->roleName()" />
                    <x-detail label="Account status">
                        <x-badge :classes="$user->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-rose-100 text-rose-800 ring-rose-600/20'"
                                 :icon="$user->is_active ? 'check-circle' : 'ban'">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </x-badge>
                    </x-detail>
                    <x-detail label="Last sign in" :value="$user->last_login_at?->format('d M Y H:i') ?? 'This is your first session'" />
                </x-detail-list>

                <div class="mt-3 border-t border-slate-100 pt-3">
                    <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
                        Permissions ({{ count($user->permissionNames()) }})
                    </p>
                    @if ($user->isSuperAdmin())
                        <p class="mt-1.5 text-sm text-slate-600">
                            This role holds every permission in the catalogue.
                        </p>
                    @elseif ($user->permissionNames() === [])
                        <p class="mt-1.5 text-sm text-slate-600">No permissions are granted to this account.</p>
                    @else
                        <ul class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($user->permissionNames() as $permission)
                                <li class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[0.7rem] text-slate-600">
                                    {{ $permission }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-card>

            <x-card title="Your recent activity" icon="clock">
                <x-activity-feed :entries="$activity" empty="Nothing recorded for this account yet." />
            </x-card>
        </div>
    </div>

</x-layouts.admin>
