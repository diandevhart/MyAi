<?php

namespace App\Http\Controllers;

use App\Models\InventoryEquipment;
use App\Models\WarehouseEquipmentStockSetting;
use App\Models\WarehouseInventory;
use App\Services\StockLevelService;
use App\Traits\GeneratesPartNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InventoryEquipmentController extends Controller
{
    use GeneratesPartNumbers;

    public function __construct(
        protected StockLevelService $stockService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $query = InventoryEquipment::with('groupRequirement');

        if ($request->filled('search')) {
            $term = '%' . mb_strtolower($request->input('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        if ($request->filled('group_requirement_id')) {
            $query->where('group_requirement_id', $request->input('group_requirement_id'));
        }

        $equipment = $query->orderBy('name')->paginate(20)->withQueryString();

        $equipmentIds = $equipment->pluck('id')->toArray();
        $stockLevels = [];
        if (!empty($equipmentIds)) {
            $stockLevels = $this->stockService->getBulkStockLevels($equipmentIds);
        }

        return Inertia::render('Equipment/Index', [
            'equipment' => $equipment,
            'stockLevels' => $stockLevels,
            'filters' => $request->only(['search', 'group_requirement_id']),
        ]);
    }

    public function show(int $id): InertiaResponse
    {
        $equipment = InventoryEquipment::with(['groupRequirement', 'equipmentReqs'])
            ->findOrFail($id);

        $stockLevel = $this->stockService->getEquipmentStockLevel($id);

        $physicalItems = WarehouseInventory::where('inventory_equipment_id', $id)
            ->with(['warehouse', 'rig', 'inventoryStatus'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Equipment/Show', [
            'equipment' => $equipment,
            'stockLevel' => $stockLevel,
            'physicalItems' => $physicalItems,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'group_requirement_id' => 'required|exists:group_requirements,id',
            'type' => 'required|in:item,ppe,kit_component',
            'unit_of_measure' => 'nullable|string|max:50',
            'manufacturer' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $equipment = InventoryEquipment::create([
            'name' => $request->input('name'),
            'group_requirement_id' => $request->input('group_requirement_id'),
            'type' => $request->input('type'),
            'unit_of_measure' => $request->input('unit_of_measure'),
            'manufacturer' => $request->input('manufacturer'),
            'description' => $request->input('description'),
        ]);

        return redirect()->route('equipment.show', $equipment->id);
    }

    public function update(Request $request, int $id)
    {
        $equipment = InventoryEquipment::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'group_requirement_id' => 'required|exists:group_requirements,id',
            'type' => 'required|in:item,ppe,kit_component',
            'unit_of_measure' => 'nullable|string|max:50',
            'manufacturer' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $equipment->update([
            'name' => $request->input('name'),
            'group_requirement_id' => $request->input('group_requirement_id'),
            'type' => $request->input('type'),
            'unit_of_measure' => $request->input('unit_of_measure'),
            'manufacturer' => $request->input('manufacturer'),
            'description' => $request->input('description'),
        ]);

        return redirect()->back();
    }

    public function stockSettings(Request $request, int $id): JsonResponse
    {
        InventoryEquipment::findOrFail($id);

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'required|integer|min:0',
            'reorder_point' => 'required|integer|min:0',
            'reorder_quantity' => 'required|integer|min:0',
        ]);

        $setting = WarehouseEquipmentStockSetting::updateOrCreate(
            [
                'warehouse_id' => $request->input('warehouse_id'),
                'inventory_equipment_id' => $id,
            ],
            [
                'min_stock_level' => $request->input('min_stock_level'),
                'max_stock_level' => $request->input('max_stock_level'),
                'reorder_point' => $request->input('reorder_point'),
                'reorder_quantity' => $request->input('reorder_quantity'),
            ]
        );

        return response()->json([
            'message' => 'Stock settings saved.',
            'setting' => $setting,
        ]);
    }
}
