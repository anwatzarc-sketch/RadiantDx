<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Rules\SharedEnumValue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The licensing, registration and practice section of a staff record.
 *
 * Separate from StaffRequest because it is separately permissioned: an
 * administrator may maintain staff records without being trusted to record
 * that somebody's licence is in good standing.
 *
 * license_status accepts the manually meaningful values. Suspended and Revoked
 * set here survive the automatic expiry sweep, which is the point of storing
 * the status rather than deriving it on read.
 */
class PhysicianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'professional_license' => ['nullable', 'string', 'max:64'],
            'license_authority' => ['nullable', 'string', 'max:128'],
            'license_issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'license_expiry' => ['nullable', 'date', 'after:license_issued_on'],
            'license_status' => ['required', new SharedEnumValue('LicenseStatus')],

            'registration_number' => ['nullable', 'string', 'max:64'],
            'registration_authority' => ['nullable', 'string', 'max:128'],
            'registration_status' => ['required', new SharedEnumValue('RegistrationStatus')],

            'practice_status' => ['nullable', new SharedEnumValue('PhysicianPracticeStatus')],
            'professional_phone' => ['nullable', 'string', 'max:32'],
            'professional_email' => ['nullable', 'email', 'max:255'],
            'professional_bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'license_expiry.after' => 'The expiry date must fall after the issue date.',
        ];
    }
}
