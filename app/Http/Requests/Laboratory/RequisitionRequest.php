<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\Gender;
use App\Enums\RequisitionPriority;
use App\Http\Requests\Concerns\IgnoresClientActorFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequisitionRequest extends FormRequest
{
    use IgnoresClientActorFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_identifier' => ['required', 'string', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_gender' => ['nullable', Rule::enum(Gender::class)],
            'patient_date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'patient_age_years' => ['nullable', 'integer', 'min:0', 'max:130'],

            'requested_date' => ['required', 'date', 'before_or_equal:today'],
            'requesting_department' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(RequisitionPriority::class)],

            'clinical_indication' => ['nullable', 'string', 'max:2000'],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],

            'investigations' => ['array', 'max:100'],
            'investigations.*' => ['string', 'regex:/^(test|panel):[1-9][0-9]*$/'],

            'action' => ['required', Rule::in(['draft', 'submit'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'investigations.*.regex' => 'One of the selected investigations is not valid.',
            'patient_identifier.required' => 'A patient identifier is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The requesting clinician is the signed-in user, resolved by
        // RequisitionService. A value sent for it is discarded here.
        $this->ignoreClientActorFields();

        $this->merge([
            'action' => $this->input('action', 'draft'),
            'priority' => $this->input('priority', RequisitionPriority::Routine->value),
            'investigations' => array_values(array_filter((array) $this->input('investigations', []))),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A draft may be saved empty; submitting requires actual work to do.
            if ($this->input('action') === 'submit' && $this->selections() === []) {
                $validator->errors()->add(
                    'investigations',
                    'Add at least one test or panel before submitting the requisition.'
                );
            }

            if ($this->input('patient_date_of_birth') === null && $this->input('patient_age_years') === null) {
                $validator->errors()->add(
                    'patient_age_years',
                    'Record either the date of birth or the age of the patient.'
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function requisitionAttributes(): array
    {
        return $this->safe()->only([
            'patient_identifier',
            'patient_name',
            'patient_gender',
            'patient_date_of_birth',
            'patient_age_years',
            'requested_date',
            'requesting_department',
            'priority',
            'clinical_indication',
            'clinical_notes',
        ]);
    }

    /**
     * The chosen investigations, decoded from their "type:id" form.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public function selections(): array
    {
        /** @var array<int, string> $raw */
        $raw = $this->input('investigations', []);
        $selections = [];

        foreach (array_unique($raw) as $entry) {
            [$type, $id] = explode(':', $entry, 2);
            $selections[] = ['type' => $type, 'id' => (int) $id];
        }

        return $selections;
    }

    public function shouldSubmit(): bool
    {
        return $this->input('action') === 'submit';
    }
}
