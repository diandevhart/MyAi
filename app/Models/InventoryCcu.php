<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCcu extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'warehouse_inventory_id',
        'name',
        'container_number',
        'container_type',
        'warehouse_id',
        'rig_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'inventory_ccu_id');
    }
}
