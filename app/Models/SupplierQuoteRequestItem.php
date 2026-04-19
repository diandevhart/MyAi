<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuoteRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_quote_request_id',
        'inventory_equipment_id',
        'quantity',
        'unit_price',
        'total_price',
        'lead_time_days',
        'notes',
        'new_item_name',
        'new_item_description',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function supplierQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class);
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }
}
