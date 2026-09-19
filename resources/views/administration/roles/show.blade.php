<x-layouts.admin :title="$role->name" :breadcrumbs="['Administration' => null, 'Roles' => route('administration.roles.index'), $role->name => null]">

    <x-page-header
        :title="$role->name"
        :subtitle="$role->description"
        :back="route('administration.roles.index')"
        back-label="Back to roles"
    >
        <x-slot:meta>
            <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">{{ $role->slug }}</x-badge>
            <x-badge :classes="$role->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                     :icon="$role->is_active ? 'check-circle' : 'ban'">
                {{ $role->is_active ? 'Active' : 'Inactive' }}
            </x-badge>
            @if ($role->isSuperAdmin())
                <x-badge classes="bg-brand-100 text-brand-800 ring-brand-600/20" icon="shield">Protected administrative role</x-badge>
            @endif
        </x-slot:meta>

        <x-slot:actions>
            @can('update', $role)
                <x-button :href="route('administration.roles.edit', $role)" icon="pencil">Edit</x-button>
            @endcan
            @can('delete', $role)
                <x-confirm-action
                    :action="route('administration.roles.destroy', $role)"
                    method="DELETE"
                    size="md"
                    title="Delete this role?"
                    :message="'The role '.$role->name.' will be removed. This cannot be undone.'"
                    confirm="Delete role"
                    icon="trash"
                >Delete</x-confirm-action>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Role" icon="role">
        <x-detail-list :columns="4">
            <x-detail label="Name" :value="$role->name" />
            <x-detail label="Slug" :value="$role->slug" mono />
            <x-detail label="Users holding this role" :value="(string) $role->users_count" />
            <x-detail label="Permissions granted" :value="$role->isSuperAdmin() ? 'All' : (string) count($assigned)" />
        </x-detail-list>
    </x-card>

    <x-card title="Permissions" subtitle="What this role authorises, grouped by module." icon="shield">
        @if ($role->isSuperAdmin())
            <p class="mb-4 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-900 ring-1 ring-sky-600/20 ring-inset">
                The administrative role implicitly holds every permission, including ones introduced by a later
                release. This keeps the installation from ever reaching a state where nobody can administer it.
            </p>
        @endif

        @include('administration.roles.partials.permissions', [
            'permissionModules' => $permissionModules,
            'assigned' => $assigned,
            'readonly' => true,
        ])
    </x-card>

</x-layouts.admin>
