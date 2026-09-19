<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per test that the laboratory has to perform. A requested panel is
     * expanded into one row per member test; panel_id and the snapshotted panel
     * name keep the original request visible on screen and on the report.
     */
    public function up(): void
    {
        Schema::create('laboratory_requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_requisition_id')
                ->constrained(table: 'laboratory_requisitions', indexName: 'lab_req_item_req_fk')
                ->cascadeOnDelete();

            // test | panel
            $table->string('source_type', 16)->default('test');

            $table->foreignId('laboratory_test_id')
                ->constrained(table: 'laboratory_tests', indexName: 'lab_req_item_test_fk')
                ->restrictOnDelete();
            $table->foreignId('laboratory_panel_id')->nullable()
                ->constrained(table: 'laboratory_panels', indexName: 'lab_req_item_panel_fk')
                ->restrictOnDelete();

            $table->string('test_name');
            $table->string('test_code');
            $table->string('specimen_type')->nullable();
            $table->string('panel_name')->nullable();
            $table->string('panel_code')->nullable();

            // pending | collected | processing | resulted | validated | cancelled
            $table->string('status', 24)->default('pending');

            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['laboratory_requisition_id', 'display_order'], 'lab_req_item_order_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_requisition_items');
    }
};
