<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_reqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_equipment_id')->constrained('inventory_equipment')->cascadeOnDelete();
            $table->string('field_name');
            $table->enum('field_type', ['text', 'number', 'date', 'select'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_reqs');
    }
};
