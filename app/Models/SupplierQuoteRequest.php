<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierQuoteRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'internal_rfq_request_id',
        'supplier_id',
        'rfq_number',
        'status',
        'sent_at',
        'due_date',
        'awarded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'due_date' => 'date',
            'awarded_at' => 'datetime',
        ];
    }

    public function internalRfqRequest(): BelongsTo
    {
        return $this->belongsTo(InternalRfqRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteRequestItem::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(FilesSupplierQuoteRequestPo::class);
    }
}
