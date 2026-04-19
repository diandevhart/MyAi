<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_equipment_stock_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('inventory_equipment_id')->constrained('inventory_equipment')->cascadeOnDelete();
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'inventory_equipment_id'], 'wess_warehouse_equipment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_equipment_stock_settings');
    }
};
