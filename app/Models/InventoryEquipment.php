<?php

namespace App\Models;

use App\Traits\GeneratesPartNumbers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryEquipment extends Model
{
    use HasFactory, SoftDeletes, GeneratesPartNumbers;

    protected $table = 'inventory_equipment';

    protected $fillable = [
        'group_requirement_id',
        'name',
        'description',
        'part_number',
        'type',
        'unit_of_measure',
        'manufacturer',
        'model_number',
        'weight',
        'dimensions',
        'image_path',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'reorder_quantity',
        'lead_time_days',
        'cost_price',
        'is_serialized',
        'requires_inspection',
        'inspection_interval_days',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_serialized' => 'boolean',
            'requires_inspection' => 'boolean',
            'is_active' => 'boolean',
            'min_stock_level' => 'integer',
            'max_stock_level' => 'integer',
            'reorder_point' => 'integer',
            'reorder_quantity' => 'integer',
            'lead_time_days' => 'integer',
            'inspection_interval_days' => 'integer',
        ];
    }

    public function groupRequirement(): BelongsTo
    {
        return $this->belongsTo(GroupRequirement::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function inventoryKits(): BelongsToMany
    {
        return $this->belongsToMany(InventoryKit::class, 'equipment_kits');
    }

    public function warehouseEquipmentStockSettings(): HasMany
    {
        return $this->hasMany(WarehouseEquipmentStockSetting::class);
    }

    public function equipmentReqs(): HasMany
    {
        return $this->hasMany(EquipmentReq::class);
    }
}
