<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\TestResultType;
use App\Models\LaboratoryTest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LaboratoryTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $test = $this->route('test');
        $testId = $test instanceof LaboratoryTest ? $test->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('laboratory_tests', 'code')->ignore($testId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'specimen_type' => ['nullable', 'string', 'max:255'],
            'result_type' => ['required', Rule::enum(TestResultType::class)],
            'unit' => ['nullable', 'string', 'max:64'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'reference_low' => ['nullable', 'numeric'],
            'reference_high' => ['nullable', 'numeric'],
            'decimal_precision' => ['required', 'integer', 'min:0', 'max:6'],
            'turnaround_time_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code may contain letters, numbers, dots, dashes and underscores only.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
            'decimal_precision' => $this->input('decimal_precision', 2),
            'display_order' => $this->input('display_order', 0),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $low = $this->input('reference_low');
            $high = $this->input('reference_high');

            if ($low !== null && $high !== null && $low !== '' && $high !== '' && (float) $low > (float) $high) {
                $validator->errors()->add(
                    'reference_high',
                    'The upper reference value must be greater than or equal to the lower reference value.'
                );
            }
        });
    }
}
