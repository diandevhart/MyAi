<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'rfq.manage',
            'rfq.approve',
            'rfq.award',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $procurementManager = Role::findOrCreate('Procurement Manager', 'web');
        $procurementManager->syncPermissions($permissions);

        $admin = Role::findOrCreate('Admin', 'web');
        $admin->givePermissionTo($permissions);

        $user = User::first();
        if ($user) {
            $user->assignRole('Procurement Manager');
        }
    }
}
