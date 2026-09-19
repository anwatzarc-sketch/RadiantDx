<?php

declare(strict_types=1);

namespace App\Enums;

enum RequisitionPriority: string
{
    case Routine = 'routine';
    case Urgent = 'urgent';
    case Stat = 'stat';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Routine',
            self::Urgent => 'Urgent',
            self::Stat => 'STAT',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Routine => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Urgent => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Stat => 'bg-rose-100 text-rose-800 ring-rose-600/20',
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
