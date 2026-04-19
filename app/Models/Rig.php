<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rig extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rig_name',
        'rig_code',
        'rig_type',
        'location',
        'is_active',
        'warehouse_id',
        'contact_person',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }
}
