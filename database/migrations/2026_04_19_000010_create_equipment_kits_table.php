<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_kit_id')->constrained('inventory_kits')->cascadeOnDelete();
            $table->foreignId('inventory_equipment_id')->constrained('inventory_equipment')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamp('created_at')->nullable();

            $table->unique(['inventory_kit_id', 'inventory_equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_kits');
    }
};
