<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which reference range a result was judged against, and how sure the
 * selection could be.
 *
 *   reference_range_basis  stratified  a sex/age row matched
 *                          default     the parameter's adult default was used
 *                          none        nothing applied; no auto flag
 *   patient_age_basis      dob | age_years | unknown
 *
 * The range values themselves are already snapshotted on this row. The FK is
 * provenance only, and is nulled rather than cascaded if the range is later
 * deleted: the snapshot has to outlive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_result_parameters', function (Blueprint $table): void {
            $table->foreignId('laboratory_reference_range_id')->nullable()->after('laboratory_test_parameter_id')
                ->constrained(table: 'laboratory_reference_ranges', indexName: 'lab_result_param_range_fk')
                ->nullOnDelete();
            $table->string('reference_range_basis', 24)->nullable()->after('abnormal_when');
            $table->string('patient_age_basis', 16)->nullable()->after('reference_range_basis');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_result_parameters', function (Blueprint $table): void {
            // The default constraint name would exceed MySQL's 64 characters,
            // so it is named, and named keys are dropped by name there. SQLite
            // rebuilds the table instead and only accepts the column form.
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite'
                ? ['laboratory_reference_range_id']
                : 'lab_result_param_range_fk');
            $table->dropColumn(['laboratory_reference_range_id', 'reference_range_basis', 'patient_age_basis']);
        });
    }
};
