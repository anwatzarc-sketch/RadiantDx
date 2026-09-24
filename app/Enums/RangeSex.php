<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The sex a reference range applies to (HL7 RFR.2, Table 0001 subset).
 *
 * Any matches every patient. M and F match only a patient recorded as male or
 * female: a patient whose sex is unknown is compared against Any rows alone,
 * never guessed into one of the others.
 */
enum RangeSex: string
{
    case Any = 'any';
    case Male = 'M';
    case Female = 'F';

    public static function forPatient(?Gender $gender): ?self
    {
        return match ($gender) {
            Gender::Male => self::Male,
            Gender::Female => self::Female,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any sex',
            self::Male => 'Male',
            self::Female => 'Female',
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
