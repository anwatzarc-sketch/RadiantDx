@php
    use App\Enums\Profession;
    use App\Enums\Speciality;
    use App\Enums\StaffStatus;
@endphp

<x-layouts.admin title="Staff" :breadcrumbs="['Administration' => null, 'Staff' => null]">

    <x-page-header
        title="Staff Management"
        subtitle="Manage staff records, professional identities and system account associations."
    >
        <x-slot:actions>
            @can('export', App\Models\Staff::class)
                <x-button :href="route('administration.staff.export', request()->query())" variant="secondary" icon="arrow-down">
                    Export
                </x-button>
            @endcan
            @can('import', App\Models\Staff::class)
                <x-button :href="route('administration.staff.import.create')" variant="secondary" icon="arrow-up">
                    Import
                </x-button>
            @endcan
            @can('create', App\Models\Staff::class)
                <x-button :href="route('administration.staff.create')" variant="primary" icon="plus">Add staff</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('administration.staff.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Name, staff ID, employee ID, email or phone" class="field-control mt-1" />
                </div>

                <div class="w-44">
                    <label for="profession" class="field-label">Profession</label>
                    <select name="profession" id="profession" class="field-control mt-1">
                        <option value="">All professions</option>
                        @foreach (Profession::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['profession'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-48">
                    <label for="speciality" class="field-label">Speciality</label>
                    <select name="speciality" id="speciality" class="field-control mt-1">
                        <option value="">All specialities</option>
                        @foreach (Speciality::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['speciality'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <label for="department" class="field-label">Department</label>
                    <select name="department" id="department" class="field-control mt-1">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="status" class="field-label">Staff status</label>
                    <select name="status" id="status" class="field-control mt-1">
                        <option value="">Any status</option>
                        @foreach (StaffStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="account" class="field-label">Account</label>
                    <select name="account" id="account" class="field-control mt-1">
                        <option value="">Any</option>
                        <option value="active" @selected(($filters['account'] ?? '') === 'active')>Has active account</option>
                        <option value="disabled" @selected(($filters['account'] ?? '') === 'disabled')>Account disabled</option>
                        <option value="none" @selected(($filters['account'] ?? '') === 'none')>No account</option>
                    </select>
                </div>
            </x-filter-bar>
        </div>

        @if ($staff->isEmpty())
            <x-empty-state
                icon="users"
                title="No staff match"
                description="Adjust the filters, or add the first staff record for your laboratory."
            >
                @can('create', App\Models\Staff::class)
                    <x-button :href="route('administration.staff.create')" size="sm">Add staff</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Photo</span></th>
                            <th scope="col">Staff ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Title / Speciality</th>
                            <th scope="col">Profession</th>
                            <th scope="col">Department</th>
                            <th scope="col">Position</th>
                            <th scope="col">Account</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td>
                                    <x-staff-avatar :staff="$member" size="xs" />
                                </td>
                                <td>
                                    <a href="{{ route('administration.staff.show', $member) }}"
                                       class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                        {{ $member->staff_id }}
                                    </a>
                                </td>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $member->full_name }}</span>
                                    @if ($member->needs_review)
                                        <span class="mt-0.5 block text-xs text-amber-700">Needs review</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($member->title)
                                        <span class="block">{{ $member->title }}</span>
                                    @endif
                                    <span class="text-xs text-slate-500">{{ $member->speciality?->label() ?? '—' }}</span>
                                </td>
                                <td>{{ $member->profession?->label() ?? '—' }}</td>
                                <td>{{ $member->department?->name ?? '—' }}</td>
                                <td>{{ $member->position?->label() ?? '—' }}</td>
                                <td>
                                    @if ($member->user === null)
                                        <x-badge classes="bg-slate-100 text-slate-600 ring-slate-500/20">No account</x-badge>
                                    @else
                                        <x-badge :classes="$member->user->is_active
                                            ? 'bg-emerald-100 text-emerald-800 ring-emerald-600/20'
                                            : 'bg-slate-100 text-slate-600 ring-slate-500/20'">
                                            {{ $member->user->is_active ? 'Active' : 'Disabled' }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :classes="$member->status->badgeClasses()">{{ $member->status->label() }}</x-badge>
                                </td>
                                <td class="text-right">
                                    <x-button :href="route('administration.staff.show', $member)" size="sm">View</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $staff->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
