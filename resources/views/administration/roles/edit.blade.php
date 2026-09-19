<x-layouts.admin :title="'Edit '.$role->name" :breadcrumbs="['Administration' => null, 'Roles' => route('administration.roles.index'), $role->name => route('administration.roles.show', $role), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$role->name"
        subtitle="Change the role details and what it may do."
        :back="route('administration.roles.show', $role)"
        back-label="Back to role"
    />

    @if ($role->isSuperAdmin())
        <div class="flex items-start gap-3 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-600/20 ring-inset">
            <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">This is the protected administrative role</p>
                <p class="mt-0.5">
                    It always holds every permission, including any added by a later release, so the system can
                    never be left without a usable administrative access path. Its permissions cannot be edited.
                </p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('administration.roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card title="Role" icon="role">
            <div class="space-y-4">
                @include('administration.roles.partials.form', ['role' => $role])
            </div>
        </x-card>

        <x-card
            title="Permissions"
            :subtitle="$role->isSuperAdmin() ? 'Fixed for the administrative role.' : 'Grouped by module, as declared in the permission catalogue.'"
            icon="shield"
        >
            @include('administration.roles.partials.permissions', [
                'permissionModules' => $permissionModules,
                'assigned' => $assigned,
                'readonly' => ! auth()->user()->can('assignPermissions', $role),
            ])
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button :href="route('administration.roles.show', $role)">Cancel</x-button>
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>

</x-layouts.admin>
