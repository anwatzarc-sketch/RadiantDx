<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable actor snapshots on the laboratory records.
 *
 * The existing foreign keys stay exactly as they are. They answer "which
 * account did this", which is the right question for an operational join. They
 * are the wrong question for a report: resolving a name through a live join
 * means a report reprinted in 2028 shows whatever the person is called then,
 * not what the filed copy said.
 *
 * So each role that appears on a printed report or a historical screen also
 * gets a frozen copy of the professional identity as it was at that moment.
 *
 * Roles covered, and why these ones:
 *   requisitions.requested_by  — the clinician line on the report
 *   requisitions.cancelled_by  — "cancelled by X" on the requisition screen
 *   results.performed_by       — "Performed by X" on the report
 *   results.validated_by       — the signatory line; the one that matters most
 *   results.printed_by         — new; there was a last_printed_at with nobody attached to it
 *
 * created_by, updated_by and unvalidated_by keep their foreign key without a
 * snapshot: they are operational, never appear on issued output, and the audit
 * trail already records them with an actor snapshot of its own.
 */
return new class extends Migration
{
    /** Roles to snapshot, by table. */
    private const ROLES = [
        'laboratory_requisitions' => ['requested_by', 'cancelled_by'],
        'laboratory_results' => ['performed_by', 'validated_by', 'printed_by'],
    ];

    public function up(): void
    {
        foreach (self::ROLES as $table => $roles) {
            Schema::table($table, function (Blueprint $blueprint) use ($roles): void {
                foreach ($roles as $role) {
                    $blueprint->foreignId("{$role}_staff_id")->nullable()
                        ->constrained('staff')->nullOnDelete();
                    $blueprint->string("{$role}_actor_name")->nullable();
                    $blueprint->string("{$role}_actor_title", 64)->nullable();
                    $blueprint->string("{$role}_actor_speciality", 64)->nullable();
                    // authenticated | system | pre_migration
                    $blueprint->string("{$role}_actor_provenance", 24)->nullable();
                }
            });
        }

        Schema::table('laboratory_results', function (Blueprint $table): void {
            // There was a last_printed_at with nobody attached to it.
            $table->foreignId('printed_by')->nullable()->after('last_printed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('laboratory_requisitions', function (Blueprint $table): void {
            $table->index('requested_by_staff_id');
        });

        Schema::table('laboratory_results', function (Blueprint $table): void {
            $table->index('validated_by_staff_id');
            $table->index('performed_by_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_requisitions', function (Blueprint $table): void {
            $table->dropIndex(['requested_by_staff_id']);
        });

        Schema::table('laboratory_results', function (Blueprint $table): void {
            $table->dropIndex(['validated_by_staff_id']);
            $table->dropIndex(['performed_by_staff_id']);
            $table->dropForeign(['printed_by']);
            $table->dropColumn('printed_by');
        });

        foreach (self::ROLES as $table => $roles) {
            Schema::table($table, function (Blueprint $blueprint) use ($roles): void {
                foreach ($roles as $role) {
                    $blueprint->dropForeign(["{$role}_staff_id"]);
                    $blueprint->dropColumn([
                        "{$role}_staff_id",
                        "{$role}_actor_name",
                        "{$role}_actor_title",
                        "{$role}_actor_speciality",
                        "{$role}_actor_provenance",
                    ]);
                }
            });
        }
    }
};
