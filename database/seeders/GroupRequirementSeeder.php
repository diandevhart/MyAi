<?php

namespace Database\Seeders;

use App\Models\GroupRequirement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupRequirementSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $root = GroupRequirement::create([
            'name' => 'Equipment Catalogue',
            'description' => 'Root node for the equipment catalogue hierarchy',
            'parent_id' => null,
            'type' => 'group',
            'level' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        GroupRequirement::create([
            'name' => 'New Purchases',
            'description' => 'Folder for new purchase items',
            'parent_id' => $root->id,
            'type' => 'group',
            'level' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }
}
