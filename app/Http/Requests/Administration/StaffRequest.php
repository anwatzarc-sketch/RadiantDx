<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Models\Staff;
use App\Rules\SharedEnumValue;
use App\Rules\ValidSubSpeciality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Serves both creation and editing of a staff record.
 *
 * `staff_id` is absent from the rules on purpose. It is issued by the server
 * and any value sent for it is discarded by prepareForValidation() before
 * validation runs, so a crafted payload cannot choose or change an identifier.
 */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffKey = $staff instanceof Staff ? $staff->getKey() : null;

        return [
            // --- Identity ---
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', new SharedEnumValue('Gender')],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],

            // --- Contact ---
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('staff', 'email')->ignore($staffKey)->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string', 'max:255'],

            // --- Professional ---
            'title' => ['nullable', 'string', 'max:64'],
            'profession' => ['nullable', new SharedEnumValue('Profession')],
            'speciality' => ['nullable', new SharedEnumValue('Speciality')],
            'sub_speciality' => [
                'nullable',
                new ValidSubSpeciality($this->input('speciality')),
            ],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'unit_id' => [
                'nullable', 'integer',
                // A unit must belong to the department that was chosen with it.
                Rule::exists('units', 'id')
                    ->where('department_id', $this->input('department_id'))
                    ->whereNull('deleted_at'),
            ],
            'position' => ['nullable', new SharedEnumValue('PositionType')],
            'professional_license' => ['nullable', 'string', 'max:64'],
            'license_expiry' => ['nullable', 'date', 'after:1950-01-01'],

            // --- Employment ---
            'employee_id' => [
                'nullable', 'string', 'max:64',
                Rule::unique('staff', 'employee_id')->ignore($staffKey)->whereNull('deleted_at'),
            ],
            'employment_type' => ['nullable', new SharedEnumValue('EmploymentType')],
            'status' => ['required', new SharedEnumValue('StaffStatus')],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'supervisor_id' => [
                'nullable', 'integer',
                // Nobody supervises themselves.
                Rule::exists('staff', 'id')->whereNull('deleted_at')->when(
                    $staffKey !== null,
                    fn ($rule) => $rule->whereNot('id', $staffKey),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'Select a unit that belongs to the chosen department.',
            'supervisor_id.exists' => 'Select an existing staff member as supervisor.',
            'email.unique' => 'Another staff record already uses this email address.',
            'employee_id.unique' => 'Another staff record already uses this employee ID.',
        ];
    }

    protected function prepareForValidation(): void
    {
        /*
         * The identifier is the server's to issue. Dropping it here rather than
         * merely omitting it from rules() means a payload carrying it cannot
         * reach the service even if a future change starts passing all() through.
         */
        $this->request->remove('staff_id');
        $this->request->remove('needs_review');

        $this->merge([
            'email' => $this->filled('email')
                ? mb_strtolower(trim((string) $this->input('email')))
                : null,
        ]);
    }
}
