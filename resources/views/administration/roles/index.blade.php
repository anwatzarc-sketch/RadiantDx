<x-layouts.admin title="Roles" :breadcrumbs="['Administration' => null, 'Roles' => null]">

    <x-page-header title="Roles" subtitle="A role is a named set of permissions. Every user holds exactly one.">
        <x-slot:actions>
            @can('create', App\Models\Role::class)
                <x-button :href="route('administration.roles.create')" variant="primary" icon="plus">New role</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('administration.roles.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Role name or slug" class="field-control mt-1" />
                </div>
                <div class="w-40">
                    <label for="status" class="field-label">Status</label>
                    <select name="status" id="status" class="field-control mt-1">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </x-filter-bar>
        </div>

        @if ($roles->isEmpty())
            <x-empty-state icon="role" title="No roles match" description="Adjust the filters or create a new role." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Users</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <a href="{{ route('administration.roles.show', $role) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $role->name }}</a>
                                    <span class="block font-mono text-xs text-slate-500">{{ $role->slug }}</span>
                                    @if ($role->description)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $role->description }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($role->isSuperAdmin())
                                        <x-badge classes="bg-brand-100 text-brand-800 ring-brand-600/20" icon="shield">
                                            All permissions
                                        </x-badge>
                                    @else
                                        <span class="tabular-nums">{{ $role->permissions_count }}</span>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $role->users_count }}</td>
                                <td>
                                    <x-badge :classes="$role->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                                             :icon="$role->is_active ? 'check-circle' : 'ban'">
                                        {{ $role->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $role)
                                            <x-button :href="route('administration.roles.edit', $role)" size="sm" icon="pencil">Edit</x-button>
                                        @endcan
                                        @can('delete', $role)
                                            <x-confirm-action
                                                :action="route('administration.roles.destroy', $role)"
                                                method="DELETE"
                                                title="Delete this role?"
                                                :message="'The role '.$role->name.' will be removed. This cannot be undone.'"
                                                confirm="Delete role"
                                                icon="trash"
                                            >Delete</x-confirm-action>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
                {{ $roles->links() }}
            </div>
        @endif
    </x-card>

</x-layouts.admin>
