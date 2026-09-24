<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HL7 mappings for the organisation tables.
 *
 *   departments.hl7_service_code  Table 0069 hospital service (PV1-10)
 *   units.hl7_location_type       Table 0260 location type (PL.6)
 *
 * Nullable: several local services have no HL7 0069 equivalent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->string('hl7_service_code', 8)->nullable()->after('code');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->char('hl7_location_type', 1)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('hl7_location_type');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('hl7_service_code');
        });
    }
};
