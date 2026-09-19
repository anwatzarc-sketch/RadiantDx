<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
            self::Unknown => 'Not recorded',
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
