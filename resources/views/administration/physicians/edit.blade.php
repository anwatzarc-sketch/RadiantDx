@php
    use App\Enums\LicenseStatus;
    use App\Enums\PhysicianPracticeStatus;
    use App\Enums\QualificationType;
    use App\Enums\RegistrationStatus;

    $value = function (string $field, $default = null) use ($staff) {
        $current = $staff->{$field} ?? $default;

        return match (true) {
            $current instanceof BackedEnum => $current->value,
            $current instanceof DateTimeInterface => $current->format('Y-m-d'),
            default => $current,
        };
    };
@endphp

<x-layouts.admin
    :title="'Licensing — '.$staff->full_name"
    :breadcrumbs="['Administration' => null, 'Physicians' => route('administration.physicians.index'), $staff->staff_code => null]"
    :back="route('administration.staff.show', $staff)"
    back-label="Back to profile"
>
    <x-page-header
        :title="'Licensing & practice'"
        :subtitle="$staff->displayName().' · '.$staff->staff_code"
    />

    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        Name, title, speciality and department belong to the staff record and are edited there.
        This screen covers only what is specific to practising under a licence.
    </div>

    <form method="POST" action="{{ route('administration.physicians.update', $staff) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card title="Licence">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.field name="professional_license" label="Licence number">
                    <x-form.input name="professional_license" :value="$value('professional_license')" />
                </x-form.field>

                <x-form.field name="license_authority" label="Issuing authority">
                    <x-form.input name="license_authority" :value="$value('license_authority')" />
                </x-form.field>

                <x-form.field name="license_issued_on" label="Issued on">
                    <x-form.input type="date" name="license_issued_on" :value="$value('license_issued_on')" />
                </x-form.field>

                <x-form.field name="license_expiry" label="Expires on">
                    <x-form.input type="date" name="license_expiry" :value="$value('license_expiry')" />
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field
                        name="license_status"
                        label="Licence status"
                        required
                        hint="Active, Expiring Soon and Expired are recalculated from the dates. Suspended and Revoked are set by you and are never overwritten automatically."
                    >
                        <x-form.select name="license_status" :options="LicenseStatus::options()"
                                       :value="$value('license_status', LicenseStatus::NotProvided->value)" required />
                    </x-form.field>
                </div>
            </div>
        </x-card>

        <x-card title="Registration">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.field name="registration_number" label="Registration number">
                    <x-form.input name="registration_number" :value="$value('registration_number')" />
                </x-form.field>

                <x-form.field name="registration_authority" label="Registering body">
                    <x-form.input name="registration_authority" :value="$value('registration_authority')" />
                </x-form.field>

                <x-form.field name="registration_status" label="Registration status" required>
                    <x-form.select name="registration_status" :options="RegistrationStatus::options()"
                                   :value="$value('registration_status', RegistrationStatus::NotRegistered->value)" required />
                </x-form.field>
            </div>
        </x-card>

        <x-card title="Practice">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.field name="practice_status" label="Practice status">
                    <x-form.select name="practice_status" :options="PhysicianPracticeStatus::options()"
                                   :value="$value('practice_status')" placeholder="Not recorded" />
                </x-form.field>

                <x-form.field name="professional_phone" label="Professional phone">
                    <x-form.input name="professional_phone" :value="$value('professional_phone')" />
                </x-form.field>

                <x-form.field name="professional_email" label="Professional email">
                    <x-form.input type="email" name="professional_email" :value="$value('professional_email')" />
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field name="professional_bio" label="Professional biography">
                        <x-form.textarea name="professional_bio" rows="4">{{ old('professional_bio', $staff->professional_bio) }}</x-form.textarea>
                    </x-form.field>
                </div>
            </div>
        </x-card>

        <div class="flex items-center justify-end gap-3">
            <x-button :href="route('administration.staff.show', $staff)" variant="secondary">Cancel</x-button>
            <x-button type="submit" variant="primary" icon="check">Save licensing details</x-button>
        </div>
    </form>

    @can('manageQualifications', $staff)
        <x-card title="Qualifications" subtitle="Degrees, certifications and fellowships held.">
            @if ($staff->qualifications->isEmpty())
                <p class="py-4 text-sm text-slate-500">No qualifications recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Institution</th>
                                <th scope="col">Field</th>
                                <th scope="col">Year</th>
                                <th scope="col">Reference</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staff->qualifications as $qualification)
                                <tr>
                                    <td>{{ $qualification->type->label() }}</td>
                                    <td>{{ $qualification->institution }}</td>
                                    <td>{{ $qualification->field ?? '—' }}</td>
                                    <td>{{ $qualification->awarded_year ?? '—' }}</td>
                                    <td class="font-mono text-xs">{{ $qualification->reference ?? '—' }}</td>
                                    <td class="text-right">
                                        <form method="POST"
                                              action="{{ route('administration.physicians.qualifications.destroy', [$staff, $qualification]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="ghost" size="sm" icon="trash">Remove</x-button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('administration.physicians.qualifications.store', $staff) }}"
                  class="mt-6 grid grid-cols-1 gap-4 border-t border-slate-200 pt-6 sm:grid-cols-2">
                @csrf

                <x-form.field name="type" label="Type" required>
                    <x-form.select name="type" :options="QualificationType::options()" required />
                </x-form.field>

                <x-form.field name="institution" label="Institution" required>
                    <x-form.input name="institution" required />
                </x-form.field>

                <x-form.field name="field" label="Field">
                    <x-form.input name="field" />
                </x-form.field>

                <x-form.field name="awarded_year" label="Year awarded">
                    <x-form.input type="number" name="awarded_year" min="1900" max="{{ date('Y') }}" />
                </x-form.field>

                <x-form.field name="reference" label="Certificate reference">
                    <x-form.input name="reference" />
                </x-form.field>

                <div class="flex items-end">
                    <x-button type="submit" variant="secondary" icon="plus">Add qualification</x-button>
                </div>
            </form>
        </x-card>
    @endcan
</x-layouts.admin>
