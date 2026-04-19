<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentTracking extends Model
{
    protected $fillable = [
        'warehouse_inventories_id',
        'inventory_equipment_id',
        'equipment_tracking_status_id',
        'warehouse_id',
        'rig_id',
        'authority_user_id',
        'last_location_id',
        'next_warehouse_id',
        'delivery_location_id',
        'book_in_date',
        'book_out_date',
        'in',
        'out',
        'claimed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'book_in_date' => 'date',
            'book_out_date' => 'date',
            'in' => 'integer',
            'out' => 'integer',
            'claimed' => 'integer',
        ];
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventories_id');
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }

    public function equipmentTrackingStatus(): BelongsTo
    {
        return $this->belongsTo(EquipmentTrackingStatus::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function authorityUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authority_user_id');
    }
}
