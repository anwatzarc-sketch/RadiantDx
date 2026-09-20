<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physician profile fields, added to the staff record rather than to a second
 * entity.
 *
 * A physician is not a different kind of person from a member of staff, and
 * giving them their own identity table would mean two records to keep in step
 * and two answers to "who validated this result". Staff already carries name,
 * title, speciality, sub-speciality, department and licence number; this adds
 * what is specific to practising under a licence.
 *
 * `license_status` and `registration_status` are stored, not derived on read.
 * Suspended and Revoked are asserted by a person and must survive the nightly
 * expiry recalculation — see LicenseStatus::isManuallyAsserted().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            // --- Licensing ---
            $table->string('license_authority', 128)->nullable()->after('professional_license');
            $table->date('license_issued_on')->nullable()->after('license_authority');
            // LicenseStatus enum
            $table->string('license_status', 24)->default('not_provided')->after('license_expiry');

            // --- Registration with a professional body ---
            $table->string('registration_number', 64)->nullable()->after('license_status');
            $table->string('registration_authority', 128)->nullable()->after('registration_number');
            // RegistrationStatus enum
            $table->string('registration_status', 24)->default('not_registered')->after('registration_authority');

            // --- Practice ---
            // PhysicianPracticeStatus enum
            $table->string('practice_status', 24)->nullable()->after('registration_status');
            $table->string('professional_phone', 32)->nullable()->after('practice_status');
            $table->string('professional_email')->nullable()->after('professional_phone');
            $table->text('professional_bio')->nullable()->after('professional_email');

            $table->index('license_status');
            $table->index('practice_status');
            $table->index('license_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropIndex(['license_status']);
            $table->dropIndex(['practice_status']);
            $table->dropIndex(['license_expiry']);
            $table->dropColumn([
                'license_authority', 'license_issued_on', 'license_status',
                'registration_number', 'registration_authority', 'registration_status',
                'practice_status', 'professional_phone', 'professional_email', 'professional_bio',
            ]);
        });
    }
};
