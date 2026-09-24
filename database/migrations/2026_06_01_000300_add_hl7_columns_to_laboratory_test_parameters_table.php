<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HL7 identity of a parameter: OBX-3 (LOINC) and OBX-2 (Table 0125 value
 * type: NM, CWE, TX, ...). data_type stays the application's own shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_test_parameters', function (Blueprint $table): void {
            $table->string('loinc_code', 16)->nullable()->after('code')->index('lab_test_param_loinc_idx');
            $table->string('hl7_value_type', 4)->nullable()->after('data_type');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_test_parameters', function (Blueprint $table): void {
            $table->dropIndex('lab_test_param_loinc_idx');
            $table->dropColumn(['loinc_code', 'hl7_value_type']);
        });
    }
};
