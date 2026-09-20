<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of 3: make the staff link required.
 *
 * The verification below is the point of this migration. If any account is
 * still unlinked, it stops rather than proceeding — an account with no staff
 * record cannot resolve a workflow actor, and discovering that during a result
 * validation is far worse than failing a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unresolved = DB::table('users')->whereNull('staff_id')->count();

        if ($unresolved > 0) {
            $emails = DB::table('users')
                ->whereNull('staff_id')
                ->limit(10)
                ->pluck('email')
                ->implode(', ');

            throw new RuntimeException(
                "Cannot require users.staff_id: {$unresolved} account(s) have no staff record ({$emails}). "
                .'Run the backfill migration first, or link these accounts by hand.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('staff_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('staff_id')->nullable()->change();
        });
    }
};
