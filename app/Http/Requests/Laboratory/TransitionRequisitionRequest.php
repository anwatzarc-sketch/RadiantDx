<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use App\Enums\RequisitionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(RequisitionStatus::class)->except([
                    RequisitionStatus::Draft,
                    RequisitionStatus::Cancelled,
                ]),
            ],
        ];
    }

    public function targetStatus(): RequisitionStatus
    {
        return RequisitionStatus::from((string) $this->validated('status'));
    }
}
