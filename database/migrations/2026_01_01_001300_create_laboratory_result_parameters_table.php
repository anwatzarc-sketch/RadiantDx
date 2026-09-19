<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per reportable value. Parameterised tests contribute one row per
     * configured parameter; single valued tests contribute exactly one row whose
     * catalogue reference is null because the test itself carries the metadata.
     * Every descriptive column is a snapshot taken at result creation time.
     */
    public function up(): void
    {
        Schema::create('laboratory_result_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_result_id')
                ->constrained(table: 'laboratory_results', indexName: 'lab_result_param_result_fk')
                ->cascadeOnDelete();

            $table->foreignId('laboratory_test_parameter_id')->nullable()
                ->constrained(table: 'laboratory_test_parameters', indexName: 'lab_result_param_catalog_fk')
                ->nullOnDelete();

            $table->string('parameter_name');
            $table->string('parameter_code');
            $table->string('data_type', 32)->default('numeric');
            $table->string('unit', 64)->nullable();
            $table->string('reference_range')->nullable();
            $table->decimal('reference_low', 18, 6)->nullable();
            $table->decimal('reference_high', 18, 6)->nullable();
            $table->decimal('critical_low', 18, 6)->nullable();
            $table->decimal('critical_high', 18, 6)->nullable();
            $table->unsignedTinyInteger('decimal_precision')->default(2);
            $table->string('abnormal_when', 32)->nullable();

            // The value as entered by the laboratory, kept verbatim. The numeric
            // column is a parsed copy used only for range comparison and sorting.
            $table->text('result_value')->nullable();
            $table->decimal('result_numeric', 18, 6)->nullable();

            // Value suggested by the reference range evaluation, and the value the
            // laboratory actually reports. The entered result is authoritative.
            $table->string('auto_interpretation', 32)->nullable();
            $table->string('interpretation', 32)->nullable();

            $table->text('comment')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['laboratory_result_id', 'display_order'], 'lab_result_param_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_result_parameters');
    }
};
