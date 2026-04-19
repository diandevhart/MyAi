<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->foreign('files_supplier_quote_request_po_id')
                ->references('id')
                ->on('files_supplier_quote_request_pos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->dropForeign(['files_supplier_quote_request_po_id']);
        });
    }
};
