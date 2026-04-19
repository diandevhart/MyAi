<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_rfq_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_rfq_request_id')->constrained('internal_rfq_requests')->cascadeOnDelete();
            $table->foreignId('inventory_equipment_id')->nullable()->constrained('inventory_equipment')->nullOnDelete();
            $table->integer('quantity');
            $table->string('new_item_name')->nullable();
            $table->text('new_item_description')->nullable();
            $table->string('new_item_unit')->nullable();
            $table->decimal('new_item_estimated_budget', 10, 2)->nullable();
            $table->string('new_item_attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_rfq_request_items');
    }
};
