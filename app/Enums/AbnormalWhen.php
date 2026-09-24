<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side of a numeric reference range a value is flagged on.
 *
 * Carried by a reference range and snapshotted onto the result row, where the
 * numeric evaluator reads it. The same rule governs the critical flags: a
 * range that only flags high values never raises LL either.
 *
 * Note that the parameter's own abnormal_when column is a different thing: for
 * yes/no and positive/negative parameters it names the value that is
 * abnormal. The evaluator reads this enum only for numeric parameters.
 */
enum AbnormalWhen: string
{
    case OutsideRange = 'outside_range';
    case AboveHigh = 'above_high';
    case BelowLow = 'below_low';
    case Never = 'never';

    public function checksLow(): bool
    {
        return $this === self::OutsideRange || $this === self::BelowLow;
    }

    public function checksHigh(): bool
    {
        return $this === self::OutsideRange || $this === self::AboveHigh;
    }

    public function label(): string
    {
        return match ($this) {
            self::OutsideRange => 'Outside the range',
            self::AboveHigh => 'Above the upper limit only',
            self::BelowLow => 'Below the lower limit only',
            self::Never => 'Never flag',
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
