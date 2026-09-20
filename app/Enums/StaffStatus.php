<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Employment standing of a staff record.
 *
 * This gates system access: only Active permits signing in and performing
 * laboratory work. Suspended and Former are retained rather than deleted so
 * historical laboratory activity keeps resolving to a real person.
 */
enum StaffStatus: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Former = 'former';


    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Former => 'Former',
        };
    }

    /**
     * Whether this standing permits signing in and performing laboratory work.
     *
     * Enforced server-side by the actor resolver, never by hiding controls.
     */
    public function permitsSystemAccess(): bool
    {
        return $this === self::Active;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Inactive => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Suspended => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Former => 'bg-rose-100 text-rose-800 ring-rose-600/20',
        };
    }
}
