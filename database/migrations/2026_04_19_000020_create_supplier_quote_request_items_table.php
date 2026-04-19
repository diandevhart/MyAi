<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->foreignId('inventory_equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->string('new_item_name')->nullable();
            $table->text('new_item_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_request_items');
    }
};
