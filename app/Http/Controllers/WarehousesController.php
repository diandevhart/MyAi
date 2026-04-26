<?php

namespace App\Http\Controllers;

use App\Models\EquipmentTracking;
use App\Models\InventoryCcu;
use App\Models\InventoryKit;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\StockLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WarehousesController extends Controller
{
    public function __construct(
        protected StockLevelService $stockService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
        }

        if ($request->filled('type')) {
            $query->where('warehouse_type', $request->input('type'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $warehouses = $query->withCount('warehouseInventories')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => $request->only(['search', 'type', 'active']),
        ]);
    }

    public function show(int $id): InertiaResponse
    {
        $warehouse = Warehouse::findOrFail($id);
        $stats = $this->stockService->getWarehouseDashboardStats($id);

        return Inertia::render('Warehouses/Show', [
            'warehouse' => $warehouse,
            'initialStats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'warehouse_type' => 'required|in:warehouse,rig_store,container_yard',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        Warehouse::create($validated);

        return redirect()->back()->with('success', 'Warehouse created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code,' . $warehouse->id,
            'warehouse_type' => 'required|in:warehouse,rig_store,container_yard',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $warehouse->update($validated);

        return redirect()->back()->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(int $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return redirect()->back()->with('success', 'Warehouse deleted successfully.');
    }

    public function dashboardStats(int $id): JsonResponse
    {
        $stats = $this->stockService->getWarehouseDashboardStats($id);
        $stats['stock_breakdown'] = $this->stockService->getDetailedStockBreakdownByWarehouse($id);

        return response()->json($stats);
    }

    public function statDetailItems(int $id, string $statType): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $data = match ($statType) {
                'available' => $this->getItemsByLatestStatus($id, 6),
                'quarantine' => $this->getItemsByLatestStatus($id, 5),
                'in_use' => $this->getItemsByLatestStatus($id, 4),
                'inbound' => $this->stockService->getInboundOutbound($id)['inbound'],
                'outbound' => $this->stockService->getInboundOutbound($id)['outbound'],
                'low_stock' => collect($this->stockService->getDetailedStockBreakdownByWarehouse($id))
                    ->filter(fn ($item) => $item['is_low_stock'])
                    ->values()
                    ->toArray(),
                'inspections_due' => WarehouseInventory::where('warehouse_inventories.warehouse_id', $id)
                    ->whereNotNull('warehouse_inventories.next_inspection_date')
                    ->where('warehouse_inventories.next_inspection_date', '<=', now()->addDays(30))
                    ->join('inventory_equipment', 'warehouse_inventories.inventory_equipment_id', '=', 'inventory_equipment.id')
                    ->select(
                        'warehouse_inventories.id',
                        'inventory_equipment.name as equipment_name',
                        'warehouse_inventories.serial_number',
                        'warehouse_inventories.next_inspection_date',
                    )
                    ->get()
                    ->toArray(),
                'recent_activity' => EquipmentTracking::where('equipment_trackings.warehouse_id', $id)
                    ->join('equipment_tracking_statuses', 'equipment_trackings.equipment_tracking_status_id', '=', 'equipment_tracking_statuses.id')
                    ->leftJoin('inventory_equipment', 'equipment_trackings.inventory_equipment_id', '=', 'inventory_equipment.id')
                    ->leftJoin('users', 'equipment_trackings.authority_user_id', '=', 'users.id')
                    ->select(
                        'equipment_trackings.id',
                        'equipment_tracking_statuses.name as status_name',
                        'inventory_equipment.name as equipment_name',
                        'users.name as user_name',
                        'equipment_trackings.created_at',
                        'equipment_trackings.updated_at',
                    )
                    ->orderBy('equipment_trackings.created_at', 'desc')
                    ->limit(15)
                    ->get()
                    ->toArray(),
                'condition_breakdown' => WarehouseInventory::where('warehouse_inventories.warehouse_id', $id)
                    ->join('inventory_statuses', 'warehouse_inventories.inventory_status_id', '=', 'inventory_statuses.id')
                    ->select('inventory_statuses.code', 'inventory_statuses.name', DB::raw('COUNT(*) as count'))
                    ->groupBy('inventory_statuses.code', 'inventory_statuses.name')
                    ->get()
                    ->toArray(),
                'stock_breakdown' => $this->stockService->getDetailedStockBreakdownByWarehouse($id),
                default => [],
            };

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            Log::error('statDetailItems failed', ['id' => $id, 'stat_type' => $statType, 'error' => $e->getMessage()]);
            return response()->json(['data' => []], 500);
        }
    }

    public function globalSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:255',
            'types' => 'nullable|array',
            'types.*' => 'in:equipment,kit,ccu',
        ]);

        $search = mb_strtolower($request->input('query'));
        $types = $request->input('types', ['equipment', 'kit', 'ccu']);
        $results = [];

        try {
            if (in_array('equipment', $types)) {
                $equipment = WarehouseInventory::join('inventory_equipment', 'warehouse_inventories.inventory_equipment_id', '=', 'inventory_equipment.id')
                    ->leftJoin('warehouses', 'warehouse_inventories.warehouse_id', '=', 'warehouses.id')
                    ->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(inventory_equipment.name) LIKE ?', ["%{$search}%"])
                          ->orWhereRaw('LOWER(inventory_equipment.part_number) LIKE ?', ["%{$search}%"])
                          ->orWhereRaw('LOWER(warehouse_inventories.serial_number) LIKE ?', ["%{$search}%"]);
                    })
                    ->select(
                        'warehouse_inventories.id',
                        'inventory_equipment.name as equipment_name',
                        'inventory_equipment.part_number',
                        'warehouse_inventories.serial_number',
                        'warehouses.name as warehouse_name',
                    )
                    ->limit(50)
                    ->get();

                $results['equipment'] = $equipment;
            }

            if (in_array('kit', $types)) {
                $kits = InventoryKit::leftJoin('warehouses', 'inventory_kits.warehouse_id', '=', 'warehouses.id')
                    ->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(inventory_kits.name) LIKE ?', ["%{$search}%"])
                          ->orWhereRaw('LOWER(inventory_kits.kit_code) LIKE ?', ["%{$search}%"]);
                    })
                    ->select(
                        'inventory_kits.id',
                        'inventory_kits.name',
                        'inventory_kits.kit_code',
                        'warehouses.name as warehouse_name',
                    )
                    ->limit(50)
                    ->get();

                $results['kit'] = $kits;
            }

            if (in_array('ccu', $types)) {
                $ccus = InventoryCcu::leftJoin('warehouses', 'inventory_ccus.warehouse_id', '=', 'warehouses.id')
                    ->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(inventory_ccus.name) LIKE ?', ["%{$search}%"])
                          ->orWhereRaw('LOWER(inventory_ccus.container_number) LIKE ?', ["%{$search}%"]);
                    })
                    ->select(
                        'inventory_ccus.id',
                        'inventory_ccus.name',
                        'inventory_ccus.container_number',
                        'warehouses.name as warehouse_name',
                    )
                    ->limit(50)
                    ->get();

                $results['ccu'] = $ccus;
            }

            $counts = [];
            foreach ($results as $type => $items) {
                $counts[$type] = count($items);
            }

            return response()->json(['results' => $results, 'counts' => $counts]);
        } catch (\Throwable $e) {
            Log::error('globalSearch failed', ['query' => $search, 'error' => $e->getMessage()]);
            return response()->json(['results' => [], 'counts' => []], 500);
        }
    }

    /**
     * Get warehouse_inventories items by their latest tracking status.
     */
    protected function getItemsByLatestStatus(int $warehouseId, int $statusId): array
    {
        return DB::table('warehouse_inventories')
            ->join('inventory_equipment', 'warehouse_inventories.inventory_equipment_id', '=', 'inventory_equipment.id')
            ->leftJoin('inventory_statuses', 'warehouse_inventories.inventory_status_id', '=', 'inventory_statuses.id')
            ->joinSub(
                EquipmentTracking::select('warehouse_inventories_id', DB::raw('MAX(equipment_trackings.id) as max_id'))
                    ->whereNotNull('equipment_trackings.warehouse_inventories_id')
                    ->groupBy('warehouse_inventories_id'),
                'latest',
                'warehouse_inventories.id',
                '=',
                'latest.warehouse_inventories_id'
            )
            ->join('equipment_trackings', 'equipment_trackings.id', '=', 'latest.max_id')
            ->where('warehouse_inventories.warehouse_id', $warehouseId)
            ->where('equipment_trackings.equipment_tracking_status_id', $statusId)
            ->whereNull('warehouse_inventories.deleted_at')
            ->select(
                'warehouse_inventories.id',
                'inventory_equipment.name as equipment_name',
                'inventory_equipment.part_number',
                'warehouse_inventories.serial_number',
                'inventory_statuses.code as condition_code',
                'inventory_statuses.name as condition_name',
            )
            ->get()
            ->toArray();
    }
}
