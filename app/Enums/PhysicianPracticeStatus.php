<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Whether a physician is currently seeing patients.
 */
enum PhysicianPracticeStatus: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Practising = 'practising';
    case OnLeave = 'on_leave';
    case NonPractising = 'non_practising';
    case Retired = 'retired';


    public function label(): string
    {
        return match ($this) {
            self::Practising => 'Practising',
            self::OnLeave => 'On Leave',
            self::NonPractising => 'Non-practising',
            self::Retired => 'Retired',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Practising => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::OnLeave => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::NonPractising => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Retired => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }
}
