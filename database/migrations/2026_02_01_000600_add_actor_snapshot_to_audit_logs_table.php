<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the audit trail with the professional identity of the actor.
 *
 * `user_id` and `user_name` are deliberately left as they are. `user_name` has
 * always been a snapshot of the actor's name at the moment of the event, so the
 * pattern already existed; these columns add the rest of the professional
 * identity beside it rather than renaming what is already in production and
 * relied on by 85 existing rows.
 *
 * Existing rows are marked `pre_migration`: they were written before staff
 * records existed, so their `user_name` is all the identity there is. That is
 * recorded honestly rather than being left to look like a resolution failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('actor_staff_id')->nullable()->after('user_name')
                ->constrained('staff')->nullOnDelete();
            $table->string('actor_title', 64)->nullable()->after('actor_staff_id');
            $table->string('actor_speciality', 64)->nullable()->after('actor_title');
            // authenticated | system | pre_migration
            $table->string('actor_provenance', 24)->nullable()->after('actor_speciality');

            $table->index('actor_staff_id');
        });

        DB::table('audit_logs')
            ->whereNull('actor_provenance')
            ->update(['actor_provenance' => 'pre_migration']);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_staff_id']);
            $table->dropIndex(['actor_staff_id']);
            $table->dropColumn([
                'actor_staff_id',
                'actor_title',
                'actor_speciality',
                'actor_provenance',
            ]);
        });
    }
};
