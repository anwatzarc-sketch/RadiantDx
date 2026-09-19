<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\Interpretation;
use App\Enums\ParameterDataType;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultParameter;
use App\Services\Laboratory\InterpretationEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates entered laboratory values against the shape declared for each
 * parameter: numbers must parse, fixed value types must be one of the
 * configured choices.
 */
class ResultEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'values' => ['array'],
            'values.*.value' => ['nullable', 'string', 'max:2000'],
            'values.*.interpretation' => ['nullable', Rule::enum(Interpretation::class)],
            'values.*.comment' => ['nullable', 'string', 'max:500'],
            'interpretation' => ['nullable', 'string', 'max:4000'],
            'comments' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $result = $this->route('result');

            if (! $result instanceof LaboratoryResult) {
                return;
            }

            $evaluator = app(InterpretationEvaluator::class);
            $parameters = $result->parameters->keyBy('id');

            foreach ((array) $this->input('values', []) as $id => $input) {
                $parameter = $parameters->get((int) $id);

                if ($parameter === null) {
                    $validator->errors()->add("values.{$id}.value", 'Unknown parameter for this result.');

                    continue;
                }

                $value = trim((string) ($input['value'] ?? ''));

                if ($value === '') {
                    continue;
                }

                $this->validateValue($validator, $parameter, $value, $evaluator, (string) $id);
            }
        });
    }

    private function validateValue(
        Validator $validator,
        LaboratoryResultParameter $parameter,
        string $value,
        InterpretationEvaluator $evaluator,
        string $key,
    ): void {
        $field = "values.{$key}.value";

        if ($parameter->data_type === ParameterDataType::Numeric) {
            if ($evaluator->toNumeric($value) === null) {
                $validator->errors()->add($field, "{$parameter->parameter_name} must be a number.");
            }

            return;
        }

        if ($parameter->data_type->hasFixedValues()) {
            $allowed = array_keys($parameter->selectableValues());

            if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                $validator->errors()->add(
                    $field,
                    "{$parameter->parameter_name} must be one of the configured values."
                );
            }
        }
    }

    /**
     * Entered values keyed by result parameter id.
     *
     * @return array<int, array{value: string|null, interpretation: string|null, comment: string|null}>
     */
    public function values(): array
    {
        /** @var array<int|string, array<string, mixed>> $raw */
        $raw = $this->validated('values', []);
        $values = [];

        foreach ($raw as $id => $input) {
            $values[(int) $id] = [
                'value' => $input['value'] ?? null,
                'interpretation' => $input['interpretation'] ?? null,
                'comment' => $input['comment'] ?? null,
            ];
        }

        return $values;
    }

    /** @return array{interpretation: string|null, comments: string|null} */
    public function resultAttributes(): array
    {
        return [
            'interpretation' => $this->validated('interpretation'),
            'comments' => $this->validated('comments'),
        ];
    }
}
