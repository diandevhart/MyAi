<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ccus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventory_id')->nullable()->constrained('warehouse_inventories')->nullOnDelete();
            $table->string('name');
            $table->string('container_number')->nullable();
            $table->string('container_type')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ccus');
    }
};
