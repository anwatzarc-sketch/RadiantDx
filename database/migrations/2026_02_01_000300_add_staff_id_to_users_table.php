<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 3 in linking users to staff records.
 *
 * Nullable to begin with, so this migration can run against a database whose
 * users have no staff records yet. The backfill follows, and only then is the
 * column made required.
 *
 * restrictOnDelete rather than cascade: deleting a staff record that an account
 * depends on should fail loudly, not silently remove someone's access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('staff_id')->nullable()->after('id')
                ->constrained('staff')->restrictOnDelete();

            // One account per staff record.
            $table->unique('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['staff_id']);
            $table->dropUnique(['staff_id']);
            $table->dropColumn('staff_id');
        });
    }
};
