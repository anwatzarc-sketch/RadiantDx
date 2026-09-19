<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Models\Role;
use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->ignore($roleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogue::names())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may contain lower case letters, numbers and hyphens only.',
            'permissions.*.in' => 'One of the submitted permissions is not part of the permission catalogue.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        /** @var array<int, string> $permissions */
        $permissions = $this->validated('permissions', []);

        return array_values(array_unique($permissions));
    }
}
