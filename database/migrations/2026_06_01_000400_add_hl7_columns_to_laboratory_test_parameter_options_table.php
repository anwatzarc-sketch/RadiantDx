<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a coded answer travels in HL7: the SNOMED CT code for OBX-5, and the
 * Table 0078 flag for OBX-8 (POS, NEG, DET, ND, N, A).
 *
 * The printed report keeps deriving its flag from is_abnormal, so it prints A
 * for an abnormal option; hl7_flag is kept for interface export, where the
 * more specific POS / DET is what a receiving system expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_test_parameter_options', function (Blueprint $table): void {
            $table->string('snomed_code', 32)->nullable()->after('label');
            $table->string('hl7_flag', 4)->nullable()->after('is_abnormal');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_test_parameter_options', function (Blueprint $table): void {
            $table->dropColumn(['snomed_code', 'hl7_flag']);
        });
    }
};
