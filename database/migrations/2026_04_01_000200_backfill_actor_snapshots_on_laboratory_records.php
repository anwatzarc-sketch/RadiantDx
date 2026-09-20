<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives existing laboratory records an actor snapshot.
 *
 * These records predate staff identities, so the best available answer differs
 * per row and the migration is explicit about which it used:
 *
 *   authenticated  — the actor foreign key resolves to an account that now has
 *                    a staff record, so the full professional identity is
 *                    recovered. Provenance is recorded as pre_migration all the
 *                    same: it was reconstructed afterwards, not captured at the
 *                    time, and a reader should be able to tell the difference.
 *
 *   pre_migration  — only a name survives (from the account, or from the free
 *                    text clinician field), or nothing does. A row with nothing
 *                    gets "Unknown — pre-migration" rather than a blank, so a
 *                    historical report still reads as a document rather than
 *                    looking broken.
 *
 * Idempotent: only rows with no snapshot are touched.
 */
return new class extends Migration
{
    private const UNKNOWN = 'Unknown — pre-migration';

    public function up(): void
    {
        $this->backfill('laboratory_requisitions', 'created_by', 'requested_by', 'requesting_clinician');
        $this->backfill('laboratory_requisitions', 'cancelled_by', 'cancelled_by');
        $this->backfill('laboratory_results', 'performed_by', 'performed_by');
        $this->backfill('laboratory_results', 'validated_by', 'validated_by');
    }

    public function down(): void
    {
        foreach ([
            'laboratory_requisitions' => ['requested_by', 'cancelled_by'],
            'laboratory_results' => ['performed_by', 'validated_by', 'printed_by'],
        ] as $table => $roles) {
            foreach ($roles as $role) {
                DB::table($table)->update([
                    "{$role}_staff_id" => null,
                    "{$role}_actor_name" => null,
                    "{$role}_actor_title" => null,
                    "{$role}_actor_speciality" => null,
                    "{$role}_actor_provenance" => null,
                ]);
            }
        }
    }

    /**
     * @param  string  $fkColumn        the existing actor foreign key
     * @param  string  $role            the snapshot role to populate
     * @param  string|null  $nameColumn a free-text name to fall back on
     */
    private function backfill(string $table, string $fkColumn, string $role, ?string $nameColumn = null): void
    {
        $rows = DB::table($table)
            ->whereNull("{$role}_actor_name")
            ->where(function ($query) use ($fkColumn, $nameColumn): void {
                $query->whereNotNull($fkColumn);

                if ($nameColumn !== null) {
                    $query->orWhereNotNull($nameColumn);
                }
            })
            ->get(array_values(array_filter(['id', $fkColumn, $nameColumn])));

        foreach ($rows as $row) {
            $userId = $row->{$fkColumn} ?? null;

            $staff = $userId === null ? null : DB::table('users')
                ->leftJoin('staff', 'users.staff_id', '=', 'staff.id')
                ->where('users.id', $userId)
                ->select('staff.id as staff_id', 'staff.full_name', 'staff.title', 'staff.speciality', 'users.name as user_name')
                ->first();

            $freeText = $nameColumn === null ? null : ($row->{$nameColumn} ?? null);

            $name = $staff->full_name
                ?? $staff->user_name
                ?? (is_string($freeText) && trim($freeText) !== '' ? trim($freeText) : null)
                ?? self::UNKNOWN;

            DB::table($table)->where('id', $row->id)->update([
                "{$role}_staff_id" => $staff->staff_id ?? null,
                "{$role}_actor_name" => $name,
                "{$role}_actor_title" => $staff->title ?? null,
                "{$role}_actor_speciality" => $staff->speciality ?? null,
                // Reconstructed after the fact, never captured at the time.
                "{$role}_actor_provenance" => 'pre_migration',
            ]);
        }
    }
};
