<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Enums\EnumRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a value against a published controlled vocabulary.
 *
 * The match is exact and case-sensitive on the machine value. Display labels
 * are never accepted, nothing is trimmed into validity, and there is no fuzzy
 * matching, silent conversion, substitution or fallback: a value is either a
 * member of the vocabulary or the input is rejected.
 *
 * Retired values are rejected for new input but remain resolvable for display,
 * so historical records keep rendering correctly.
 */
class SharedEnumValue implements ValidationRule
{
    public function __construct(
        private readonly string $enumName,
        private readonly bool $allowInactive = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! EnumRegistry::has($this->enumName)) {
            // A rule naming a vocabulary that is not published is a programming
            // error, not user input. Fail closed rather than letting anything
            // through unvalidated.
            $fail("Unknown {$this->enumName} value");

            return;
        }

        if (! is_string($value)) {
            $fail("Unknown {$this->enumName} value");

            return;
        }

        $class = EnumRegistry::resolve($this->enumName);
        $case = $class::tryFrom($value);

        if ($case === null) {
            $fail("Unknown {$this->enumName} value");

            return;
        }

        if (! $this->allowInactive && ! EnumRegistry::isSelectableValue($this->enumName, $value)) {
            $fail("Unknown {$this->enumName} value");
        }
    }
}
