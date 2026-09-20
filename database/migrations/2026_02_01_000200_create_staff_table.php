<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The central staff record: who a person is professionally, independent of
 * whether they hold a system account.
 *
 * A staff record may exist with no user (someone who appears on reports but
 * never signs in); a user may not exist without one.
 *
 * `status` carries StaffStatus and is what gates system access. Staff are
 * never hard deleted once they have laboratory history — they move to
 * `inactive` or `former` so historical activity keeps resolving to a real
 * person. Soft deletes exist for records created in error only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();

            // Human readable, immutable, never reused: STF-000001
            $table->string('staff_id', 32)->unique();

            // --- Identity ---
            $table->string('full_name');
            // male | female | other | unknown
            $table->string('gender', 16)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path')->nullable();

            // --- Contact ---
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            // --- Professional ---
            $table->string('title', 64)->nullable();
            // Profession enum
            $table->string('profession', 48)->nullable();
            // Speciality / SubSpeciality enums
            $table->string('speciality', 64)->nullable();
            $table->string('sub_speciality', 64)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            // PositionType enum
            $table->string('position', 48)->nullable();
            $table->string('professional_license', 64)->nullable();
            $table->date('license_expiry')->nullable();

            // --- Employment ---
            $table->string('employee_id', 64)->nullable();
            // EmploymentType enum
            $table->string('employment_type', 32)->nullable();
            // StaffStatus enum: active | inactive | suspended | former
            $table->string('status', 24)->default('active');
            $table->date('joined_on')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('staff')->nullOnDelete();

            // Set when a record was created by the backfill or an import and
            // still needs a human to complete its professional details.
            $table->boolean('needs_review')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('full_name');
            $table->index('profession');
            $table->index('speciality');
            $table->index('employee_id');
            $table->index(['department_id', 'status']);
            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
