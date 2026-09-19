<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('password')
                ->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'role_id',
                'is_active',
                'must_change_password',
                'last_login_at',
                'last_login_ip',
                'deleted_at',
            ]);
        });
    }
};
