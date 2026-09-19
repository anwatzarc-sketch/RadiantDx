<x-layouts.admin title="Panels" :breadcrumbs="['Laboratory catalogue' => null, 'Panels' => null]">

    <x-page-header
        title="Panels"
        subtitle="Groups of tests that can be requested together. Requesting a panel expands it into its member tests."
    >
        <x-slot:actions>
            @can('create', App\Models\LaboratoryPanel::class)
                <x-button :href="route('laboratory.panels.create')" variant="primary" icon="plus">New panel</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('laboratory.panels.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Name, code or category" class="field-control mt-1" />
                </div>
                <div class="w-48">
                    <label for="category" class="field-label">Category</label>
                    <select name="category" id="category" class="field-control mt-1">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
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

        @if ($panels->isEmpty())
            <x-empty-state
                icon="layers"
                title="No panels yet"
                description="Group the tests that are commonly requested together so clinicians can order them in one step."
            >
                @can('create', App\Models\LaboratoryPanel::class)
                    <x-button :href="route('laboratory.panels.create')" variant="primary" size="sm" icon="plus">Add a panel</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Panel</th>
                            <th scope="col">Category</th>
                            <th scope="col">Tests</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($panels as $panel)
                            <tr>
                                <td>
                                    <a href="{{ route('laboratory.panels.show', $panel) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $panel->name }}</a>
                                    <span class="block font-mono text-xs text-slate-500">{{ $panel->code }}</span>
                                </td>
                                <td>{{ $panel->category ?: '—' }}</td>
                                <td>
                                    <span class="tabular-nums">{{ $panel->tests_count }}</span>
                                    @if ($panel->tests_count === 0)
                                        <span class="mt-0.5 block text-xs font-medium text-amber-700">Empty panel</span>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :classes="$panel->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                                             :icon="$panel->is_active ? 'check-circle' : 'ban'">
                                        {{ $panel->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $panel)
                                            <x-button :href="route('laboratory.panels.edit', $panel)" size="sm" icon="pencil">Edit</x-button>
                                        @endcan
                                        @can('activate', $panel)
                                            <form method="POST" action="{{ route('laboratory.panels.activate', $panel) }}">
                                                @csrf @method('PATCH')
                                                <x-button type="submit" size="sm" variant="ghost" icon="check">Activate</x-button>
                                            </form>
                                        @endcan
                                        @can('deactivate', $panel)
                                            <x-confirm-action
                                                :action="route('laboratory.panels.deactivate', $panel)"
                                                method="PATCH"
                                                variant="secondary"
                                                title="Deactivate this panel?"
                                                :message="$panel->name.' will be withdrawn from new requisitions. Existing requisitions are unaffected.'"
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

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $panels->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
