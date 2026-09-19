<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue values offered by dropdown parameters. Kept relational rather
     * than serialised so options can be queried, ordered and retired safely.
     */
    public function up(): void
    {
        Schema::create('laboratory_test_parameter_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_test_parameter_id')
                ->constrained(table: 'laboratory_test_parameters', indexName: 'lab_param_option_param_fk')
                ->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->boolean('is_abnormal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['laboratory_test_parameter_id', 'value'], 'lab_param_option_unique');
            $table->index(['laboratory_test_parameter_id', 'display_order'], 'lab_param_option_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_test_parameter_options');
    }
};
