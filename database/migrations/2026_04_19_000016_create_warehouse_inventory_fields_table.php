<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_inventory_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventory_id')->constrained('warehouse_inventories')->cascadeOnDelete();
            $table->foreignId('equipment_req_id')->constrained('equipment_reqs')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_inventory_id', 'equipment_req_id'], 'wif_inventory_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventory_fields');
    }
};
