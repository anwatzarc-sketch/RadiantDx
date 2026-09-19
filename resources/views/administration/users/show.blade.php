<x-layouts.admin :title="$user->name" :breadcrumbs="['Administration' => null, 'Users' => route('administration.users.index'), $user->name => null]">

    <x-page-header
        :title="$user->name"
        :subtitle="$user->email"
        :back="route('administration.users.index')"
        back-label="Back to users"
    >
        <x-slot:meta>
            <x-badge :classes="$user->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                     :icon="$user->is_active ? 'check-circle' : 'ban'">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </x-badge>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20" icon="role">{{ $user->roleName() }}</x-badge>
            @if ($user->must_change_password)
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30" icon="lock">Password change pending</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $user)
                <x-button :href="route('administration.users.edit', $user)" icon="pencil">Edit</x-button>
            @endcan

            @can('activate', $user)
                <form method="POST" action="{{ route('administration.users.activate', $user) }}">
                    @csrf @method('PATCH')
                    <x-button type="submit" variant="success" icon="check">Activate</x-button>
                </form>
            @endcan

            @can('deactivate', $user)
                <x-confirm-action
                    :action="route('administration.users.deactivate', $user)"
                    method="PATCH"
                    size="md"
                    title="Deactivate this account?"
                    :message="$user->name.' will no longer be able to sign in. Their laboratory records stay untouched.'"
                    confirm="Deactivate"
                    icon="ban"
                >Deactivate</x-confirm-action>
            @endcan

            @can('delete', $user)
                <x-confirm-action
                    :action="route('administration.users.destroy', $user)"
                    method="DELETE"
                    size="md"
                    title="Delete this account?"
                    :message="$user->name.' will be removed. Audit history keeps their name so past actions stay traceable.'"
                    confirm="Delete user"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Account" icon="profile">
                <x-detail-list :columns="2">
                    <x-detail label="Full name" :value="$user->name" />
                    <x-detail label="Email address" :value="$user->email" />
                    <x-detail label="Role" :value="$user->roleName()" />
                    <x-detail label="Status" :value="$user->is_active ? 'Active' : 'Inactive'" />
                    <x-detail label="Last sign in" :value="$user->last_login_at?->format('d M Y H:i') ?? 'Never'" />
                    <x-detail label="Last sign in address" :value="$user->last_login_ip" mono />
                    <x-detail label="Created" :value="$user->created_at?->format('d M Y H:i')" />
                    <x-detail label="Updated" :value="$user->updated_at?->format('d M Y H:i')" />
                </x-detail-list>
            </x-card>

            <x-card title="Effective permissions" subtitle="Everything this account is authorised to do" icon="shield">
                @if ($user->role === null)
                    <p class="text-sm text-slate-600">No role is assigned, so this account has no access.</p>
                @elseif ($user->isSuperAdmin())
                    <p class="text-sm text-slate-600">
                        This account holds the administrative role and therefore every permission in the catalogue.
                    </p>
                @elseif ($user->role->permissions->isEmpty())
                    <p class="text-sm text-slate-600">The assigned role grants no permissions yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($user->role->permissions->groupBy('module') as $module => $permissions)
                            <div>
                                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $module }}</p>
                                <ul class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($permissions as $permission)
                                        <li class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                            {{ $permission->label }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <x-card title="Activity" subtitle="Recent actions by this account" icon="clock">
            <x-activity-feed :entries="$activity" empty="This account has not done anything yet." />
        </x-card>
    </div>

</x-layouts.admin>
