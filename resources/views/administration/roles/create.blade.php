<x-layouts.admin title="New role" :breadcrumbs="['Administration' => null, 'Roles' => route('administration.roles.index'), 'New role' => null]">

    <x-page-header
        title="New role"
        subtitle="Name the role, then choose exactly what it may do."
        :back="route('administration.roles.index')"
        back-label="Back to roles"
    />

    <form method="POST" action="{{ route('administration.roles.store') }}" class="space-y-6">
        @csrf

        <x-card title="Role" icon="role">
            <div class="space-y-4">
                @include('administration.roles.partials.form', ['role' => null])
            </div>
        </x-card>

        <x-card title="Permissions" subtitle="Grouped by module, as declared in the permission catalogue." icon="shield">
            @include('administration.roles.partials.permissions', [
                'permissionModules' => $permissionModules,
                'assigned' => $assigned,
                'readonly' => false,
            ])
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button :href="route('administration.roles.index')">Cancel</x-button>
            <x-button type="submit" variant="primary">Create role</x-button>
        </div>
    </form>

</x-layouts.admin>
