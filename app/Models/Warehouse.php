<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'province',
        'country',
        'capacity',
        'warehouse_type',
        'is_active',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function equipmentTrackings(): HasMany
    {
        return $this->hasMany(EquipmentTracking::class);
    }

    public function warehouseEquipmentStockSettings(): HasMany
    {
        return $this->hasMany(WarehouseEquipmentStockSetting::class);
    }
}
