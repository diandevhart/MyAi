<?php

namespace Database\Seeders;

use App\Models\EquipmentTrackingStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EquipmentTrackingStatusSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Registered', 'sort_order' => 1],
            ['id' => 2, 'name' => 'Reserved', 'sort_order' => 2],
            ['id' => 3, 'name' => 'In Transit', 'sort_order' => 3],
            ['id' => 4, 'name' => 'In Use', 'sort_order' => 4],
            ['id' => 5, 'name' => 'In Quarantine', 'sort_order' => 5],
            ['id' => 6, 'name' => 'Stored/Available', 'sort_order' => 6],
            ['id' => 7, 'name' => 'Destroyed', 'sort_order' => 7],
            ['id' => 8, 'name' => 'Pending Order', 'sort_order' => 8],
            ['id' => 9, 'name' => 'Out for Delivery', 'sort_order' => 9],
            ['id' => 10, 'name' => 'Delivered', 'sort_order' => 10],
            ['id' => 11, 'name' => 'Ordered', 'sort_order' => 11],
            ['id' => 12, 'name' => 'Missing/Lost', 'sort_order' => 12],
        ];

        foreach ($statuses as $status) {
            EquipmentTrackingStatus::updateOrCreate(
                ['id' => $status['id']],
                $status,
            );
        }
    }
}
