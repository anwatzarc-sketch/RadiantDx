<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A laboratory test either reports one value of its own, or reports a set of
 * separately configured parameters.
 */
enum TestResultType: string
{
    case Single = 'single';
    case Parameterised = 'parameterised';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single value',
            self::Parameterised => 'Multiple parameters',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Single => 'The test reports one result. Unit and reference range are configured on the test itself.',
            self::Parameterised => 'The test reports one result per configured parameter, each with its own unit and reference range.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
