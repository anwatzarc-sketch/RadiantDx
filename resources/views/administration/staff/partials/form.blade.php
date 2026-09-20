@php
    use App\Enums\EmploymentType;
    use App\Enums\Gender;
    use App\Enums\StaffStatus;

    /** @var \App\Models\Staff|null $staff */
    $staff ??= null;

    // The form components apply old() themselves, so this only has to turn a
    // model attribute into the scalar an input expects.
    $value = function (string $field, $default = null) use ($staff) {
        $current = $staff?->{$field} ?? $default;

        return match (true) {
            $current instanceof BackedEnum => $current->value,
            $current instanceof DateTimeInterface => $current->format('Y-m-d'),
            default => $current,
        };
    };
@endphp

{{--
    Grouped rather than one long form: personal, professional, employment and
    contact are four different conversations, and an administrator filling one
    in from a personnel file works through them in that order.

    The staff identifier is shown read-only on edit and not shown at all on
    create, because it does not exist until the server issues it.
--}}

@if ($staff?->exists)
    <x-card title="Identifier">
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-mono text-sm font-semibold text-slate-900">{{ $staff->staff_id }}</span>
            <span class="text-xs text-slate-500">
                Issued by the system when the record was created. It cannot be changed.
            </span>
        </div>
    </x-card>
@endif

<x-card title="Personal">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="full_name" label="Full name" required>
            <x-form.input name="full_name" :value="$value('full_name')" required />
        </x-form.field>

        <x-form.field name="title" label="Title" hint="Dr, Prof, Mr, Ms — shown before the name on reports.">
            <x-form.input name="title" :value="$value('title')" />
        </x-form.field>

        <x-form.field name="gender" label="Gender">
            <x-form.select name="gender" :options="Gender::options()" :value="$value('gender')" placeholder="Not recorded" />
        </x-form.field>

        <x-form.field name="date_of_birth" label="Date of birth">
            <x-form.input type="date" name="date_of_birth" :value="$value('date_of_birth')" />
        </x-form.field>
    </div>
</x-card>

<x-card title="Professional">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="profession" label="Profession">
            <x-form.shared-enum-select enum="Profession" name="profession" :value="$value('profession')" placeholder="Select a profession" />
        </x-form.field>

        <x-form.field name="position" label="Position">
            <x-form.shared-enum-select enum="PositionType" name="position" :value="$value('position')" placeholder="Select a position" />
        </x-form.field>

        <x-form.field name="speciality" label="Speciality" hint="Searchable — try 'card' or 'heart'.">
            <x-form.shared-enum-select enum="Speciality" name="speciality" :value="$value('speciality')" placeholder="Select a speciality" />
        </x-form.field>

        <x-form.field name="sub_speciality" label="Sub-speciality" hint="Offered once a speciality is chosen.">
            <x-form.shared-enum-select
                enum="SubSpeciality"
                name="sub_speciality"
                :value="$value('sub_speciality')"
                :parent="$value('speciality')"
                parent-field="Speciality"
                placeholder="Select a sub-speciality"
            />
        </x-form.field>

        <x-form.field name="department_id" label="Department">
            <x-form.select
                name="department_id"
                :options="$departments->pluck('name', 'id')->all()"
                :value="$value('department_id')"
                placeholder="No department"
            />
        </x-form.field>

        <x-form.field name="professional_license" label="Licence number">
            <x-form.input name="professional_license" :value="$value('professional_license')" />
        </x-form.field>

        <x-form.field name="license_expiry" label="Licence expiry">
            <x-form.input type="date" name="license_expiry" :value="$value('license_expiry')" />
        </x-form.field>
    </div>
</x-card>

<x-card title="Employment">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="employee_id" label="Employee ID" hint="Your payroll or HR reference, if you use one.">
            <x-form.input name="employee_id" :value="$value('employee_id')" />
        </x-form.field>

        <x-form.field name="employment_type" label="Employment type">
            <x-form.select name="employment_type" :options="EmploymentType::options()" :value="$value('employment_type')" placeholder="Not recorded" />
        </x-form.field>

        <x-form.field name="status" label="Status" required
                      hint="Only Active staff may sign in and perform laboratory work.">
            <x-form.select name="status" :options="StaffStatus::options()" :value="$value('status', StaffStatus::Active->value)" required />
        </x-form.field>

        <x-form.field name="joined_on" label="Joined on">
            <x-form.input type="date" name="joined_on" :value="$value('joined_on')" />
        </x-form.field>

        <x-form.field name="supervisor_id" label="Supervisor">
            <x-form.select
                name="supervisor_id"
                :options="$supervisors->reject(fn ($s) => $staff && $s->id === $staff->id)->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' ('.$s->staff_id.')'])->all()"
                :value="$value('supervisor_id')"
                placeholder="No supervisor"
            />
        </x-form.field>
    </div>
</x-card>

<x-card title="Contact">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="phone" label="Phone">
            <x-form.input name="phone" :value="$value('phone')" />
        </x-form.field>

        <x-form.field name="email" label="Email" hint="The person's own address. Sign-in credentials are set separately.">
            <x-form.input type="email" name="email" :value="$value('email')" />
        </x-form.field>

        <div class="sm:col-span-2">
            <x-form.field name="address" label="Address">
                <x-form.input name="address" :value="$value('address')" />
            </x-form.field>
        </div>
    </div>
</x-card>
