<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalRfqRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_rfq_request_id',
        'inventory_equipment_id',
        'quantity',
        'new_item_name',
        'new_item_description',
        'new_item_unit',
        'new_item_estimated_budget',
        'new_item_attachment_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'new_item_estimated_budget' => 'decimal:2',
        ];
    }

    protected function isNewItem(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->inventory_equipment_id),
        );
    }

    public function internalRfqRequest(): BelongsTo
    {
        return $this->belongsTo(InternalRfqRequest::class);
    }

    public function inventoryEquipment(): BelongsTo
    {
        return $this->belongsTo(InventoryEquipment::class);
    }
}
