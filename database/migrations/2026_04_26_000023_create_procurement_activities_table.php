<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('internal_rfq_request_id')->nullable()->constrained('internal_rfq_requests')->nullOnDelete();
            $table->foreignId('supplier_quote_request_id')->nullable()->constrained('supplier_quote_requests')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_activities');
    }
};
