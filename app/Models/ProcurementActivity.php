<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'internal_rfq_request_id',
        'supplier_quote_request_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function internalRfqRequest(): BelongsTo
    {
        return $this->belongsTo(InternalRfqRequest::class);
    }

    public function supplierQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class);
    }
}
