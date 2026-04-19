<?php

namespace Database\Seeders;

use App\Models\InventoryStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventoryStatusSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $statuses = [
            ['code' => 'A', 'name' => 'New', 'sort_order' => 1],
            ['code' => 'B', 'name' => 'Good', 'sort_order' => 2],
            ['code' => 'C', 'name' => 'Fair', 'sort_order' => 3],
            ['code' => 'D', 'name' => 'Poor', 'sort_order' => 4],
            ['code' => 'E', 'name' => 'Quarantined', 'sort_order' => 5],
            ['code' => 'X', 'name' => 'Lost/Stolen', 'sort_order' => 6],
        ];

        foreach ($statuses as $status) {
            InventoryStatus::updateOrCreate(
                ['code' => $status['code']],
                $status,
            );
        }
    }
}
