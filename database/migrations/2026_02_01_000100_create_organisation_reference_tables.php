<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments and units.
 *
 * These are reference entities rather than enums: every site that deploys this
 * application has its own departments, and a facility must be able to add one
 * without a code change. `laboratory_requisitions.requesting_department` is
 * free text today; these tables are what it will resolve against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('name');
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // A unit code is unique inside its department, not globally.
            $table->unique(['department_id', 'code']);
            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('departments');
    }
};
