<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_results', function (Blueprint $table): void {
            $table->id();
            $table->string('result_number')->unique();

            $table->foreignId('laboratory_requisition_id')
                ->constrained(table: 'laboratory_requisitions', indexName: 'lab_result_req_fk')
                ->cascadeOnDelete();

            $table->foreignId('laboratory_requisition_item_id')
                ->constrained(table: 'laboratory_requisition_items', indexName: 'lab_result_item_fk')
                ->cascadeOnDelete();

            $table->foreignId('laboratory_test_id')
                ->constrained(table: 'laboratory_tests', indexName: 'lab_result_test_fk')
                ->restrictOnDelete();

            // Snapshot of the catalogue at the moment the result was produced so
            // historical reports stay faithful when the catalogue later changes.
            $table->string('test_name');
            $table->string('test_code');
            $table->string('specimen_type')->nullable();
            $table->string('panel_name')->nullable();

            // pending | in_progress | completed
            $table->string('status', 24)->default('pending');

            // pending_validation | validated
            $table->string('validation_status', 24)->default('pending_validation');

            $table->text('interpretation')->nullable();
            $table->text('comments')->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->nullable();

            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->foreignId('unvalidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unvalidated_at')->nullable();
            $table->string('unvalidation_reason')->nullable();

            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('last_printed_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('laboratory_requisition_item_id', 'lab_result_item_unique');
            $table->index(['status', 'validation_status'], 'lab_result_status_idx');
            $table->index('laboratory_requisition_id', 'lab_result_req_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_results');
    }
};
