<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\LicenseStatus;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Keeps a licence's status in step with its expiry date.
 *
 * Two rules make this safe to run unattended:
 *
 *  1. A status a person asserted is never overwritten. Suspended and Revoked
 *     are decisions by a regulator, not facts about a date — a revoked licence
 *     does not quietly become "Expired" when its end date passes, and a
 *     suspended one does not become "Active" because its expiry is still in the
 *     future. See LicenseStatus::isManuallyAsserted().
 *
 *  2. The "expiring soon" window is configuration, not a number written into
 *     the code, because how much notice a laboratory wants before a licence
 *     lapses is a local policy.
 */
class LicenseStatusDeriver
{
    /** The status a licence should currently hold, given its dates. */
    public function derive(Staff $staff, ?Carbon $asAt = null): LicenseStatus
    {
        $current = $staff->license_status ?? LicenseStatus::NotProvided;

        // Rule 1: a human said so. Leave it alone.
        if ($current->isManuallyAsserted()) {
            return $current;
        }

        if ($staff->professional_license === null || $staff->professional_license === '') {
            return LicenseStatus::NotProvided;
        }

        $expiry = $staff->license_expiry;

        if ($expiry === null) {
            // A licence number with no expiry recorded is current as far as
            // this application knows; claiming otherwise would be inventing.
            return LicenseStatus::Active;
        }

        $asAt ??= Carbon::now();

        if ($expiry->isBefore($asAt->copy()->startOfDay())) {
            return LicenseStatus::Expired;
        }

        return $expiry->isBefore($asAt->copy()->addDays($this->warningDays()))
            ? LicenseStatus::ExpiringSoon
            : LicenseStatus::Active;
    }

    /**
     * Applies the derived status, returning whether anything changed.
     *
     * Saves only on a real change so an unattended sweep does not touch every
     * row's updated_at every night.
     */
    public function apply(Staff $staff, ?Carbon $asAt = null): bool
    {
        $derived = $this->derive($staff, $asAt);

        if ($staff->license_status === $derived) {
            return false;
        }

        $staff->license_status = $derived;
        $staff->save();

        return true;
    }

    public function warningDays(): int
    {
        return (int) config('laboratory.licensing.expiring_soon_days', 60);
    }
}
