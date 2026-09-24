<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\AbnormalWhen;
use App\Enums\AgeUnit;
use App\Enums\RangeSex;
use App\Models\CodeSetValue;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTestParameter;
use App\Services\Laboratory\ReferenceRangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReferenceRangeRequest extends FormRequest
{
    private const NUMERIC_FIELDS = [
        'age_min', 'age_max', 'reference_low', 'reference_high', 'critical_low', 'critical_high', 'display_order',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ageCategories = CodeSetValue::query()->where('value_set', 'age_category')->pluck('code')->all();

        return [
            'sex' => ['required', Rule::enum(RangeSex::class)],
            'age_category' => array_filter([
                'nullable',
                'string',
                'max:32',
                $ageCategories === [] ? null : Rule::in($ageCategories),
            ]),
            'age_min' => ['nullable', 'numeric', 'min:0', 'max:999999', 'required_with:age_min_unit'],
            'age_min_unit' => ['nullable', Rule::enum(AgeUnit::class), 'required_with:age_min'],
            'age_max' => ['nullable', 'numeric', 'gt:0', 'max:999999', 'required_with:age_max_unit'],
            'age_max_unit' => ['nullable', Rule::enum(AgeUnit::class), 'required_with:age_max'],
            'reference_low' => ['nullable', 'numeric'],
            'reference_high' => ['nullable', 'numeric'],
            'critical_low' => ['nullable', 'numeric'],
            'critical_high' => ['nullable', 'numeric'],
            'reference_range_text' => ['nullable', 'string', 'max:255'],
            'abnormal_when' => ['required', Rule::enum(AbnormalWhen::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'age_min.required_with' => 'Give the lower age as well as its unit.',
            'age_min_unit.required_with' => 'Choose the unit the lower age is in.',
            'age_max.required_with' => 'Give the upper age as well as its unit.',
            'age_max_unit.required_with' => 'Choose the unit the upper age is in.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Blank inputs arrive as empty strings; null is what "unbounded" and
        // "no limit" are stored as.
        $blanks = [];

        foreach ([...self::NUMERIC_FIELDS, 'age_min_unit', 'age_max_unit', 'age_category', 'reference_range_text', 'notes'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $blanks[$field] = null;
            }
        }

        $this->merge($blanks);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkOrder($validator, 'reference_low', 'reference_high', 'The upper reference limit must not be below the lower one.');
            $this->checkOrder($validator, 'critical_low', 'critical_high', 'The upper critical limit must not be below the lower one.');

            $this->checkCriticalOutsideReference($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $candidate = $this->candidate();

            if ($candidate->age_min !== null && $candidate->age_max !== null
                && $candidate->ageMinInDays() >= $candidate->ageMaxInDays()) {
                $validator->errors()->add('age_max', 'The upper age must be above the lower age.');

                return;
            }

            // Saving makes the range active, so it must not share an age with
            // another active range of the same sex.
            $service = app(ReferenceRangeService::class);
            $clash = $service->overlapping($candidate);

            if ($clash !== null) {
                $validator->errors()->add(
                    'age_min',
                    'These ages overlap '.$service->describe($clash).'. Adjust the ages, or deactivate that range first.'
                );
            }
        });
    }

    /**
     * The validated attributes, typed for the model.
     *
     * @return array<string, mixed>
     */
    public function rangeAttributes(): array
    {
        $attributes = $this->safe()->only([
            'sex', 'age_category', 'age_min', 'age_min_unit', 'age_max', 'age_max_unit',
            'reference_low', 'reference_high', 'critical_low', 'critical_high',
            'reference_range_text', 'abnormal_when', 'notes', 'display_order',
        ]);

        // A blank order keeps the current one on edit; on create the service
        // places the range after the parameter's others.
        if (($attributes['display_order'] ?? null) === null) {
            unset($attributes['display_order']);
        }

        return $attributes;
    }

    /** The range as it would be saved, for the overlap check. */
    private function candidate(): LaboratoryReferenceRange
    {
        $existing = $this->route('referenceRange');

        if ($existing instanceof LaboratoryReferenceRange) {
            $candidate = clone $existing;
            $candidate->fill($this->validated());

            return $candidate;
        }

        /** @var LaboratoryTestParameter $parameter */
        $parameter = $this->route('parameter');
        $candidate = new LaboratoryReferenceRange($this->validated());
        $candidate->laboratory_test_parameter_id = $parameter->getKey();

        return $candidate;
    }

    private function checkOrder(Validator $validator, string $lowKey, string $highKey, string $message): void
    {
        $low = $this->input($lowKey);
        $high = $this->input($highKey);

        if ($low !== null && $high !== null && (float) $low > (float) $high) {
            $validator->errors()->add($highKey, $message);
        }
    }

    /**
     * A critical limit inside the reference range would call a value both
     * normal and critical. Equal is allowed: it means every low (or high)
     * value is already critical.
     */
    private function checkCriticalOutsideReference(Validator $validator): void
    {
        $pairs = [
            ['critical_low', 'reference_low', fn (float $critical, float $reference): bool => $critical <= $reference,
                'The critical low limit must be at or below the lower reference limit.'],
            ['critical_high', 'reference_high', fn (float $critical, float $reference): bool => $critical >= $reference,
                'The critical high limit must be at or above the upper reference limit.'],
            ['critical_low', 'reference_high', fn (float $critical, float $reference): bool => $critical < $reference,
                'The critical low limit must be below the upper reference limit.'],
            ['critical_high', 'reference_low', fn (float $critical, float $reference): bool => $critical > $reference,
                'The critical high limit must be above the lower reference limit.'],
        ];

        foreach ($pairs as [$criticalKey, $referenceKey, $holds, $message]) {
            $critical = $this->input($criticalKey);
            $reference = $this->input($referenceKey);

            if ($critical !== null && $reference !== null && ! $holds((float) $critical, (float) $reference)) {
                $validator->errors()->add($criticalKey, $message);

                return;
            }
        }
    }
}
