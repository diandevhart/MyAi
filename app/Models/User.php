<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function managedWarehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_managed_warehouses');
    }

    public function managesWarehouse(int $warehouseId): bool
    {
        if ($this->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }
        if ($this->managedWarehouses()->doesntExist()) {
            return true;
        }
        return $this->managedWarehouses()->where('warehouse_id', $warehouseId)->exists();
    }
}
