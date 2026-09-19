<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('specimen_type')->nullable();

            // A test either produces a single value of its own, or a set of
            // individually configured parameters.
            $table->string('result_type', 32)->default('single');

            // Only meaningful for single valued tests; parameterised tests carry
            // this information on each parameter row instead.
            $table->string('unit', 64)->nullable();
            $table->string('reference_range')->nullable();
            $table->decimal('reference_low', 18, 6)->nullable();
            $table->decimal('reference_high', 18, 6)->nullable();
            $table->unsignedTinyInteger('decimal_precision')->default(2);

            $table->unsignedSmallInteger('turnaround_time_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_tests');
    }
};
