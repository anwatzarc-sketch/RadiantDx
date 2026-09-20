<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Standing of a professional licence.
 *
 * Suspended and Revoked are asserted by a person, not derived from a date.
 * Automated expiry checking must never overwrite them - a licence revoked by
 * its regulator does not become merely Expired once its end date passes.
 * See isManuallyAsserted().
 *
 * The ExpiringSoon window is a business rule read from configuration, not a
 * number baked into this enum.
 */
enum LicenseStatus: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case NotProvided = 'not_provided';


    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ExpiringSoon => 'Expiring Soon',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
            self::NotProvided => 'Not Provided',
        };
    }

    /**
     * Whether this status was asserted by a person rather than derived from a
     * date, and must therefore survive automated recalculation.
     */
    public function isManuallyAsserted(): bool
    {
        return match ($this) {
            self::Suspended, self::Revoked => true,
            default => false,
        };
    }

    /** Whether this status permits practising. */
    public function permitsPractice(): bool
    {
        return match ($this) {
            self::Active, self::ExpiringSoon => true,
            default => false,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::ExpiringSoon => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Expired => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::Suspended => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Revoked => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::NotProvided => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }
}
