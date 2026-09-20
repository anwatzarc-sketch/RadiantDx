<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use App\Rules\SharedEnumValue;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One qualification. Which staff record it belongs to comes from the route.
 */
class StaffQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', new SharedEnumValue('QualificationType')],
            'institution' => ['required', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'awarded_year' => ['nullable', 'integer', 'min:1900', 'max:'.(int) date('Y')],
            'reference' => ['nullable', 'string', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The owning record is a route parameter; a staff_id in the body is
        // ignored rather than trusted.
        $this->request->remove('staff_id');
    }
}
