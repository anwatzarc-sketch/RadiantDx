<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Models\LaboratoryPanel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaboratoryPanelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $panel = $this->route('panel');
        $panelId = $panel instanceof LaboratoryPanel ? $panel->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('laboratory_panels', 'code')->ignore($panelId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'tests' => ['nullable', 'array'],
            'tests.*' => ['integer', Rule::exists('laboratory_tests', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
            'display_order' => $this->input('display_order', 0),
        ]);
    }

    /**
     * Member tests in the order the form submitted them.
     *
     * @return array<int, int>
     */
    public function testIds(): array
    {
        /** @var array<int, int|string> $tests */
        $tests = $this->validated('tests', []);

        return array_values(array_unique(array_map('intval', $tests)));
    }
}
