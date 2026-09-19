<x-layouts.admin title="Laboratory tests" :breadcrumbs="['Laboratory catalogue' => null, 'Laboratory tests' => null]">

    <x-page-header
        title="Laboratory tests"
        subtitle="Everything the laboratory can perform. Tests are the building blocks of panels and requisitions."
    >
        <x-slot:actions>
            @can('create', App\Models\LaboratoryTest::class)
                <x-button :href="route('laboratory.tests.create')" variant="primary" icon="plus">New test</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('laboratory.tests.index')" :active="collect($filters)->filter()->isNotEmpty()">
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
                <div class="w-48">
                    <label for="result_type" class="field-label">Result shape</label>
                    <select name="result_type" id="result_type" class="field-control mt-1">
                        <option value="">Any shape</option>
                        @foreach (App\Enums\TestResultType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['result_type'] ?? '') === $value)>{{ $label }}</option>
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

        @if ($tests->isEmpty())
            <x-empty-state
                icon="catalogue"
                title="No laboratory tests yet"
                description="The catalogue defines what the laboratory can perform. Add the first test to begin."
            >
                @can('create', App\Models\LaboratoryTest::class)
                    <x-button :href="route('laboratory.tests.create')" variant="primary" size="sm" icon="plus">Add a test</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Test</th>
                            <th scope="col">Category</th>
                            <th scope="col">Specimen</th>
                            <th scope="col">Reports</th>
                            <th scope="col">Panels</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tests as $test)
                            <tr>
                                <td>
                                    <a href="{{ route('laboratory.tests.show', $test) }}"
                                       class="font-medium text-brand-700 hover:underline">{{ $test->name }}</a>
                                    <span class="block font-mono text-xs text-slate-500">{{ $test->code }}</span>
                                </td>
                                <td>{{ $test->category ?: '—' }}</td>
                                <td>{{ $test->specimen_type ?: '—' }}</td>
                                <td>
                                    @if ($test->isParameterised())
                                        <span class="text-sm">{{ $test->parameters_count }} parameters</span>
                                        @if ($test->parameters_count === 0)
                                            <span class="mt-0.5 block text-xs font-medium text-amber-700">Needs a parameter</span>
                                        @endif
                                    @else
                                        <span class="text-sm">Single value</span>
                                        @if ($test->unit)
                                            <span class="block text-xs text-slate-500">{{ $test->unit }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $test->panels_count }}</td>
                                <td>
                                    <x-badge :classes="$test->is_active ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20'"
                                             :icon="$test->is_active ? 'check-circle' : 'ban'">
                                        {{ $test->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $test)
                                            <x-button :href="route('laboratory.tests.edit', $test)" size="sm" icon="pencil">Edit</x-button>
                                        @endcan
                                        @can('activate', $test)
                                            <form method="POST" action="{{ route('laboratory.tests.activate', $test) }}">
                                                @csrf @method('PATCH')
                                                <x-button type="submit" size="sm" variant="ghost" icon="check">Activate</x-button>
                                            </form>
                                        @endcan
                                        @can('deactivate', $test)
                                            <x-confirm-action
                                                :action="route('laboratory.tests.deactivate', $test)"
                                                method="PATCH"
                                                title="Deactivate this test?"
                                                :message="$test->name.' will be withdrawn from new requisitions. Existing requisitions and results are unaffected.'"
                                                confirm="Deactivate"
                                                variant="secondary"
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

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $tests->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
