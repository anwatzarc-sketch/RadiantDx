<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('module');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('module_order')->default(0);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['module', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
