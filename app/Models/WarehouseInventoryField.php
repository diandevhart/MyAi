<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventoryField extends Model
{
    protected $fillable = [
        'warehouse_inventory_id',
        'equipment_req_id',
        'value',
    ];

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }

    public function equipmentReq(): BelongsTo
    {
        return $this->belongsTo(EquipmentReq::class);
    }
}
