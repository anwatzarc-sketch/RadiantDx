@php
    use App\Enums\LicenseStatus;
    use App\Enums\PhysicianPracticeStatus;
    use App\Enums\Speciality;
@endphp

<x-layouts.admin title="Physicians" :breadcrumbs="['Administration' => null, 'Physicians' => null]">

    <x-page-header
        title="Physicians"
        subtitle="Staff who practise under a professional licence. Identity comes from the staff record."
    />

    <x-card :padded="false">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
            <x-filter-bar :action="route('administration.physicians.index')" :active="collect($filters)->filter()->isNotEmpty()">
                <div class="min-w-56 flex-1">
                    <label for="search" class="field-label">Search</label>
                    <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Name, staff ID, licence or registration number" class="field-control mt-1" />
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

                <div class="w-44">
                    <label for="license_status" class="field-label">Licence</label>
                    <select name="license_status" id="license_status" class="field-control mt-1">
                        <option value="">Any licence status</option>
                        @foreach (LicenseStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['license_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <label for="practice_status" class="field-label">Practice</label>
                    <select name="practice_status" id="practice_status" class="field-control mt-1">
                        <option value="">Any practice status</option>
                        @foreach (PhysicianPracticeStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['practice_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </x-filter-bar>
        </div>

        @if ($physicians->isEmpty())
            <x-empty-state
                icon="users"
                title="No physicians match"
                description="Physicians are staff whose profession requires a licence. Adjust the filters, or set a profession on a staff record."
            />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Photo</span></th>
                            <th scope="col">Staff ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Speciality</th>
                            <th scope="col">Department</th>
                            <th scope="col">Licence</th>
                            <th scope="col">Practice</th>
                            <th scope="col">Profile</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($physicians as $physician)
                            @php($completion = $physician->profileCompletion())
                            <tr>
                                <td><x-staff-avatar :staff="$physician" size="xs" /></td>
                                <td>
                                    <a href="{{ route('administration.staff.show', $physician) }}"
                                       class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                        {{ $physician->staff_id }}
                                    </a>
                                </td>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $physician->displayName() }}</span>
                                </td>
                                <td>
                                    <span class="block">{{ $physician->speciality?->label() ?? '—' }}</span>
                                    @if ($physician->sub_speciality)
                                        <span class="text-xs text-slate-500">{{ $physician->sub_speciality->label() }}</span>
                                    @endif
                                </td>
                                <td>{{ $physician->department?->name ?? '—' }}</td>
                                <td>
                                    <x-badge :classes="$physician->license_status?->badgeClasses() ?? 'bg-slate-100 text-slate-600 ring-slate-500/20'">
                                        {{ $physician->license_status?->label() ?? 'Not provided' }}
                                    </x-badge>
                                    @if ($physician->license_expiry)
                                        <span class="mt-0.5 block text-xs text-slate-500">
                                            to {{ $physician->license_expiry->format('d M Y') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($physician->practice_status)
                                        <x-badge :classes="$physician->practice_status->badgeClasses()">
                                            {{ $physician->practice_status->label() }}
                                        </x-badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span @class([
                                        'text-xs font-semibold',
                                        'text-emerald-700' => $completion >= 80,
                                        'text-amber-700' => $completion >= 50 && $completion < 80,
                                        'text-rose-700' => $completion < 50,
                                    ])>{{ $completion }}%</span>
                                </td>
                                <td class="text-right">
                                    @can('managePhysicianProfile', $physician)
                                        <x-button :href="route('administration.physicians.edit', $physician)" size="sm">
                                            Licensing
                                        </x-button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $physicians->links() }}</div>
        @endif
    </x-card>

</x-layouts.admin>
