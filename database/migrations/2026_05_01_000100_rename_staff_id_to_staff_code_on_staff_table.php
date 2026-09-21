<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the staff business identifier a name that is not also a foreign key.
 *
 * `staff.staff_id` held the printed identifier (STF-000042) while
 * `users.staff_id`, `staff_qualifications.staff_id` and every
 * `*_by_staff_id` column hold a foreign key to `staff.id`. One name, two
 * meanings, and the wrong reading produces code that looks right: a join on
 * `staff.staff_id = users.staff_id` compiles, runs, and matches nothing.
 *
 * The foreign keys keep the conventional name. The business identifier becomes
 * `staff_code`, which is what the rest of the schema already calls a
 * human-facing code (`departments.code`, `units.code`, `laboratory_tests.code`).
 *
 * The unique index is dropped and recreated rather than renamed: a column
 * rename leaves the old index name behind, MariaDB gained RENAME INDEX only in
 * 10.5.2, and this installation runs 10.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropUnique(['staff_id']);
        });

        Schema::table('staff', function (Blueprint $table): void {
            $table->renameColumn('staff_id', 'staff_code');
        });

        Schema::table('staff', function (Blueprint $table): void {
            $table->unique('staff_code');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropUnique(['staff_code']);
        });

        Schema::table('staff', function (Blueprint $table): void {
            $table->renameColumn('staff_code', 'staff_id');
        });

        Schema::table('staff', function (Blueprint $table): void {
            $table->unique('staff_id');
        });
    }
};
