<?php

namespace App\Traits;

use App\Models\GroupRequirement;
use App\Models\InventoryEquipment;

trait GeneratesPartNumbers
{
    protected function generatePartNumber(?int $groupRequirementId = null): string
    {
        $prefix = 'GEN';

        if ($groupRequirementId) {
            $group = GroupRequirement::find($groupRequirementId);

            if ($group) {
                // Strip non-alpha characters and take first 3 uppercase letters
                $clean = preg_replace('/[^a-zA-Z]/', '', $group->name);
                $clean = strtoupper($clean);

                if (strlen($clean) < 3) {
                    $clean = str_pad($clean, 3, 'X');
                }

                $prefix = substr($clean, 0, 3);
            }
        }

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastNumber = InventoryEquipment::where('part_number', 'LIKE', $prefix . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING(part_number FROM '\\d+$') AS INTEGER)) as max_num")
                ->value('max_num');

            $nextNumber = ($lastNumber ?? 0) + 1;
            $partNumber = $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Check uniqueness before returning
            if (!InventoryEquipment::where('part_number', $partNumber)->exists()) {
                return $partNumber;
            }
        }

        // Fallback: append timestamp to guarantee uniqueness
        return $prefix . '-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
    }
}
