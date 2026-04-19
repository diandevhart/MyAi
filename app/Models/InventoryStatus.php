<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStatus extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
