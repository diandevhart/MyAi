<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilesSupplierQuoteRequestPo extends Model
{
    use HasFactory;

    protected $table = 'files_supplier_quote_request_pos';

    protected $fillable = [
        'supplier_quote_request_id',
        'file_path',
        'file_name',
        'file_type',
        'uploaded_by',
    ];

    public function supplierQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
