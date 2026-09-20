<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic and professional qualifications held by a member of staff.
 *
 * A separate table because they are repeatable and open ended: a physician may
 * hold a medical degree, a speciality certification and two fellowships, and
 * flattening those into columns on the staff record would cap the number
 * arbitrarily.
 *
 * Cascades on delete: a qualification has no meaning without the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            // QualificationType enum
            $table->string('type', 48);
            $table->string('institution');
            $table->string('field')->nullable();
            $table->unsignedSmallInteger('awarded_year')->nullable();
            $table->string('reference', 128)->nullable();

            $table->timestamps();

            $table->index(['staff_id', 'awarded_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_qualifications');
    }
};
