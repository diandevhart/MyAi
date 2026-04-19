<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_rfq_request_id')->constrained('internal_rfq_requests')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('rfq_number')->unique();
            $table->enum('status', ['draft', 'sent', 'quoted', 'awarded', 'cancelled'])->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_requests');
    }
};
