<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LOINC panel code, where one exists (OBR-4 for an ordered battery).
 *
 * Nullable because most local panels have no LOINC equivalent: a panel whose
 * membership differs from the LOINC definition must not claim its code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_panels', function (Blueprint $table): void {
            $table->string('loinc_code', 16)->nullable()->after('code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_panels', function (Blueprint $table): void {
            $table->dropIndex(['loinc_code']);
            $table->dropColumn('loinc_code');
        });
    }
};
