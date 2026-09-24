<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference ranges stratified by sex and age: HL7 OM2-6 / OM2-7 (RFR), FHIR
 * ObservationDefinition.qualifiedInterval.
 *
 * A parameter may carry many of these. The one used for a result is chosen
 * when the result is opened (ReferenceRangeResolver) and copied onto the
 * result row, so later edits here never change an issued report.
 *
 * Age bounds are [min, max): min inclusive, max exclusive, each in its own
 * UCUM unit (d, mo, a). A null bound is unbounded.
 *
 * Every range starts as a placeholder: a textbook value nobody at this
 * laboratory has approved. The verified_by columns record who approved it and
 * as whom (the actor snapshot every lab table uses), which ISO 15189 requires.
 * A placeholder that has not been verified may also be inactive, and the
 * resolver never selects an inactive row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_reference_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_test_parameter_id')
                ->constrained(table: 'laboratory_test_parameters', indexName: 'lab_ref_range_param_fk')
                ->cascadeOnDelete();

            // any | M | F
            $table->string('sex', 8)->default('any');

            // A code from the age_category value set, descriptive only: the
            // numeric bounds below are what the resolver compares.
            $table->string('age_category', 32)->nullable();
            $table->decimal('age_min', 8, 2)->nullable();
            $table->string('age_min_unit', 2)->nullable();
            $table->decimal('age_max', 8, 2)->nullable();
            $table->string('age_max_unit', 2)->nullable();

            $table->decimal('reference_low', 18, 6)->nullable();
            $table->decimal('reference_high', 18, 6)->nullable();
            $table->decimal('critical_low', 18, 6)->nullable();
            $table->decimal('critical_high', 18, 6)->nullable();
            $table->string('reference_range_text')->nullable();

            // outside_range | above_high | below_low | never
            $table->string('abnormal_when', 32)->nullable();

            $table->boolean('is_placeholder')->default(true);
            $table->text('notes')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('verified_by_actor_name')->nullable();
            $table->string('verified_by_actor_title', 64)->nullable();
            $table->string('verified_by_actor_speciality', 64)->nullable();
            $table->string('verified_by_actor_provenance', 24)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['laboratory_test_parameter_id', 'sex', 'is_active'], 'lab_ref_range_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_reference_ranges');
    }
};
