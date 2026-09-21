<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Enums\StaffStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Serves both creation and editing. On edit the password is optional, and
 * leaving it blank keeps the existing one.
 *
 * On creation the account is bound to a staff record chosen in the form, so
 * `staff_id` is accepted here -- but only for a record that is Active and has
 * no account yet, and only on create. An existing account cannot be moved to
 * another staff record; User::booted() refuses that, and the field is dropped
 * from the payload on update so a crafted request cannot attempt it.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->getKey() : null;

        return [
            // Create only. The account's display name is taken from the staff
            // record rather than typed, so `name` is not accepted on create.
            'staff_id' => $userId === null
                ? [
                    'required',
                    'integer',
                    Rule::exists('staff', 'id')->where('status', StaffStatus::Active->value),
                    // Matches the plain unique index on users.staff_id, which
                    // soft-deleted accounts still occupy.
                    Rule::unique('users', 'staff_id'),
                ]
                : ['prohibited'],
            'name' => [$userId === null ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'password' => [
                $userId === null ? 'required' : 'nullable',
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
            'staff_id.required' => 'Select the staff member this account belongs to.',
            'staff_id.exists' => 'Select a staff member whose standing is Active.',
            'staff_id.unique' => 'That staff member already has an account.',
            'staff_id.prohibited' => 'An account cannot be moved to a different staff record.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function validatedAttributes(): array
    {
        $data = $this->safe()->only(['staff_id', 'name', 'email', 'role_id', 'is_active', 'password']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Derived from the staff record on create, so whatever arrived in the
        // read-only mirror field is discarded rather than trusted.
        if (array_key_exists('staff_id', $data)) {
            unset($data['name']);
            $data['staff_id'] = (int) $data['staff_id'];
        }

        // A form submits '3', and the `integer` rule checks that without
        // converting it. UserService declares strict types and types the role
        // as ?int, so the cast has to happen here rather than being left to a
        // coercion that strict_types does not perform.
        if (array_key_exists('role_id', $data)) {
            $data['role_id'] = $data['role_id'] === null ? null : (int) $data['role_id'];
        }

        return $data;
    }
}
