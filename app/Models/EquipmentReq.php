<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentReq extends Model
{
    protected $fillable = [
        'inventory_equipment_id',
        'field_name',
        'field_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }

    public function warehouseInventoryFields(): HasMany
    {
        return $this->hasMany(WarehouseInventoryField::class);
    }
}
