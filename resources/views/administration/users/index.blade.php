<x-layouts.admin title="Users" :breadcrumbs="['Administration' => null, 'Users' => null]">

    <x-page-header title="Users" subtitle="Accounts that can sign in to the laboratory system.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <x-button :href="route('administration.users.create')" variant="primary" icon="plus">New user</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('administration.users.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Name or email" class="field-control mt-1" />
                </div>
                <div class="w-44">
                    <label for="role" class="field-label">Role</label>
                    <select name="role" id="role" class="field-control mt-1">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) ($filters['role'] ?? '') === (string) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
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

        @if ($users->isEmpty())
            <x-empty-state
                icon="users"
                title="No users match"
                description="Adjust the filters, or create the first account for your laboratory staff."
            />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last sign in</th>
                            <th scope="col">Created</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <a href="{{ route('administration.users.show', $user) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $user->name }}</a>
                                    <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                                </td>
                                <td>{{ $user->roleName() }}</td>
                                <td>
                                    <x-badge :classes="$user->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                                             :icon="$user->is_active ? 'check-circle' : 'ban'">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                <td class="text-xs text-slate-500">
                                    {{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}
                                </td>
                                <td class="text-xs text-slate-500">{{ $user->created_at?->format('d M Y') }}</td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $user)
                                            <x-button :href="route('administration.users.edit', $user)" size="sm" icon="pencil">Edit</x-button>
                                        @endcan
                                        @can('activate', $user)
                                            <form method="POST" action="{{ route('administration.users.activate', $user) }}">
                                                @csrf @method('PATCH')
                                                <x-button type="submit" size="sm" variant="ghost" icon="check">Activate</x-button>
                                            </form>
                                        @endcan
                                        @can('deactivate', $user)
                                            <x-confirm-action
                                                :action="route('administration.users.deactivate', $user)"
                                                method="PATCH"
                                                title="Deactivate this account?"
                                                :message="$user->name.' will no longer be able to sign in. Existing records stay untouched.'"
                                                confirm="Deactivate"
                                                icon="ban"
                                            >Deactivate</x-confirm-action>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">
                {{ $users->links() }}
            </div>
        @endif
    </x-card>

</x-layouts.admin>
