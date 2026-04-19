<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseInventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inventory_equipment_id',
        'warehouse_id',
        'rig_id',
        'serial_number',
        'inventory_status_id',
        'status',
        'storage_method',
        'purchase_order_number',
        'date_of_purchase',
        'supplier_id',
        'files_supplier_quote_request_po_id',
        'issued_at',
        'returned_at',
        'inspected_by',
        'inspection_date',
        'next_inspection_date',
        'received',
        'service_stock',
        'task_id',
        'inventory_kit_id',
        'inventory_ccu_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_purchase' => 'date',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
            'inspection_date' => 'date',
            'next_inspection_date' => 'date',
            'received' => 'boolean',
            'service_stock' => 'boolean',
        ];
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Rig::class);
    }

    public function inventoryStatus(): BelongsTo
    {
        return $this->belongsTo(InventoryStatus::class);
    }

    public function inspectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function inventoryKit(): BelongsTo
    {
        return $this->belongsTo(InventoryKit::class);
    }

    public function inventoryCcu(): BelongsTo
    {
        return $this->belongsTo(InventoryCcu::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function equipmentTrackings(): HasMany
    {
        return $this->hasMany(EquipmentTracking::class, 'warehouse_inventories_id');
    }

    public function warehouseInventoryFields(): HasMany
    {
        return $this->hasMany(WarehouseInventoryField::class);
    }

    public function filesSupplierQuoteRequestPo(): BelongsTo
    {
        return $this->belongsTo(FilesSupplierQuoteRequestPo::class, 'files_supplier_quote_request_po_id');
    }
}
