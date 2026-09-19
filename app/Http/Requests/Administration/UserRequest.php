<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Serves both creation and editing. On edit the password is optional, and
 * leaving it blank keeps the existing one.
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
            'name' => ['required', 'string', 'max:255'],
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
        $data = $this->safe()->only(['name', 'email', 'role_id', 'is_active', 'password']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
