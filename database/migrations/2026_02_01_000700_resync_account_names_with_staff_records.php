<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair: bring account names back in line with their staff records.
 *
 * Between the staff backfill and the cascade added to StaffService, a name
 * corrected on a staff record did not reach the account behind it. Every
 * requisition and result screen — and the printed report — names the actor
 * through the account, so those records kept showing the stale name.
 *
 * The staff record is the source of truth for who someone is, so it wins.
 *
 * Only genuinely divergent rows are touched, which makes this safe to re-run.
 * There is no down(): the previous values were stale copies, and restoring them
 * would reintroduce the defect.
 */
return new class extends Migration
{
    public function up(): void
    {
        $divergent = DB::table('users')
            ->join('staff', 'users.staff_id', '=', 'staff.id')
            ->whereColumn('users.name', '!=', 'staff.full_name')
            ->select('users.id', 'staff.full_name')
            ->get();

        foreach ($divergent as $row) {
            DB::table('users')->where('id', $row->id)->update(['name' => $row->full_name]);
        }
    }

    public function down(): void
    {
        // Intentionally empty — see the class comment.
    }
};
