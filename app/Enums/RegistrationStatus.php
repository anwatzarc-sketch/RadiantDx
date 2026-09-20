<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Standing of registration with a professional body.
 */
enum RegistrationStatus: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Registered = 'registered';
    case Provisional = 'provisional';
    case Lapsed = 'lapsed';
    case NotRegistered = 'not_registered';


    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Provisional => 'Provisional',
            self::Lapsed => 'Lapsed',
            self::NotRegistered => 'Not Registered',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Registered => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Provisional => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Lapsed => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::NotRegistered => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }
}
