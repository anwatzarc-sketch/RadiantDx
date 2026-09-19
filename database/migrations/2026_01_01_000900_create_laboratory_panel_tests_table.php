<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_panel_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_panel_id')
                ->constrained(table: 'laboratory_panels', indexName: 'lab_panel_test_panel_fk')
                ->cascadeOnDelete();
            $table->foreignId('laboratory_test_id')
                ->constrained(table: 'laboratory_tests', indexName: 'lab_panel_test_test_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['laboratory_panel_id', 'laboratory_test_id'], 'lab_panel_test_unique');
            $table->index(['laboratory_panel_id', 'display_order'], 'lab_panel_test_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratory_panel_tests');
    }
};
