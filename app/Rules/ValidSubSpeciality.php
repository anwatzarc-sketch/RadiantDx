<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SubSpeciality;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a sub-speciality belongs to the speciality submitted with it.
 *
 * The dependent select in the interface only offers matching pairs, but that is
 * a convenience. This is the enforcement: a request pairing Echocardiography
 * with General Surgery is rejected server-side whatever the form showed.
 */
class ValidSubSpeciality implements ValidationRule
{
    public function __construct(private readonly ?string $speciality) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Unknown SubSpeciality value');

            return;
        }

        $case = SubSpeciality::tryFrom($value);

        if ($case === null || ! $case->isActive()) {
            $fail('Unknown SubSpeciality value');

            return;
        }

        // A sub-speciality cannot stand on its own.
        if ($this->speciality === null || $this->speciality === '') {
            $fail('Select a speciality before choosing a sub-speciality.');

            return;
        }

        if ($case->parent() !== $this->speciality) {
            $fail(':attribute is not a sub-speciality of the selected speciality.');
        }
    }
}
