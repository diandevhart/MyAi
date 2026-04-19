<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_inventories_id')->nullable()->constrained('warehouse_inventories')->nullOnDelete();
            $table->foreignId('inventory_equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->foreignId('equipment_tracking_status_id')->constrained('equipment_tracking_statuses');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->foreignId('authority_user_id')->constrained('users');
            $table->unsignedBigInteger('last_location_id')->nullable();
            $table->unsignedBigInteger('next_warehouse_id')->nullable();
            $table->unsignedBigInteger('delivery_location_id')->nullable();
            $table->date('book_in_date')->nullable();
            $table->date('book_out_date')->nullable();
            $table->integer('in')->default(0);
            $table->integer('out')->default(0);
            $table->integer('claimed')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_trackings');
    }
};
