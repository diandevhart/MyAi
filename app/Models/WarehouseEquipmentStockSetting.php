<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseEquipmentStockSetting extends Model
{
    protected $fillable = [
        'warehouse_id',
        'inventory_equipment_id',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'reorder_quantity',
    ];

    protected function casts(): array
    {
        return [
            'min_stock_level' => 'integer',
            'max_stock_level' => 'integer',
            'reorder_point' => 'integer',
            'reorder_quantity' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }
}
