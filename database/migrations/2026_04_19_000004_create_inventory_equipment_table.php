<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_requirement_id')->constrained('group_requirements')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('part_number')->unique();
            $table->enum('type', ['item', 'ppe', 'kit_component'])->default('item');
            $table->string('unit_of_measure')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('dimensions')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->integer('lead_time_days')->default(0);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('is_serialized')->default(true);
            $table->boolean('requires_inspection')->default(false);
            $table->integer('inspection_interval_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_equipment');
    }
};
