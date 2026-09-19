<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationStatus: string
{
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::PendingValidation => 'Pending validation',
            self::Validated => 'Validated',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingValidation => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Validated => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
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
