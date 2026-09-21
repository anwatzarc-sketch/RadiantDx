<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating the system account for a staff record.
 *
 * The form carries only account settings — sign-in address, role, status and
 * credentials. Who the account belongs to is already known: the request was
 * made against a specific staff profile, and the controller reads the staff
 * record from the route.
 *
 * Any identity field in the payload is stripped before validation, so a
 * crafted request cannot attach an account to somebody else's staff record.
 */
class StaffAccountRequest extends FormRequest
{
    /**
     * Identity fields that a client might send and that must never be read.
     * The staff record always comes from the route.
     */
    private const IGNORED_IDENTITY_FIELDS = [
        'staff_id',
        'staff_code',
        'staff',
        'user_id',
        'name',
        'full_name',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role_id.exists' => 'Select an active role.',
            'email.unique' => 'Another account already uses this email address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (self::IGNORED_IDENTITY_FIELDS as $field) {
            $this->request->remove($field);
        }

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function accountAttributes(): array
    {
        return $this->safe()->only(['email', 'password', 'role_id', 'is_active']);
    }
}
