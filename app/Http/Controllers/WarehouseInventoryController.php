<?php

namespace App\Http\Controllers;

use App\Models\EquipmentTracking;
use App\Models\InventoryCcu;
use App\Models\InventoryEquipment;
use App\Models\InventoryKit;
use App\Models\InventoryStatus;
use App\Models\Rig;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryField;
use App\Services\EquipmentTrackingValidationService;
use App\Services\StockLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WarehouseInventoryController extends Controller
{
    public function __construct(
        protected StockLevelService $stockService
    ) {}

    public function storeEquipment(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'inventory_equipment_id' => 'required|exists:inventory_equipment,id',
            'serial_number' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'inventory_status_id' => 'nullable|exists:inventory_statuses,id',
            'dynamic_fields' => 'nullable|array',
            'dynamic_fields.*.equipment_req_id' => 'required_with:dynamic_fields|exists:equipment_reqs,id',
            'dynamic_fields.*.value' => 'nullable|string',
        ]);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $equipment = InventoryEquipment::findOrFail($request->input('inventory_equipment_id'));
        $userId = Auth::id();
        $quantity = $equipment->is_serialized ? 1 : ($request->input('quantity', 1));
        $createdItems = [];

        try {
            DB::beginTransaction();

            for ($i = 0; $i < $quantity; $i++) {
                $item = WarehouseInventory::create([
                    'inventory_equipment_id' => $equipment->id,
                    'warehouse_id' => $warehouse->id,
                    'serial_number' => $equipment->is_serialized ? $request->input('serial_number') : null,
                    'inventory_status_id' => $request->input('inventory_status_id'),
                    'received' => true,
                ]);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipment->id,
                    'equipment_tracking_status_id' => 1, // Registered
                    'warehouse_id' => $warehouse->id,
                    'authority_user_id' => $userId,
                    'in' => 0,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'Initial registration',
                ]);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipment->id,
                    'equipment_tracking_status_id' => 5, // Quarantine
                    'warehouse_id' => $warehouse->id,
                    'authority_user_id' => $userId,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'Entered quarantine for inspection',
                ]);

                if ($request->has('dynamic_fields')) {
                    foreach ($request->input('dynamic_fields') as $field) {
                        WarehouseInventoryField::create([
                            'warehouse_inventory_id' => $item->id,
                            'equipment_req_id' => $field['equipment_req_id'],
                            'value' => $field['value'] ?? null,
                        ]);
                    }
                }

                $createdItems[] = $item;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully registered {$quantity} item(s).",
                'items' => $createdItems,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeEquipment failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to register equipment.'], 500);
        }
    }

    public function storeEquipmentPPE(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'inventory_equipment_id' => 'required|exists:inventory_equipment,id',
            'quantity' => 'required|integer|min:1',
            'inventory_status_id' => 'nullable|exists:inventory_statuses,id',
            'dynamic_fields' => 'nullable|array',
            'dynamic_fields.*.equipment_req_id' => 'required_with:dynamic_fields|exists:equipment_reqs,id',
            'dynamic_fields.*.value' => 'nullable|string',
        ]);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $equipment = InventoryEquipment::findOrFail($request->input('inventory_equipment_id'));
        $userId = Auth::id();
        $quantity = $request->input('quantity');
        $createdItems = [];

        try {
            DB::beginTransaction();

            for ($i = 0; $i < $quantity; $i++) {
                $item = WarehouseInventory::create([
                    'inventory_equipment_id' => $equipment->id,
                    'warehouse_id' => $warehouse->id,
                    'inventory_status_id' => $request->input('inventory_status_id'),
                    'received' => true,
                ]);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipment->id,
                    'equipment_tracking_status_id' => 1, // Registered
                    'warehouse_id' => $warehouse->id,
                    'authority_user_id' => $userId,
                    'in' => 0,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'PPE initial registration',
                ]);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $equipment->id,
                    'equipment_tracking_status_id' => 5, // Quarantine
                    'warehouse_id' => $warehouse->id,
                    'authority_user_id' => $userId,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'PPE entered quarantine for inspection',
                ]);

                if ($request->has('dynamic_fields')) {
                    foreach ($request->input('dynamic_fields') as $field) {
                        WarehouseInventoryField::create([
                            'warehouse_inventory_id' => $item->id,
                            'equipment_req_id' => $field['equipment_req_id'],
                            'value' => $field['value'] ?? null,
                        ]);
                    }
                }

                $createdItems[] = $item;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully registered {$quantity} PPE item(s).",
                'items' => $createdItems,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeEquipmentPPE failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to register PPE equipment.'], 500);
        }
    }

    public function approveQuarantine(Request $request, int $warehouseInventoryId): JsonResponse
    {
        $request->validate([
            'inventory_status_id' => 'nullable|exists:inventory_statuses,id',
        ]);

        $item = WarehouseInventory::findOrFail($warehouseInventoryId);
        $userId = Auth::id();

        try {
            DB::beginTransaction();

            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $item->id,
                'inventory_equipment_id' => $item->inventory_equipment_id,
                'equipment_tracking_status_id' => 5, // Quarantine
                'warehouse_id' => $item->warehouse_id,
                'authority_user_id' => $userId,
                'in' => 0,
                'out' => 1,
                'claimed' => 0,
                'book_out_date' => now()->toDateString(),
                'notes' => 'Leaving quarantine — approved',
            ]);

            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $item->id,
                'inventory_equipment_id' => $item->inventory_equipment_id,
                'equipment_tracking_status_id' => 6, // Available
                'warehouse_id' => $item->warehouse_id,
                'authority_user_id' => $userId,
                'in' => 1,
                'out' => 0,
                'claimed' => 0,
                'book_in_date' => now()->toDateString(),
                'notes' => 'Approved and now available',
            ]);

            if ($request->filled('inventory_status_id')) {
                $item->update(['inventory_status_id' => $request->input('inventory_status_id')]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Item approved from quarantine.',
                'item' => $item->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('approveQuarantine failed', ['id' => $warehouseInventoryId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to approve quarantine.'], 500);
        }
    }

    public function approveQuarantineBulk(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:warehouse_inventories,id',
        ]);

        $ids = $request->input('ids');
        $userId = Auth::id();
        $approvedCount = 0;

        try {
            DB::beginTransaction();

            foreach ($ids as $id) {
                $item = WarehouseInventory::find($id);
                if (!$item) {
                    continue;
                }

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 5, // Quarantine
                    'warehouse_id' => $item->warehouse_id,
                    'authority_user_id' => $userId,
                    'in' => 0,
                    'out' => 1,
                    'claimed' => 0,
                    'book_out_date' => now()->toDateString(),
                    'notes' => 'Bulk leaving quarantine — approved',
                ]);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 6, // Available
                    'warehouse_id' => $item->warehouse_id,
                    'authority_user_id' => $userId,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'Bulk approved and now available',
                ]);

                $approvedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully approved {$approvedCount} item(s) from quarantine.",
                'approved_count' => $approvedCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('approveQuarantineBulk failed', ['ids' => $ids, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to bulk approve quarantine.'], 500);
        }
    }

    public function updateCondition(Request $request, int $warehouseInventoryId): JsonResponse
    {
        $request->validate([
            'inventory_status_id' => 'required|exists:inventory_statuses,id',
        ]);

        $item = WarehouseInventory::findOrFail($warehouseInventoryId);
        $item->update(['inventory_status_id' => $request->input('inventory_status_id')]);

        return response()->json([
            'message' => 'Condition updated successfully.',
            'item' => $item->fresh(),
        ]);
    }

    public function destroyItem(int $warehouseInventoryId): JsonResponse
    {
        $item = WarehouseInventory::findOrFail($warehouseInventoryId);
        $userId = Auth::id();

        try {
            DB::beginTransaction();

            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $item->id,
                'inventory_equipment_id' => $item->inventory_equipment_id,
                'equipment_tracking_status_id' => 7, // Destroyed
                'warehouse_id' => $item->warehouse_id,
                'authority_user_id' => $userId,
                'in' => 0,
                'out' => 1,
                'claimed' => 0,
                'notes' => 'Item marked as destroyed',
            ]);

            // Set condition to X (Lost/Stolen)
            $conditionX = InventoryStatus::where('code', 'X')->first();
            if ($conditionX) {
                $item->update(['inventory_status_id' => $conditionX->id]);
            }

            DB::commit();

            return response()->json(['message' => 'Item marked as destroyed.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('destroyItem failed', ['id' => $warehouseInventoryId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark item as destroyed.'], 500);
        }
    }

    public function markMissing(int $warehouseInventoryId): JsonResponse
    {
        $item = WarehouseInventory::findOrFail($warehouseInventoryId);
        $userId = Auth::id();

        try {
            DB::beginTransaction();

            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $item->id,
                'inventory_equipment_id' => $item->inventory_equipment_id,
                'equipment_tracking_status_id' => 12, // Missing/Lost
                'warehouse_id' => $item->warehouse_id,
                'authority_user_id' => $userId,
                'in' => 0,
                'out' => 0,
                'claimed' => 1,
                'notes' => 'Item marked as missing/lost',
            ]);

            // Set condition to X (Lost/Stolen)
            $conditionX = InventoryStatus::where('code', 'X')->first();
            if ($conditionX) {
                $item->update(['inventory_status_id' => $conditionX->id]);
            }

            DB::commit();

            return response()->json(['message' => 'Item marked as missing/lost.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('markMissing failed', ['id' => $warehouseInventoryId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark item as missing.'], 500);
        }
    }

    public function bookOutEquipment(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'exists:warehouse_inventories,id',
            'destination_type' => 'required|in:warehouse,rig',
            'destination_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $userId = Auth::id();
        $items = $request->input('items');
        $destinationType = $request->input('destination_type');
        $destinationId = $request->input('destination_id');
        $notes = $request->input('notes', '');
        $bookedCount = 0;

        try {
            DB::beginTransaction();

            $nextWarehouseId = null;
            $rigId = null;

            if ($destinationType === 'warehouse') {
                Warehouse::findOrFail($destinationId);
                $nextWarehouseId = $destinationId;
            } else {
                $rig = Rig::findOrFail($destinationId);
                $nextWarehouseId = $rig->warehouse_id;
                $rigId = $rig->id;
            }

            foreach ($items as $itemId) {
                $item = WarehouseInventory::findOrFail($itemId);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 3, // In Transit
                    'warehouse_id' => $item->warehouse_id,
                    'rig_id' => $rigId,
                    'authority_user_id' => $userId,
                    'last_location_id' => $item->warehouse_id,
                    'next_warehouse_id' => $nextWarehouseId,
                    'in' => 0,
                    'out' => 1,
                    'claimed' => 0,
                    'book_out_date' => now()->toDateString(),
                    'notes' => $notes ?: 'Booked out to ' . $destinationType,
                ]);

                $item->update(['warehouse_id' => null]);
                $bookedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully booked out {$bookedCount} item(s).",
                'booked_count' => $bookedCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('bookOutEquipment failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to book out equipment.'], 500);
        }
    }

    public function receiveEquipment(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'exists:warehouse_inventories,id',
        ]);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $userId = Auth::id();
        $items = $request->input('items');
        $receivedCount = 0;

        try {
            DB::beginTransaction();

            foreach ($items as $itemId) {
                $item = WarehouseInventory::findOrFail($itemId);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 5, // Quarantine
                    'warehouse_id' => $warehouse->id,
                    'authority_user_id' => $userId,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'book_in_date' => now()->toDateString(),
                    'notes' => 'Received at warehouse — entered quarantine',
                ]);

                $item->update([
                    'warehouse_id' => $warehouse->id,
                    'rig_id' => null,
                    'received' => true,
                ]);

                $receivedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully received {$receivedCount} item(s).",
                'received_count' => $receivedCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('receiveEquipment failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to receive equipment.'], 500);
        }
    }

    public function storeKit(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'inventory_kit_id' => 'required|exists:inventory_kits,id',
            'serial_numbers' => 'nullable|array',
        ]);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $kit = InventoryKit::with('inventoryEquipment')->findOrFail($request->input('inventory_kit_id'));
        $userId = Auth::id();
        $serialNumbers = $request->input('serial_numbers', []);
        $createdItems = [];

        try {
            DB::beginTransaction();

            foreach ($kit->inventoryEquipment as $equipment) {
                $quantity = $equipment->pivot->quantity;

                for ($i = 0; $i < $quantity; $i++) {
                    $serial = $serialNumbers[$equipment->id][$i] ?? null;

                    $item = WarehouseInventory::create([
                        'inventory_equipment_id' => $equipment->id,
                        'warehouse_id' => $warehouse->id,
                        'inventory_kit_id' => $kit->id,
                        'serial_number' => $serial,
                        'received' => true,
                    ]);

                    EquipmentTracking::create([ //IOC IMPLEMENTED
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipment->id,
                        'equipment_tracking_status_id' => 1, // Registered
                        'warehouse_id' => $warehouse->id,
                        'authority_user_id' => $userId,
                        'in' => 0,
                        'out' => 0,
                        'claimed' => 0,
                        'book_in_date' => now()->toDateString(),
                        'notes' => "Kit '{$kit->name}' — initial registration",
                    ]);

                    EquipmentTracking::create([ //IOC IMPLEMENTED
                        'warehouse_inventories_id' => $item->id,
                        'inventory_equipment_id' => $equipment->id,
                        'equipment_tracking_status_id' => 5, // Quarantine
                        'warehouse_id' => $warehouse->id,
                        'authority_user_id' => $userId,
                        'in' => 1,
                        'out' => 0,
                        'claimed' => 0,
                        'book_in_date' => now()->toDateString(),
                        'notes' => "Kit '{$kit->name}' — entered quarantine",
                    ]);

                    $createdItems[] = $item;
                }
            }

            $kit->update(['warehouse_id' => $warehouse->id]);

            DB::commit();

            return response()->json([
                'message' => 'Kit registered successfully with ' . count($createdItems) . ' item(s).',
                'items' => $createdItems,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeKit failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to register kit.'], 500);
        }
    }

    public function storeCcu(Request $request, int $warehouseId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'container_number' => 'nullable|string|max:255',
            'container_type' => 'nullable|string|max:255',
            'inventory_equipment_id' => 'required|exists:inventory_equipment,id',
            'items' => 'nullable|array',
            'items.*.inventory_equipment_id' => 'required_with:items|exists:inventory_equipment,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.serial_numbers' => 'nullable|array',
        ]);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $userId = Auth::id();
        $createdItems = [];

        try {
            DB::beginTransaction();

            // 1. Create WarehouseInventory for the CCU itself
            $ccuInventory = WarehouseInventory::create([
                'inventory_equipment_id' => $request->input('inventory_equipment_id'),
                'warehouse_id' => $warehouse->id,
                'received' => true,
            ]);

            // 2. Tracking for the CCU itself
            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $ccuInventory->id,
                'inventory_equipment_id' => $ccuInventory->inventory_equipment_id,
                'equipment_tracking_status_id' => 1, // Registered
                'warehouse_id' => $warehouse->id,
                'authority_user_id' => $userId,
                'in' => 0,
                'out' => 0,
                'claimed' => 0,
                'book_in_date' => now()->toDateString(),
                'notes' => 'CCU container registered',
            ]);

            EquipmentTracking::create([ //IOC IMPLEMENTED
                'warehouse_inventories_id' => $ccuInventory->id,
                'inventory_equipment_id' => $ccuInventory->inventory_equipment_id,
                'equipment_tracking_status_id' => 5, // Quarantine
                'warehouse_id' => $warehouse->id,
                'authority_user_id' => $userId,
                'in' => 1,
                'out' => 0,
                'claimed' => 0,
                'book_in_date' => now()->toDateString(),
                'notes' => 'CCU container entered quarantine',
            ]);

            // 3. Create InventoryCcu record
            $ccu = InventoryCcu::create([
                'warehouse_inventory_id' => $ccuInventory->id,
                'name' => $request->input('name'),
                'container_number' => $request->input('container_number'),
                'container_type' => $request->input('container_type'),
                'warehouse_id' => $warehouse->id,
                'is_active' => true,
            ]);

            // 4. Create items inside the CCU
            if ($request->has('items')) {
                foreach ($request->input('items') as $contentItem) {
                    $qty = $contentItem['quantity'];
                    $serials = $contentItem['serial_numbers'] ?? [];

                    for ($i = 0; $i < $qty; $i++) {
                        $item = WarehouseInventory::create([
                            'inventory_equipment_id' => $contentItem['inventory_equipment_id'],
                            'warehouse_id' => $warehouse->id,
                            'inventory_ccu_id' => $ccu->id,
                            'serial_number' => $serials[$i] ?? null,
                            'received' => true,
                        ]);

                        EquipmentTracking::create([ //IOC IMPLEMENTED
                            'warehouse_inventories_id' => $item->id,
                            'inventory_equipment_id' => $item->inventory_equipment_id,
                            'equipment_tracking_status_id' => 1, // Registered
                            'warehouse_id' => $warehouse->id,
                            'authority_user_id' => $userId,
                            'in' => 0,
                            'out' => 0,
                            'claimed' => 0,
                            'book_in_date' => now()->toDateString(),
                            'notes' => "CCU '{$ccu->name}' content — registered",
                        ]);

                        EquipmentTracking::create([ //IOC IMPLEMENTED
                            'warehouse_inventories_id' => $item->id,
                            'inventory_equipment_id' => $item->inventory_equipment_id,
                            'equipment_tracking_status_id' => 5, // Quarantine
                            'warehouse_id' => $warehouse->id,
                            'authority_user_id' => $userId,
                            'in' => 1,
                            'out' => 0,
                            'claimed' => 0,
                            'book_in_date' => now()->toDateString(),
                            'notes' => "CCU '{$ccu->name}' content — entered quarantine",
                        ]);

                        $createdItems[] = $item;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'CCU registered with ' . count($createdItems) . ' item(s) inside.',
                'ccu' => $ccu,
                'ccu_inventory' => $ccuInventory,
                'items' => $createdItems,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeCcu failed', ['warehouse_id' => $warehouseId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to register CCU.'], 500);
        }
    }

    public function storeEquipmentOnRig(Request $request, int $rigId): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'exists:warehouse_inventories,id',
        ]);

        $rig = Rig::findOrFail($rigId);
        $userId = Auth::id();
        $items = $request->input('items');
        $deployedCount = 0;

        try {
            DB::beginTransaction();

            foreach ($items as $itemId) {
                $item = WarehouseInventory::findOrFail($itemId);

                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 4, // In Use
                    'warehouse_id' => null,
                    'rig_id' => $rig->id,
                    'authority_user_id' => $userId,
                    'last_location_id' => $item->warehouse_id,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'notes' => "Deployed to rig '{$rig->rig_name}'",
                ]);

                $item->update([
                    'rig_id' => $rig->id,
                    'warehouse_id' => null,
                ]);

                $deployedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully deployed {$deployedCount} item(s) to rig.",
                'deployed_count' => $deployedCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeEquipmentOnRig failed', ['rig_id' => $rigId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to deploy equipment to rig.'], 500);
        }
    }

    public function storeKitOnRig(Request $request, int $rigId): JsonResponse
    {
        $request->validate([
            'inventory_kit_id' => 'required|exists:inventory_kits,id',
        ]);

        $rig = Rig::findOrFail($rigId);
        $kit = InventoryKit::findOrFail($request->input('inventory_kit_id'));
        $userId = Auth::id();
        $deployedCount = 0;

        try {
            DB::beginTransaction();

            $kitItems = WarehouseInventory::where('inventory_kit_id', $kit->id)
                ->whereNotNull('warehouse_id')
                ->get();

            foreach ($kitItems as $item) {
                EquipmentTracking::create([ //IOC IMPLEMENTED
                    'warehouse_inventories_id' => $item->id,
                    'inventory_equipment_id' => $item->inventory_equipment_id,
                    'equipment_tracking_status_id' => 4, // In Use
                    'warehouse_id' => null,
                    'rig_id' => $rig->id,
                    'authority_user_id' => $userId,
                    'last_location_id' => $item->warehouse_id,
                    'in' => 1,
                    'out' => 0,
                    'claimed' => 0,
                    'notes' => "Kit '{$kit->name}' deployed to rig '{$rig->rig_name}'",
                ]);

                $item->update([
                    'rig_id' => $rig->id,
                    'warehouse_id' => null,
                ]);

                $deployedCount++;
            }

            $kit->update(['warehouse_id' => null]);

            DB::commit();

            return response()->json([
                'message' => "Kit '{$kit->name}' deployed with {$deployedCount} item(s) to rig.",
                'deployed_count' => $deployedCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeKitOnRig failed', ['rig_id' => $rigId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to deploy kit to rig.'], 500);
        }
    }

    // ─── Movement History ──────────────────────────────────

    public function movementHistory(int $id): JsonResponse
    {
        $item = WarehouseInventory::findOrFail($id);
        $history = $this->stockService->getMovementHistory($id);

        return response()->json(['data' => $history]);
    }

    // ─── Kits ──────────────────────────────────────────────

    public function kitsIndex(): \Inertia\Response
    {
        $kits = InventoryKit::with(['warehouse', 'groupRequirement'])
            ->withCount('inventoryEquipment as component_count')
            ->orderBy('name')
            ->paginate(18);

        return Inertia::render('Kits/Index', [
            'kits' => $kits,
        ]);
    }

    public function kitShow(int $id): \Inertia\Response
    {
        $kit = InventoryKit::with([
            'warehouse',
            'groupRequirement',
            'inventoryEquipment',
            'warehouseInventories.inventoryStatus',
            'warehouseInventories.warehouse',
        ])->withCount('inventoryEquipment as component_count')
          ->findOrFail($id);

        return Inertia::render('Kits/Show', [
            'kit' => $kit,
        ]);
    }

    public function kitStore(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'kit_code' => 'nullable|string|max:100',
            'group_requirement_id' => 'nullable|exists:group_requirements,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'components' => 'required|array|min:1',
            'components.*.inventory_equipment_id' => 'required|exists:inventory_equipment,id',
            'components.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $kit = InventoryKit::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'kit_code' => $request->input('kit_code'),
                'group_requirement_id' => $request->input('group_requirement_id'),
                'warehouse_id' => $request->input('warehouse_id'),
                'is_active' => true,
            ]);

            foreach ($request->input('components') as $comp) {
                $kit->inventoryEquipment()->attach($comp['inventory_equipment_id'], [
                    'quantity' => $comp['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => "Kit '{$kit->name}' created successfully.",
                'kit' => $kit->load('inventoryEquipment'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('kitStore failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create kit.'], 500);
        }
    }

    public function kitUpdate(Request $request, int $id): JsonResponse
    {
        $kit = InventoryKit::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'kit_code' => 'nullable|string|max:100',
            'group_requirement_id' => 'nullable|exists:group_requirements,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'is_active' => 'nullable|boolean',
            'components' => 'nullable|array',
            'components.*.inventory_equipment_id' => 'required_with:components|exists:inventory_equipment,id',
            'components.*.quantity' => 'required_with:components|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $kit->update($request->only(['name', 'description', 'kit_code', 'group_requirement_id', 'warehouse_id', 'is_active']));

            if ($request->has('components')) {
                $syncData = [];
                foreach ($request->input('components') as $comp) {
                    $syncData[$comp['inventory_equipment_id']] = ['quantity' => $comp['quantity']];
                }
                $kit->inventoryEquipment()->sync($syncData);
            }

            DB::commit();

            return response()->json([
                'message' => "Kit '{$kit->name}' updated.",
                'kit' => $kit->fresh(['inventoryEquipment', 'warehouse']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('kitUpdate failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update kit.'], 500);
        }
    }

    public function kitDestroy(int $id): JsonResponse
    {
        $kit = InventoryKit::findOrFail($id);

        try {
            $kit->delete();
            return response()->json(['message' => "Kit '{$kit->name}' deleted."]);
        } catch (\Throwable $e) {
            Log::error('kitDestroy failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete kit.'], 500);
        }
    }

    // ─── CCU / Containers ──────────────────────────────────

    public function ccuIndex(): \Inertia\Response
    {
        $ccus = InventoryCcu::with(['warehouse', 'rig'])
            ->withCount('items')
            ->orderBy('name')
            ->paginate(18);

        return Inertia::render('CCU/Index', [
            'ccus' => $ccus,
        ]);
    }

    public function ccuShow(int $id): \Inertia\Response
    {
        $ccu = InventoryCcu::with([
            'warehouse',
            'rig',
            'items.inventoryEquipment',
            'items.inventoryStatus',
        ])->withCount('items')
          ->findOrFail($id);

        return Inertia::render('CCU/Show', [
            'ccu' => $ccu,
        ]);
    }

    public function ccuUpdate(Request $request, int $id): JsonResponse
    {
        $ccu = InventoryCcu::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'container_number' => 'nullable|string|max:100',
            'container_type' => 'nullable|string|max:100',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'rig_id' => 'nullable|exists:rigs,id',
            'is_active' => 'nullable|boolean',
        ]);

        $ccu->update($request->only(['name', 'container_number', 'container_type', 'warehouse_id', 'rig_id', 'is_active']));

        return response()->json([
            'message' => "Container '{$ccu->name}' updated.",
            'ccu' => $ccu->fresh(['warehouse', 'rig']),
        ]);
    }

    public function ccuDestroy(int $id): JsonResponse
    {
        $ccu = InventoryCcu::findOrFail($id);

        if ($ccu->items()->count() > 0) {
            return response()->json(['message' => 'Cannot delete container that still has items inside.'], 422);
        }

        try {
            $ccu->delete();
            return response()->json(['message' => "Container '{$ccu->name}' deleted."]);
        } catch (\Throwable $e) {
            Log::error('ccuDestroy failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete container.'], 500);
        }
    }

    public function trackingValidation()
    {
        return Inertia::render('Admin/TrackingValidation');
    }

    public function runValidation()
    {
        $service = app(EquipmentTrackingValidationService::class);
        return response()->json($service->runAllChecks());
    }

    public function runFix(string $checkName)
    {
        $service = app(EquipmentTrackingValidationService::class);
        $method = 'fix' . str_replace(' ', '', ucwords(str_replace('_', ' ', $checkName)));
        if (!method_exists($service, $method)) {
            return response()->json(['message' => 'Fix not available'], 400);
        }
        return response()->json($service->$method());
    }
}
