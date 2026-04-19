<?php

namespace Database\Seeders;

use App\Models\EquipmentTracking;
use App\Models\GroupRequirement;
use App\Models\InternalRfqRequest;
use App\Models\InternalRfqRequestItem;
use App\Models\InventoryEquipment;
use App\Models\InventoryKit;
use App\Models\Rig;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->error('No user found. Run the default seeder first.');
            return;
        }
        $userId = $user->id;

        DB::transaction(function () use ($userId) {

            // ────────────────────────────────────────────────
            // 1. Warehouses
            // ────────────────────────────────────────────────
            $cpt = Warehouse::firstOrCreate(
                ['code' => 'WH-CPT'],
                ['name' => 'Cape Town Main', 'warehouse_type' => 'warehouse', 'city' => 'Cape Town', 'province' => 'Western Cape', 'country' => 'South Africa', 'capacity' => 5000, 'is_active' => true]
            );

            $sal = Warehouse::firstOrCreate(
                ['code' => 'WH-SAL'],
                ['name' => 'Saldanha Rig Store', 'warehouse_type' => 'rig_store', 'city' => 'Saldanha', 'province' => 'Western Cape', 'country' => 'South Africa', 'capacity' => 1000, 'is_active' => true]
            );

            $dur = Warehouse::firstOrCreate(
                ['code' => 'WH-DUR'],
                ['name' => 'Durban Container Yard', 'warehouse_type' => 'container_yard', 'city' => 'Durban', 'province' => 'KwaZulu-Natal', 'country' => 'South Africa', 'capacity' => 3000, 'is_active' => true]
            );

            // ────────────────────────────────────────────────
            // 2. Rigs
            // ────────────────────────────────────────────────
            $rigDsv = Rig::firstOrCreate(
                ['rig_code' => 'RIG-DSV'],
                ['rig_name' => 'Deepsea Stavanger', 'rig_type' => 'rig', 'location' => 'Offshore Mossel Bay', 'is_active' => true, 'warehouse_id' => $sal->id]
            );

            Rig::firstOrCreate(
                ['rig_code' => 'VSL-PAC'],
                ['rig_name' => 'MV Pacific Explorer', 'rig_type' => 'vessel', 'location' => 'Port Elizabeth', 'is_active' => true, 'warehouse_id' => null]
            );

            // ────────────────────────────────────────────────
            // 3. Suppliers
            // ────────────────────────────────────────────────
            $supplierPPE = Supplier::firstOrCreate(
                ['email' => 'james@safetyfirst.co.za'],
                ['name' => 'SafetyFirst PPE Supplies', 'contact_person' => 'James Mbeki', 'phone' => '+27 21 555 0101', 'city' => 'Cape Town', 'country' => 'South Africa', 'payment_terms' => '30 days net', 'rating' => 4.50, 'is_active' => true]
            );

            $supplierTools = Supplier::firstOrCreate(
                ['email' => 'sarah@industrialtools.co.za'],
                ['name' => 'Industrial Tools SA', 'contact_person' => 'Sarah van der Merwe', 'phone' => '+27 11 555 0202', 'city' => 'Johannesburg', 'country' => 'South Africa', 'payment_terms' => '14 days net', 'rating' => 3.80, 'is_active' => true]
            );

            $supplierMarine = Supplier::firstOrCreate(
                ['email' => 'ahmed@marineequip.com'],
                ['name' => 'Marine Equipment International', 'contact_person' => 'Ahmed Hassan', 'phone' => '+971 4 555 0303', 'city' => 'Dubai', 'country' => 'UAE', 'payment_terms' => '45 days net', 'rating' => 4.20, 'is_active' => true]
            );

            // ────────────────────────────────────────────────
            // 4. Group Requirements (catalogue tree)
            // ────────────────────────────────────────────────
            $root = GroupRequirement::where('name', 'Equipment Catalogue')->whereNull('parent_id')->first();
            if (!$root) {
                $root = GroupRequirement::create([
                    'name' => 'Equipment Catalogue',
                    'parent_id' => null,
                    'type' => 'group',
                    'level' => 0,
                    'sort_order' => 0,
                    'is_active' => true,
                ]);
            }

            // --- PPE ---
            $ppe = GroupRequirement::firstOrCreate(['name' => 'PPE', 'parent_id' => $root->id], ['type' => 'group', 'level' => 1, 'sort_order' => 1, 'is_active' => true]);

            $headProt = GroupRequirement::firstOrCreate(['name' => 'Head Protection', 'parent_id' => $ppe->id], ['type' => 'group', 'level' => 2, 'sort_order' => 0, 'is_active' => true]);
            $grHardHat = GroupRequirement::firstOrCreate(['name' => 'Hard Hat', 'parent_id' => $headProt->id], ['type' => 'ppe', 'level' => 3, 'sort_order' => 0, 'is_active' => true]);
            $grWeldHelmet = GroupRequirement::firstOrCreate(['name' => 'Welding Helmet', 'parent_id' => $headProt->id], ['type' => 'ppe', 'level' => 3, 'sort_order' => 1, 'is_active' => true]);

            $handProt = GroupRequirement::firstOrCreate(['name' => 'Hand Protection', 'parent_id' => $ppe->id], ['type' => 'group', 'level' => 2, 'sort_order' => 1, 'is_active' => true]);
            $grLeatherGloves = GroupRequirement::firstOrCreate(['name' => 'Leather Gloves', 'parent_id' => $handProt->id], ['type' => 'ppe', 'level' => 3, 'sort_order' => 0, 'is_active' => true]);
            $grNitrileGloves = GroupRequirement::firstOrCreate(['name' => 'Nitrile Gloves', 'parent_id' => $handProt->id], ['type' => 'ppe', 'level' => 3, 'sort_order' => 1, 'is_active' => true]);

            $eyeProt = GroupRequirement::firstOrCreate(['name' => 'Eye Protection', 'parent_id' => $ppe->id], ['type' => 'group', 'level' => 2, 'sort_order' => 2, 'is_active' => true]);
            $grGoggles = GroupRequirement::firstOrCreate(['name' => 'Safety Goggles', 'parent_id' => $eyeProt->id], ['type' => 'ppe', 'level' => 3, 'sort_order' => 0, 'is_active' => true]);

            // --- NDT Equipment ---
            $ndt = GroupRequirement::firstOrCreate(['name' => 'NDT Equipment', 'parent_id' => $root->id], ['type' => 'group', 'level' => 1, 'sort_order' => 2, 'is_active' => true]);

            $ut = GroupRequirement::firstOrCreate(['name' => 'Ultrasonic Testing', 'parent_id' => $ndt->id], ['type' => 'group', 'level' => 2, 'sort_order' => 0, 'is_active' => true]);
            $grUtProbe = GroupRequirement::firstOrCreate(['name' => 'UT Probe', 'parent_id' => $ut->id], ['type' => 'item', 'level' => 3, 'sort_order' => 0, 'is_active' => true]);
            $grUtDetector = GroupRequirement::firstOrCreate(['name' => 'UT Flaw Detector', 'parent_id' => $ut->id], ['type' => 'item', 'level' => 3, 'sort_order' => 1, 'is_active' => true]);

            $eddy = GroupRequirement::firstOrCreate(['name' => 'Eddy Current', 'parent_id' => $ndt->id], ['type' => 'group', 'level' => 2, 'sort_order' => 1, 'is_active' => true]);
            $grNortec = GroupRequirement::firstOrCreate(['name' => 'Nortec Scanner', 'parent_id' => $eddy->id], ['type' => 'item', 'level' => 3, 'sort_order' => 0, 'is_active' => true]);

            // --- Hand Tools ---
            $tools = GroupRequirement::firstOrCreate(['name' => 'Hand Tools', 'parent_id' => $root->id], ['type' => 'group', 'level' => 1, 'sort_order' => 3, 'is_active' => true]);
            $grTorque = GroupRequirement::firstOrCreate(['name' => 'Torque Wrench', 'parent_id' => $tools->id], ['type' => 'item', 'level' => 2, 'sort_order' => 0, 'is_active' => true]);
            $grPipeCutter = GroupRequirement::firstOrCreate(['name' => 'Pipe Cutter', 'parent_id' => $tools->id], ['type' => 'item', 'level' => 2, 'sort_order' => 1, 'is_active' => true]);
            $grCrimper = GroupRequirement::firstOrCreate(['name' => 'Hydraulic Crimper', 'parent_id' => $tools->id], ['type' => 'item', 'level' => 2, 'sort_order' => 2, 'is_active' => true]);

            // --- Lifting Equipment ---
            $lifting = GroupRequirement::firstOrCreate(['name' => 'Lifting Equipment', 'parent_id' => $root->id], ['type' => 'group', 'level' => 1, 'sort_order' => 4, 'is_active' => true]);
            $grChainSling = GroupRequirement::firstOrCreate(['name' => 'Chain Sling 2T', 'parent_id' => $lifting->id], ['type' => 'item', 'level' => 2, 'sort_order' => 0, 'is_active' => true]);
            $grShackle = GroupRequirement::firstOrCreate(['name' => 'Shackle 5T', 'parent_id' => $lifting->id], ['type' => 'item', 'level' => 2, 'sort_order' => 1, 'is_active' => true]);

            // --- Kits ---
            $kitsGroup = GroupRequirement::firstOrCreate(['name' => 'Kits', 'parent_id' => $root->id], ['type' => 'group', 'level' => 1, 'sort_order' => 5, 'is_active' => true]);
            $grWeldKit = GroupRequirement::firstOrCreate(['name' => 'Welding Kit', 'parent_id' => $kitsGroup->id], ['type' => 'kit', 'level' => 2, 'sort_order' => 0, 'is_active' => true]);
            $grNdtKit = GroupRequirement::firstOrCreate(['name' => 'NDT Inspection Kit', 'parent_id' => $kitsGroup->id], ['type' => 'kit', 'level' => 2, 'sort_order' => 1, 'is_active' => true]);

            // ────────────────────────────────────────────────
            // 5. Inventory Equipment (catalogue entries)
            // ────────────────────────────────────────────────
            // Skip inventory data if already seeded
            if (WarehouseInventory::count() > 0) {
                $this->command->info('WarehouseSeeder skipped inventory — data already exists.');
                return;
            }

            $equipDefs = [
                ['gr' => $grHardHat, 'name' => 'Hard Hat - Standard White', 'pn' => 'HEA-0001', 'type' => 'ppe', 'uom' => 'each', 'mfr' => 'MSA Safety', 'serial' => false, 'inspect' => true, 'inspDays' => 365, 'min' => 20, 'max' => 100, 'reorder' => 25, 'reorderQty' => 50, 'cost' => 185.00],
                ['gr' => $grWeldHelmet, 'name' => 'Welding Helmet - Auto Darkening', 'pn' => 'HEA-0002', 'type' => 'ppe', 'uom' => 'each', 'mfr' => 'Lincoln Electric', 'serial' => true, 'inspect' => true, 'inspDays' => 180, 'min' => 5, 'max' => 20, 'reorder' => 8, 'reorderQty' => 10, 'cost' => 1250.00],
                ['gr' => $grLeatherGloves, 'name' => 'Leather Work Gloves', 'pn' => 'HAN-0001', 'type' => 'ppe', 'uom' => 'pair', 'mfr' => 'Honeywell', 'serial' => false, 'inspect' => false, 'inspDays' => null, 'min' => 50, 'max' => 200, 'reorder' => 60, 'reorderQty' => 100, 'cost' => 95.00],
                ['gr' => $grNitrileGloves, 'name' => 'Nitrile Disposable Gloves (Box 100)', 'pn' => 'HAN-0002', 'type' => 'ppe', 'uom' => 'box', 'mfr' => 'Ansell', 'serial' => false, 'inspect' => false, 'inspDays' => null, 'min' => 30, 'max' => 150, 'reorder' => 40, 'reorderQty' => 60, 'cost' => 165.00],
                ['gr' => $grGoggles, 'name' => 'Safety Goggles - Clear', 'pn' => 'EYE-0001', 'type' => 'ppe', 'uom' => 'each', 'mfr' => '3M', 'serial' => false, 'inspect' => false, 'inspDays' => null, 'min' => 15, 'max' => 80, 'reorder' => 20, 'reorderQty' => 40, 'cost' => 120.00],
                ['gr' => $grUtProbe, 'name' => 'UT Probe 5MHz Angle Beam', 'pn' => 'ULT-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Olympus', 'serial' => true, 'inspect' => true, 'inspDays' => 365, 'min' => 3, 'max' => 15, 'reorder' => 5, 'reorderQty' => 5, 'cost' => 3200.00],
                ['gr' => $grUtDetector, 'name' => 'UT Flaw Detector - Epoch 650', 'pn' => 'ULT-0002', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Olympus', 'serial' => true, 'inspect' => true, 'inspDays' => 365, 'min' => 1, 'max' => 5, 'reorder' => 2, 'reorderQty' => 2, 'cost' => 45000.00],
                ['gr' => $grNortec, 'name' => 'Nortec 600 Eddy Current Tester', 'pn' => 'EDD-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Olympus', 'serial' => true, 'inspect' => true, 'inspDays' => 365, 'min' => 1, 'max' => 3, 'reorder' => 1, 'reorderQty' => 1, 'cost' => 38000.00],
                ['gr' => $grTorque, 'name' => 'Torque Wrench 3/4" Drive', 'pn' => 'TOR-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Snap-on', 'serial' => true, 'inspect' => true, 'inspDays' => 365, 'min' => 5, 'max' => 20, 'reorder' => 8, 'reorderQty' => 10, 'cost' => 4500.00],
                ['gr' => $grPipeCutter, 'name' => 'Pipe Cutter 2-4 inch', 'pn' => 'PIP-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Ridgid', 'serial' => true, 'inspect' => false, 'inspDays' => null, 'min' => 3, 'max' => 10, 'reorder' => 4, 'reorderQty' => 5, 'cost' => 2800.00],
                ['gr' => $grCrimper, 'name' => 'Hydraulic Crimper HCT-300', 'pn' => 'HYD-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Greenlee', 'serial' => true, 'inspect' => true, 'inspDays' => 180, 'min' => 2, 'max' => 8, 'reorder' => 3, 'reorderQty' => 3, 'cost' => 12500.00],
                ['gr' => $grChainSling, 'name' => 'Chain Sling 2 Leg 2T WLL', 'pn' => 'CHA-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Gunnebo', 'serial' => true, 'inspect' => true, 'inspDays' => 90, 'min' => 5, 'max' => 25, 'reorder' => 8, 'reorderQty' => 10, 'cost' => 3800.00],
                ['gr' => $grShackle, 'name' => 'Bow Shackle 5T WLL Green Pin', 'pn' => 'SHA-0001', 'type' => 'item', 'uom' => 'each', 'mfr' => 'Van Beest', 'serial' => true, 'inspect' => true, 'inspDays' => 90, 'min' => 10, 'max' => 50, 'reorder' => 15, 'reorderQty' => 20, 'cost' => 850.00],
                ['gr' => $grWeldKit, 'name' => 'Welding Kit - Standard', 'pn' => 'KIT-0001', 'type' => 'kit_component', 'uom' => 'kit', 'mfr' => 'Various', 'serial' => false, 'inspect' => false, 'inspDays' => null, 'min' => 2, 'max' => 10, 'reorder' => 3, 'reorderQty' => 5, 'cost' => 2500.00],
                ['gr' => $grNdtKit, 'name' => 'NDT Inspection Kit - Basic', 'pn' => 'KIT-0002', 'type' => 'kit_component', 'uom' => 'kit', 'mfr' => 'Various', 'serial' => false, 'inspect' => false, 'inspDays' => null, 'min' => 1, 'max' => 5, 'reorder' => 2, 'reorderQty' => 2, 'cost' => 52000.00],
            ];

            $equipMap = []; // keyed by part_number
            foreach ($equipDefs as $def) {
                $equip = InventoryEquipment::create([
                    'group_requirement_id' => $def['gr']->id,
                    'name' => $def['name'],
                    'part_number' => $def['pn'],
                    'type' => $def['type'],
                    'unit_of_measure' => $def['uom'],
                    'manufacturer' => $def['mfr'],
                    'is_serialized' => $def['serial'],
                    'requires_inspection' => $def['inspect'],
                    'inspection_interval_days' => $def['inspDays'],
                    'min_stock_level' => $def['min'],
                    'max_stock_level' => $def['max'],
                    'reorder_point' => $def['reorder'],
                    'reorder_quantity' => $def['reorderQty'],
                    'cost_price' => $def['cost'],
                    'is_active' => true,
                ]);
                $equipMap[$def['pn']] = $equip;
            }

            // ────────────────────────────────────────────────
            // 6. Warehouse Inventories + Equipment Trackings
            // ────────────────────────────────────────────────

            // Helper to create an item with full tracking history
            $createItem = function (
                int $equipId,
                int $warehouseId,
                ?int $rigId,
                string $finalStatus, // 'available', 'quarantine', 'in_use', 'in_transit'
                int $userId,
                ?string $serial = null,
                ?int $supplierId = null,
                ?string $inspDateOffset = null, // e.g. '-10 days' for overdue, '+60 days' for future
            ) {
                $statusCodeMap = ['available' => 1, 'quarantine' => 1, 'in_use' => 1, 'in_transit' => 1];
                // inventory_status_id: 1=New for most, 5=Quarantined for quarantine
                $condId = $finalStatus === 'quarantine' ? 5 : 1;

                $item = WarehouseInventory::create([
                    'inventory_equipment_id' => $equipId,
                    'warehouse_id' => ($finalStatus === 'in_transit') ? null : $warehouseId,
                    'rig_id' => $rigId,
                    'serial_number' => $serial,
                    'inventory_status_id' => $condId,
                    'status' => $finalStatus,
                    'supplier_id' => $supplierId,
                    'date_of_purchase' => now()->subDays(rand(30, 180))->toDateString(),
                    'received' => true,
                    'next_inspection_date' => $inspDateOffset ? now()->modify($inspDateOffset)->toDateString() : null,
                ]);

                $regDate = now()->subDays(rand(30, 90));

                // Step 1: Registered  //IOC IMPLEMENTED
                EquipmentTracking::create([
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipId,
                    'equipment_tracking_status_id' => 1, // Registered
                    'warehouse_id' => $warehouseId,
                    'authority_user_id' => $userId,
                    'in' => 0, 'out' => 0, 'claimed' => 0,  //IOC IMPLEMENTED
                    'book_in_date' => $regDate->toDateString(),
                ]);

                // Step 2: Into Quarantine  //IOC IMPLEMENTED
                EquipmentTracking::create([
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipId,
                    'equipment_tracking_status_id' => 5, // In Quarantine
                    'warehouse_id' => $warehouseId,
                    'authority_user_id' => $userId,
                    'in' => 1, 'out' => 0, 'claimed' => 0,  //IOC IMPLEMENTED
                    'book_in_date' => $regDate->addDays(1)->toDateString(),
                ]);

                if ($finalStatus === 'quarantine') {
                    // Stay in quarantine — no further tracking rows
                    return $item;
                }

                // Step 3: Out of Quarantine  //IOC IMPLEMENTED
                EquipmentTracking::create([
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipId,
                    'equipment_tracking_status_id' => 5, // In Quarantine
                    'warehouse_id' => $warehouseId,
                    'authority_user_id' => $userId,
                    'in' => 0, 'out' => 1, 'claimed' => 0,  //IOC IMPLEMENTED
                    'book_out_date' => $regDate->addDays(2)->toDateString(),
                ]);

                // Step 4: Into Available  //IOC IMPLEMENTED
                EquipmentTracking::create([
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipId,
                    'equipment_tracking_status_id' => 6, // Stored/Available
                    'warehouse_id' => $warehouseId,
                    'authority_user_id' => $userId,
                    'in' => 1, 'out' => 0, 'claimed' => 0,  //IOC IMPLEMENTED
                    'book_in_date' => $regDate->addDays(2)->toDateString(),
                ]);

                if ($finalStatus === 'available') {
                    return $item;
                }

                if ($finalStatus === 'in_use') {
                    // Out of Available  //IOC IMPLEMENTED
                    EquipmentTracking::create([
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipId,
                        'equipment_tracking_status_id' => 6, // Stored/Available
                        'warehouse_id' => $warehouseId,
                        'authority_user_id' => $userId,
                        'in' => 0, 'out' => 1, 'claimed' => 0,  //IOC IMPLEMENTED
                        'book_out_date' => $regDate->addDays(5)->toDateString(),
                    ]);

                    // Into In Use  //IOC IMPLEMENTED
                    EquipmentTracking::create([
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipId,
                        'equipment_tracking_status_id' => 4, // In Use
                        'warehouse_id' => $warehouseId,
                        'rig_id' => $rigId,
                        'authority_user_id' => $userId,
                        'in' => 1, 'out' => 0, 'claimed' => 0,  //IOC IMPLEMENTED
                        'book_in_date' => $regDate->addDays(5)->toDateString(),
                    ]);
                    return $item;
                }

                if ($finalStatus === 'in_transit') {
                    // Out of Available  //IOC IMPLEMENTED
                    EquipmentTracking::create([
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipId,
                        'equipment_tracking_status_id' => 6, // Stored/Available
                        'warehouse_id' => $warehouseId,
                        'authority_user_id' => $userId,
                        'in' => 0, 'out' => 1, 'claimed' => 0,  //IOC IMPLEMENTED
                        'book_out_date' => $regDate->addDays(5)->toDateString(),
                    ]);

                    // Into In Transit  //IOC IMPLEMENTED
                    EquipmentTracking::create([
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipId,
                        'equipment_tracking_status_id' => 3, // In Transit
                        'warehouse_id' => $warehouseId,
                        'authority_user_id' => $userId,
                        'in' => 1, 'out' => 0, 'claimed' => 0,  //IOC IMPLEMENTED
                        'book_out_date' => $regDate->addDays(5)->toDateString(),
                    ]);
                    return $item;
                }

                return $item;
            };

            // ── Cape Town: ~30 Available ────────────────────
            $availableSpread = [
                ['pn' => 'HEA-0001', 'qty' => 6, 'insp' => '+90 days'],   // Hard Hat
                ['pn' => 'HEA-0002', 'qty' => 3, 'insp' => '+45 days'],   // Welding Helmet
                ['pn' => 'HAN-0001', 'qty' => 4, 'insp' => null],         // Leather Gloves
                ['pn' => 'HAN-0002', 'qty' => 3, 'insp' => null],         // Nitrile Gloves
                ['pn' => 'EYE-0001', 'qty' => 3, 'insp' => null],         // Safety Goggles
                ['pn' => 'ULT-0001', 'qty' => 2, 'insp' => '+120 days'],  // UT Probe
                ['pn' => 'TOR-0001', 'qty' => 2, 'insp' => '-10 days'],   // Torque (overdue!)
                ['pn' => 'PIP-0001', 'qty' => 1, 'insp' => '+200 days'],  // Pipe Cutter
                ['pn' => 'CHA-0001', 'qty' => 3, 'insp' => '+20 days'],   // Chain Sling (due soon)
                ['pn' => 'SHA-0001', 'qty' => 3, 'insp' => '+15 days'],   // Shackle (due soon)
            ];

            $serialCounter = 1000;
            foreach ($availableSpread as $spread) {
                $eq = $equipMap[$spread['pn']];
                for ($i = 0; $i < $spread['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $spread['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $cpt->id, null, 'available', $userId, $serial, $supplierPPE->id, $spread['insp']);
                }
            }

            // ── Cape Town: 5 In Quarantine ──────────────────
            $quarantineItems = [
                ['pn' => 'HEA-0001', 'qty' => 2],
                ['pn' => 'ULT-0002', 'qty' => 1],
                ['pn' => 'HYD-0001', 'qty' => 1],
                ['pn' => 'EDD-0001', 'qty' => 1],
            ];
            foreach ($quarantineItems as $qi) {
                $eq = $equipMap[$qi['pn']];
                for ($i = 0; $i < $qi['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $qi['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $cpt->id, null, 'quarantine', $userId, $serial, $supplierTools->id);
                }
            }

            // ── Rig DSV: 5 In Use ──────────────────────────
            $rigItems = [
                ['pn' => 'TOR-0001', 'qty' => 1],
                ['pn' => 'CHA-0001', 'qty' => 2],
                ['pn' => 'SHA-0001', 'qty' => 2],
            ];
            foreach ($rigItems as $ri) {
                $eq = $equipMap[$ri['pn']];
                for ($i = 0; $i < $ri['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $ri['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $cpt->id, $rigDsv->id, 'in_use', $userId, $serial, $supplierMarine->id, '+60 days');
                }
            }

            // ── In Transit: 3 items CPT → SAL ──────────────
            $transitItems = [
                ['pn' => 'HEA-0001', 'qty' => 1],
                ['pn' => 'HAN-0001', 'qty' => 1],
                ['pn' => 'EYE-0001', 'qty' => 1],
            ];
            foreach ($transitItems as $ti) {
                $eq = $equipMap[$ti['pn']];
                for ($i = 0; $i < $ti['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $ti['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $cpt->id, null, 'in_transit', $userId, $serial, $supplierPPE->id);
                }
            }

            // ── Saldanha: a few items ───────────────────────
            $salItems = [
                ['pn' => 'HEA-0001', 'qty' => 2, 'status' => 'available'],
                ['pn' => 'CHA-0001', 'qty' => 1, 'status' => 'available'],
                ['pn' => 'TOR-0001', 'qty' => 1, 'status' => 'quarantine'],
            ];
            foreach ($salItems as $si) {
                $eq = $equipMap[$si['pn']];
                for ($i = 0; $i < $si['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $si['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $sal->id, null, $si['status'], $userId, $serial, $supplierPPE->id, '+90 days');
                }
            }

            // ── Durban: a few items ─────────────────────────
            $durItems = [
                ['pn' => 'SHA-0001', 'qty' => 2, 'status' => 'available'],
                ['pn' => 'HAN-0002', 'qty' => 1, 'status' => 'available'],
            ];
            foreach ($durItems as $di) {
                $eq = $equipMap[$di['pn']];
                for ($i = 0; $i < $di['qty']; $i++) {
                    $serial = $eq->is_serialized ? sprintf('SN-%s-%04d', $di['pn'], $serialCounter++) : null;
                    $createItem($eq->id, $dur->id, null, $di['status'], $userId, $serial, $supplierMarine->id, '+180 days');
                }
            }

            // ────────────────────────────────────────────────
            // 7. Inventory Kits
            // ────────────────────────────────────────────────
            $weldingKit = InventoryKit::create([
                'name' => 'Standard Welding Kit',
                'kit_code' => 'KIT-WLD-001',
                'group_requirement_id' => $grWeldKit->id,
                'warehouse_id' => $cpt->id,
                'is_active' => true,
            ]);
            DB::table('equipment_kits')->insert([
                ['inventory_kit_id' => $weldingKit->id, 'inventory_equipment_id' => $equipMap['HEA-0002']->id, 'quantity' => 1, 'created_at' => now()],
                ['inventory_kit_id' => $weldingKit->id, 'inventory_equipment_id' => $equipMap['HAN-0001']->id, 'quantity' => 2, 'created_at' => now()],
                ['inventory_kit_id' => $weldingKit->id, 'inventory_equipment_id' => $equipMap['EYE-0001']->id, 'quantity' => 1, 'created_at' => now()],
            ]);

            $ndtKit = InventoryKit::create([
                'name' => 'NDT Inspection Kit - Basic',
                'kit_code' => 'KIT-NDT-001',
                'group_requirement_id' => $grNdtKit->id,
                'warehouse_id' => $cpt->id,
                'is_active' => true,
            ]);
            DB::table('equipment_kits')->insert([
                ['inventory_kit_id' => $ndtKit->id, 'inventory_equipment_id' => $equipMap['ULT-0001']->id, 'quantity' => 2, 'created_at' => now()],
                ['inventory_kit_id' => $ndtKit->id, 'inventory_equipment_id' => $equipMap['ULT-0002']->id, 'quantity' => 1, 'created_at' => now()],
                ['inventory_kit_id' => $ndtKit->id, 'inventory_equipment_id' => $equipMap['EDD-0001']->id, 'quantity' => 1, 'created_at' => now()],
            ]);

            // ────────────────────────────────────────────────
            // 8. Internal RFQ Requests
            // ────────────────────────────────────────────────
            $rfqApproved = InternalRfqRequest::create([
                'requester_user_id' => $userId,
                'warehouse_id' => $cpt->id,
                'urgency' => 'high',
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now()->subDays(5),
                'notes' => 'Urgent PPE restock for upcoming project',
            ]);

            InternalRfqRequestItem::create([
                'internal_rfq_request_id' => $rfqApproved->id,
                'inventory_equipment_id' => $equipMap['HEA-0001']->id,
                'quantity' => 50,
                'notes' => 'Restock white hard hats',
            ]);
            InternalRfqRequestItem::create([
                'internal_rfq_request_id' => $rfqApproved->id,
                'inventory_equipment_id' => $equipMap['HAN-0001']->id,
                'quantity' => 100,
                'notes' => 'Leather gloves running low',
            ]);
            InternalRfqRequestItem::create([
                'internal_rfq_request_id' => $rfqApproved->id,
                'inventory_equipment_id' => $equipMap['EYE-0001']->id,
                'quantity' => 40,
                'notes' => 'Safety goggles for new crew',
            ]);

            $rfqPending = InternalRfqRequest::create([
                'requester_user_id' => $userId,
                'warehouse_id' => $sal->id,
                'urgency' => 'medium',
                'status' => 'pending',
                'notes' => 'Lifting equipment inspection replacements',
            ]);

            InternalRfqRequestItem::create([
                'internal_rfq_request_id' => $rfqPending->id,
                'inventory_equipment_id' => $equipMap['CHA-0001']->id,
                'quantity' => 10,
                'notes' => 'Replace expired chain slings',
            ]);
            InternalRfqRequestItem::create([
                'internal_rfq_request_id' => $rfqPending->id,
                'inventory_equipment_id' => $equipMap['SHA-0001']->id,
                'quantity' => 20,
                'notes' => 'Additional shackles for rig operations',
            ]);

            $this->command->info('WarehouseSeeder completed — 3 warehouses, 2 rigs, 3 suppliers, 15 equipment types, ~50 physical items, 2 kits, 2 RFQ requests.');
        });
    }
}
