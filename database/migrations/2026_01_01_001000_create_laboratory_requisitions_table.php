<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->string('requisition_number')->unique();

            $table->string('patient_identifier');
            $table->string('patient_name');
            $table->string('patient_gender', 16)->nullable();
            $table->date('patient_date_of_birth')->nullable();
            $table->unsignedSmallInteger('patient_age_years')->nullable();

            $table->date('requested_date');
            $table->string('requesting_clinician')->nullable();
            $table->string('requesting_department')->nullable();

            // routine | urgent | stat
            $table->string('priority', 16)->default('routine');

            $table->text('clinical_indication')->nullable();
            $table->text('clinical_notes')->nullable();

            // draft | submitted | collected | processing | completed | cancelled
            $table->string('status', 24)->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'requested_date']);
            $table->index('priority');
            $table->index('patient_identifier');
            $table->index('patient_name');
            $table->index('requested_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_requisitions');
    }
};
