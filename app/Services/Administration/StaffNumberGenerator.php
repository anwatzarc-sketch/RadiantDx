<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Models\Staff;
use App\Services\Laboratory\ReferenceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues staff identifiers in the form STF-000001.
 *
 * This mirrors {@see ReferenceNumberGenerator}, which
 * has issued requisition and result numbers in production: the next value is
 * derived from the highest already issued, read inside the caller's
 * transaction, and the unique index on the column is the actual guarantee.
 * Two concurrent callers may compute the same number; the second insert fails
 * and {@see nextWithRetry()} tries again.
 *
 * Deliberately not a second numbering architecture — the application already
 * had one that works, and a lock table would be a competing mechanism for the
 * same problem.
 *
 * Unlike the laboratory references, this sequence does not restart daily:
 * a staff identifier is permanent and never reused, so it counts from one for
 * the life of the installation.
 */
class StaffNumberGenerator
{
    private const PREFIX = 'STF-';

    private const PADDING = 6;

    /** How many times to retry when a concurrent insert takes the number first. */
    private const MAX_ATTEMPTS = 5;

    public function next(): string
    {
        $latest = DB::table((new Staff)->getTable())
            ->where('staff_code', 'like', self::PREFIX.'%')
            ->orderByDesc('staff_code')
            ->value('staff_code');

        $sequence = $latest === null
            ? 1
            : ((int) mb_substr((string) $latest, mb_strlen(self::PREFIX))) + 1;

        return self::format($sequence);
    }

    /**
     * Runs $persist with a freshly issued identifier, retrying if another
     * request took that number between generation and insert.
     *
     * @template TResult
     *
     * @param  callable(string): TResult  $persist
     * @return TResult
     */
    public function nextWithRetry(callable $persist): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $persist($this->next());
            } catch (UniqueConstraintViolationException $e) {
                // Someone else took this number. Recompute and try again; give
                // up rather than spin if contention is pathological.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    public static function format(int $sequence): string
    {
        return self::PREFIX.str_pad((string) $sequence, self::PADDING, '0', STR_PAD_LEFT);
    }
}
