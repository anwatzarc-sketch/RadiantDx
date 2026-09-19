<?php

declare(strict_types=1);

namespace App\Http\Requests\Laboratory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the operations that must record why they happened: cancelling a
 * requisition and unvalidating a result. The reason becomes part of the audit
 * trail, so it is required rather than optional.
 */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Record the reason for this action.',
            'reason.min' => 'Give a slightly more descriptive reason.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
