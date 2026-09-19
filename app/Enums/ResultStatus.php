<?php

declare(strict_types=1);

namespace App\Enums;

/** How far result entry has progressed for a single investigation. */
enum ResultStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting entry',
            self::InProgress => 'Partially entered',
            self::Completed => 'Entry complete',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::InProgress => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Completed => 'bg-sky-100 text-sky-800 ring-sky-600/20',
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
