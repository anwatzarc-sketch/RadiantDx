<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * UCUM units a reference-range age bound is expressed in.
 *
 * Day equivalents are used only to compare intervals with each other
 * (overlap checks, "narrowest band wins"). A patient's age is never computed
 * through them: it is counted in each bound's own unit, in whole units, so a
 * child is exactly "1 a" on their first birthday whatever the leap years.
 */
enum AgeUnit: string
{
    case Days = 'd';
    case Months = 'mo';
    case Years = 'a';

    public function inDays(): float
    {
        return match ($this) {
            self::Days => 1.0,
            self::Months => 30.4375,
            self::Years => 365.25,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Days => 'days',
            self::Months => 'months',
            self::Years => 'years',
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
