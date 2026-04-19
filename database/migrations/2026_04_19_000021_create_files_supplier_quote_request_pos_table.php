<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files_supplier_quote_request_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->enum('file_type', ['quote', 'po', 'invoice', 'attachment']);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files_supplier_quote_request_pos');
    }
};
