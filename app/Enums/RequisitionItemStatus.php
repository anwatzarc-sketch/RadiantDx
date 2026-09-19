<?php

declare(strict_types=1);

namespace App\Enums;

/** Progress of one requested investigation through the laboratory. */
enum RequisitionItemStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Processing = 'processing';
    case Resulted = 'resulted';
    case Validated = 'validated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Collected => 'Collected',
            self::Processing => 'Processing',
            self::Resulted => 'Result entered',
            self::Validated => 'Validated',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Collected => 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
            self::Processing => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Resulted => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Validated => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Cancelled => 'bg-rose-100 text-rose-800 ring-rose-600/20',
        };
    }
}
