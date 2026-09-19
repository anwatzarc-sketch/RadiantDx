<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_test_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_test_id')
                ->constrained(table: 'laboratory_tests', indexName: 'lab_test_param_test_fk')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();

            // numeric | text | boolean | positive_negative | dropdown
            $table->string('data_type', 32)->default('numeric');

            $table->string('unit', 64)->nullable();
            $table->string('reference_range')->nullable();
            $table->decimal('reference_low', 18, 6)->nullable();
            $table->decimal('reference_high', 18, 6)->nullable();
            $table->decimal('critical_low', 18, 6)->nullable();
            $table->decimal('critical_high', 18, 6)->nullable();
            $table->unsignedTinyInteger('decimal_precision')->default(2);

            // Interpretation rule for non numeric parameters: records which outcome
            // should be flagged as abnormal on the report.
            $table->string('abnormal_when', 32)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['laboratory_test_id', 'code'], 'lab_test_param_code_unique');
            $table->index(['laboratory_test_id', 'display_order'], 'lab_test_param_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_test_parameters');
    }
};
