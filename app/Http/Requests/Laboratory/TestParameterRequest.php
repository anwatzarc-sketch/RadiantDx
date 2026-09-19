<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\ParameterDataType;
use App\Models\LaboratoryTestParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TestParameterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $parameter = $this->route('parameter');
        $parameterId = $parameter instanceof LaboratoryTestParameter ? $parameter->getKey() : null;
        $testId = $this->input('laboratory_test_id');

        return [
            'laboratory_test_id' => ['required', 'integer', Rule::exists('laboratory_tests', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('laboratory_test_parameters', 'code')
                    ->where('laboratory_test_id', $testId)
                    ->ignore($parameterId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'data_type' => ['required', Rule::enum(ParameterDataType::class)],
            'unit' => ['nullable', 'string', 'max:64'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'reference_low' => ['nullable', 'numeric'],
            'reference_high' => ['nullable', 'numeric'],
            'critical_low' => ['nullable', 'numeric'],
            'critical_high' => ['nullable', 'numeric'],
            'decimal_precision' => ['required', 'integer', 'min:0', 'max:6'],
            'abnormal_when' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],

            'options' => ['nullable', 'array', 'max:50'],
            'options.*.value' => ['required_with:options.*.label', 'nullable', 'string', 'max:255'],
            'options.*.label' => ['nullable', 'string', 'max:255'],
            'options.*.is_abnormal' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'This test already has a parameter with that code.',
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
            $dataType = ParameterDataType::tryFrom((string) $this->input('data_type'));

            if ($dataType === ParameterDataType::Dropdown && $this->dropdownOptions() === []) {
                $validator->errors()->add('options', 'A dropdown parameter needs at least one value.');
            }

            foreach ([['reference_low', 'reference_high'], ['critical_low', 'critical_high']] as [$lowKey, $highKey]) {
                $low = $this->input($lowKey);
                $high = $this->input($highKey);

                if ($low !== null && $high !== null && $low !== '' && $high !== '' && (float) $low > (float) $high) {
                    $validator->errors()->add($highKey, 'The upper value must be greater than or equal to the lower value.');
                }
            }
        });
    }

    /**
     * Dropdown values with the blank template rows removed.
     *
     * @return array<int, array{value: string, label: string, is_abnormal: bool}>
     */
    public function dropdownOptions(): array
    {
        /** @var array<int, array<string, mixed>> $options */
        $options = $this->input('options', []);
        $clean = [];

        foreach ($options as $option) {
            $value = trim((string) ($option['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $clean[] = [
                'value' => $value,
                'label' => trim((string) ($option['label'] ?? '')) ?: $value,
                'is_abnormal' => (bool) ($option['is_abnormal'] ?? false),
            ];
        }

        return $clean;
    }
}
