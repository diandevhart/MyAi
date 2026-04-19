<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_equipment_id')->constrained('inventory_equipment')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->foreignId('inventory_status_id')->nullable()->constrained('inventory_statuses')->nullOnDelete();
            $table->string('status')->nullable();
            $table->string('storage_method')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->date('date_of_purchase')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('files_supplier_quote_request_po_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->foreign('inspected_by')->references('id')->on('users')->nullOnDelete();
            $table->date('inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->boolean('received')->default(false);
            $table->boolean('service_stock')->default(false);
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('inventory_kit_id')->nullable();
            $table->unsignedBigInteger('inventory_ccu_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventories');
    }
};
