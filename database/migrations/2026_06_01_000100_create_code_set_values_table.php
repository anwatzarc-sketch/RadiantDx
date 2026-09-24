<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coded value sets: the local code the application stores, beside its HL7
 * table entry and FHIR coding.
 *
 * One table for every set rather than one per set, because they share a shape
 * and most exist only to translate at an interface boundary. value_set is a
 * slug of the set's name ("age_category", "units_of_measure_ucum").
 *
 * A row whose set has no local code of its own (HL7 order-control codes, MFN
 * events) uses its HL7 code as the code, so (value_set, code) is always a
 * usable natural key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_set_values', function (Blueprint $table): void {
            $table->id();
            $table->string('value_set', 64)->index();
            $table->string('code', 64);
            $table->string('display');
            $table->string('hl7_code', 32)->nullable();
            $table->string('hl7_display')->nullable();
            $table->string('hl7_table', 64)->nullable();
            $table->string('fhir_code', 64)->nullable();
            $table->string('fhir_system')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['value_set', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_set_values');
    }
};
