<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HL7 identity of a test, for interfaces.
 *
 *   loinc_code        OBR-4 universal service identifier
 *   hl7_section_code  Table 0074 diagnostic service section (HM, CH, ...)
 *   hl7_nature_code   Table 0174 nature of test (A single, F functional
 *                     group, C calculated, P panel)
 *
 * The local category and result_type columns stay authoritative for the
 * application; these only record how the test is described to the outside.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->string('loinc_code', 16)->nullable()->after('code')->index();
            $table->string('hl7_section_code', 8)->nullable()->after('category');
            $table->char('hl7_nature_code', 1)->nullable()->after('result_type');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_tests', function (Blueprint $table): void {
            $table->dropIndex(['loinc_code']);
            $table->dropColumn(['loinc_code', 'hl7_section_code', 'hl7_nature_code']);
        });
    }
};
