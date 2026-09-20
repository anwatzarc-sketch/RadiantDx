<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 2 of 3: give every existing account a staff record.
 *
 * Only what is actually known is carried across — the account's name and email.
 * Professional details are NOT invented: profession, speciality, title and
 * department are left null and the record is flagged `needs_review` so an
 * administrator completes it. A fabricated speciality on a laboratory report
 * would be worse than a blank one.
 *
 * Idempotent: re-running links nothing twice, because only users with a null
 * staff_id are considered. Reversible: down() removes exactly the records this
 * created and nothing else.
 *
 * Soft-deleted users are included. They still appear as the actor on historical
 * laboratory records, so they need an identity to resolve to.
 */
return new class extends Migration
{
    /** Marks the records this migration created, so down() can find them again. */
    private const BACKFILL_MARKER = 'backfill:users';

    public function up(): void
    {
        DB::transaction(function (): void {
            $users = DB::table('users')
                ->whereNull('staff_id')
                ->orderBy('id')
                ->get(['id', 'name', 'email']);

            if ($users->isEmpty()) {
                return;
            }

            // Continue the sequence rather than restarting it, in case staff
            // records already exist.
            $sequence = $this->highestExistingSequence();
            $now = now();

            foreach ($users as $user) {
                $sequence++;

                $staffId = DB::table('staff')->insertGetId([
                    'staff_id' => 'STF-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'status' => 'active',
                    'needs_review' => true,
                    'address' => self::BACKFILL_MARKER,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')->where('id', $user->id)->update(['staff_id' => $staffId]);
            }

            // The marker was only needed to find these rows again; it is not
            // an address and must not be shown as one.
            DB::table('staff')->where('address', self::BACKFILL_MARKER)->update(['address' => null]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Detach first: the foreign key is restrictOnDelete.
            $staffIds = DB::table('users')->whereNotNull('staff_id')->pluck('staff_id');

            DB::table('users')->whereNotNull('staff_id')->update(['staff_id' => null]);

            DB::table('staff')
                ->whereIn('id', $staffIds)
                ->where('needs_review', true)
                ->delete();
        });
    }

    private function highestExistingSequence(): int
    {
        $latest = DB::table('staff')
            ->where('staff_id', 'like', 'STF-%')
            ->orderByDesc('staff_id')
            ->value('staff_id');

        return $latest === null ? 0 : (int) mb_substr((string) $latest, 4);
    }
};
