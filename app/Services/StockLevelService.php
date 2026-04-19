<?php

namespace App\Services;

use App\Models\EquipmentTracking;
use App\Models\InventoryCcu;
use App\Models\InventoryEquipment;
use App\Models\InventoryKit;
use App\Models\WarehouseEquipmentStockSetting;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockLevelService
{
    protected const REGISTERED = 1;
    protected const RESERVED = 2;
    protected const IN_TRANSIT = 3;
    protected const IN_USE = 4;
    protected const IN_QUARANTINE = 5;
    protected const STORED_AVAILABLE = 6;
    protected const DESTROYED = 7;
    protected const PENDING_ORDER = 8;
    protected const OUT_FOR_DELIVERY = 9;
    protected const DELIVERED = 10;
    protected const ORDERED = 11;
    protected const MISSING_LOST = 12;

    public function getWarehouseDashboardStats(int $warehouseId): array
    {
        try {
            $totalItems = WarehouseInventory::where('warehouse_id', $warehouseId)->count();

            $available = $this->sumLedgerByStatus($warehouseId, self::STORED_AVAILABLE);
            $inQuarantine = $this->sumLedgerByStatus($warehouseId, self::IN_QUARANTINE);
            $inUse = $this->sumLedgerByStatus($warehouseId, self::IN_USE);

            $inbound = $this->countInbound($warehouseId);
            $outbound = $this->countOutbound($warehouseId);

            $lowStockCount = $this->countLowStock($warehouseId);

            $kitsCount = InventoryKit::where('warehouse_id', $warehouseId)->count();
            $ccuCount = InventoryCcu::where('warehouse_id', $warehouseId)->count();

            $inspectionsDue = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->whereNotNull('next_inspection_date')
                ->where('next_inspection_date', '<=', now()->addDays(30))
                ->count();

            return [
                'total_items' => $totalItems,
                'available' => $available,
                'in_quarantine' => $inQuarantine,
                'in_use' => $inUse,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'low_stock_count' => $lowStockCount,
                'kits_count' => $kitsCount,
                'ccu_count' => $ccuCount,
                'inspections_due' => $inspectionsDue,
            ];
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getWarehouseDashboardStats failed', [
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_items' => 0,
                'available' => 0,
                'in_quarantine' => 0,
                'in_use' => 0,
                'inbound' => 0,
                'outbound' => 0,
                'low_stock_count' => 0,
                'kits_count' => 0,
                'ccu_count' => 0,
                'inspections_due' => 0,
            ];
        }
    }

    public function getEquipmentStockLevel(int $equipmentId, ?int $warehouseId = null): array
    {
        try {
            $query = EquipmentTracking::where('equipment_trackings.inventory_equipment_id', $equipmentId);

            if ($warehouseId) {
                $query->where('equipment_trackings.warehouse_id', $warehouseId);
            }

            $byStatus = (clone $query)
                ->select(
                    'equipment_trackings.equipment_tracking_status_id',
                    DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
                )
                ->groupBy('equipment_trackings.equipment_tracking_status_id')
                ->pluck('net', 'equipment_tracking_status_id')
                ->toArray();

            $total = array_sum($byStatus);
            $available = $byStatus[self::STORED_AVAILABLE] ?? 0;
            $inUse = $byStatus[self::IN_USE] ?? 0;
            $inTransit = $byStatus[self::IN_TRANSIT] ?? 0;
            $inQuarantine = $byStatus[self::IN_QUARANTINE] ?? 0;
            $destroyed = $byStatus[self::DESTROYED] ?? 0;
            $missing = $byStatus[self::MISSING_LOST] ?? 0;

            $byWarehouse = EquipmentTracking::where('equipment_trackings.inventory_equipment_id', $equipmentId)
                ->select(
                    'equipment_trackings.warehouse_id',
                    DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
                )
                ->groupBy('equipment_trackings.warehouse_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->warehouse_id => (int) $row->net])
                ->toArray();

            $byCondition = WarehouseInventory::where('warehouse_inventories.inventory_equipment_id', $equipmentId)
                ->join('inventory_statuses', 'warehouse_inventories.inventory_status_id', '=', 'inventory_statuses.id')
                ->select('inventory_statuses.code', 'inventory_statuses.name', DB::raw('COUNT(*) as count'))
                ->groupBy('inventory_statuses.code', 'inventory_statuses.name')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'name' => $row->name,
                    'count' => (int) $row->count,
                ])
                ->toArray();

            return [
                'total' => $total,
                'available' => $available,
                'in_use' => $inUse,
                'in_transit' => $inTransit,
                'in_quarantine' => $inQuarantine,
                'destroyed' => $destroyed,
                'missing' => $missing,
                'by_warehouse' => $byWarehouse,
                'by_condition' => $byCondition,
            ];
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getEquipmentStockLevel failed', [
                'equipment_id' => $equipmentId,
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'available' => 0,
                'in_use' => 0,
                'in_transit' => 0,
                'in_quarantine' => 0,
                'destroyed' => 0,
                'missing' => 0,
                'by_warehouse' => [],
                'by_condition' => [],
            ];
        }
    }

    public function getBulkStockLevels(array $equipmentIds): array
    {
        try {
            if (empty($equipmentIds)) {
                return [];
            }

            $rows = EquipmentTracking::whereIn('equipment_trackings.inventory_equipment_id', $equipmentIds)
                ->select(
                    'equipment_trackings.inventory_equipment_id',
                    'equipment_trackings.equipment_tracking_status_id',
                    DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
                )
                ->groupBy('equipment_trackings.inventory_equipment_id', 'equipment_trackings.equipment_tracking_status_id')
                ->get();

            $result = [];
            foreach ($equipmentIds as $id) {
                $result[$id] = ['available' => 0, 'in_use' => 0, 'in_transit' => 0, 'total' => 0];
            }

            foreach ($rows as $row) {
                $eqId = $row->inventory_equipment_id;
                $net = (int) $row->net;

                if (!isset($result[$eqId])) {
                    $result[$eqId] = ['available' => 0, 'in_use' => 0, 'in_transit' => 0, 'total' => 0];
                }

                $result[$eqId]['total'] += $net;

                match ((int) $row->equipment_tracking_status_id) {
                    self::STORED_AVAILABLE => $result[$eqId]['available'] += $net,
                    self::IN_USE => $result[$eqId]['in_use'] += $net,
                    self::IN_TRANSIT => $result[$eqId]['in_transit'] += $net,
                    default => null,
                };
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getBulkStockLevels failed', [
                'equipment_ids' => $equipmentIds,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getDetailedStockBreakdownByWarehouse(int $warehouseId): array
    {
        try {
            $stockByEquipment = EquipmentTracking::where('equipment_trackings.warehouse_id', $warehouseId)
                ->select(
                    'equipment_trackings.inventory_equipment_id',
                    'equipment_trackings.equipment_tracking_status_id',
                    DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
                )
                ->groupBy('equipment_trackings.inventory_equipment_id', 'equipment_trackings.equipment_tracking_status_id')
                ->get();

            $equipmentIds = $stockByEquipment->pluck('inventory_equipment_id')->unique()->toArray();

            if (empty($equipmentIds)) {
                return [];
            }

            $equipment = InventoryEquipment::whereIn('id', $equipmentIds)
                ->select('id', 'name', 'part_number', 'reorder_point', 'reorder_quantity')
                ->get()
                ->keyBy('id');

            $overrides = WarehouseEquipmentStockSetting::where('warehouse_id', $warehouseId)
                ->whereIn('inventory_equipment_id', $equipmentIds)
                ->get()
                ->keyBy('inventory_equipment_id');

            $grouped = [];
            foreach ($stockByEquipment as $row) {
                $eqId = $row->inventory_equipment_id;
                if (!isset($grouped[$eqId])) {
                    $grouped[$eqId] = ['available' => 0, 'quarantine' => 0, 'in_use' => 0, 'total' => 0];
                }
                $net = (int) $row->net;
                $grouped[$eqId]['total'] += $net;

                match ((int) $row->equipment_tracking_status_id) {
                    self::STORED_AVAILABLE => $grouped[$eqId]['available'] += $net,
                    self::IN_QUARANTINE => $grouped[$eqId]['quarantine'] += $net,
                    self::IN_USE => $grouped[$eqId]['in_use'] += $net,
                    default => null,
                };
            }

            $result = [];
            foreach ($grouped as $eqId => $stock) {
                $eq = $equipment[$eqId] ?? null;
                if (!$eq) {
                    continue;
                }

                $override = $overrides[$eqId] ?? null;
                $reorderPoint = $override?->reorder_point ?? $eq->reorder_point;
                $reorderQuantity = $override?->reorder_quantity ?? $eq->reorder_quantity;

                $result[] = [
                    'equipment_name' => $eq->name,
                    'part_number' => $eq->part_number,
                    'available' => $stock['available'],
                    'quarantine' => $stock['quarantine'],
                    'in_use' => $stock['in_use'],
                    'total' => $stock['total'],
                    'reorder_point' => $reorderPoint,
                    'reorder_quantity' => $reorderQuantity,
                    'is_low_stock' => $stock['available'] < $reorderPoint,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getDetailedStockBreakdownByWarehouse failed', [
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getInboundOutbound(int $warehouseId): array
    {
        try {
            $latestTrackingSub = EquipmentTracking::select('warehouse_inventories_id', DB::raw('MAX(equipment_trackings.id) as max_id'))
                ->whereNotNull('equipment_trackings.warehouse_inventories_id')
                ->groupBy('warehouse_inventories_id');

            $inbound = DB::table('equipment_trackings')
                ->joinSub($latestTrackingSub, 'latest', function ($join) {
                    $join->on('equipment_trackings.id', '=', 'latest.max_id');
                })
                ->join('warehouse_inventories', 'equipment_trackings.warehouse_inventories_id', '=', 'warehouse_inventories.id')
                ->join('inventory_equipment', 'warehouse_inventories.inventory_equipment_id', '=', 'inventory_equipment.id')
                ->leftJoin('warehouses as from_wh', 'equipment_trackings.last_location_id', '=', 'from_wh.id')
                ->leftJoin('warehouses as to_wh', 'equipment_trackings.next_warehouse_id', '=', 'to_wh.id')
                ->where('equipment_trackings.next_warehouse_id', $warehouseId)
                ->whereIn('equipment_trackings.equipment_tracking_status_id', [self::IN_TRANSIT, self::OUT_FOR_DELIVERY])
                ->select(
                    'equipment_trackings.warehouse_inventories_id',
                    'inventory_equipment.name as equipment_name',
                    'warehouse_inventories.serial_number',
                    'from_wh.name as from_warehouse',
                    'to_wh.name as to_warehouse',
                    'equipment_trackings.created_at as date',
                )
                ->get()
                ->toArray();

            $outbound = DB::table('equipment_trackings')
                ->joinSub($latestTrackingSub, 'latest', function ($join) {
                    $join->on('equipment_trackings.id', '=', 'latest.max_id');
                })
                ->join('warehouse_inventories', 'equipment_trackings.warehouse_inventories_id', '=', 'warehouse_inventories.id')
                ->join('inventory_equipment', 'warehouse_inventories.inventory_equipment_id', '=', 'inventory_equipment.id')
                ->leftJoin('warehouses as from_wh', 'equipment_trackings.last_location_id', '=', 'from_wh.id')
                ->leftJoin('warehouses as to_wh', 'equipment_trackings.next_warehouse_id', '=', 'to_wh.id')
                ->where('equipment_trackings.last_location_id', $warehouseId)
                ->where('equipment_trackings.equipment_tracking_status_id', self::IN_TRANSIT)
                ->where('equipment_trackings.warehouse_id', '!=', $warehouseId)
                ->select(
                    'equipment_trackings.warehouse_inventories_id',
                    'inventory_equipment.name as equipment_name',
                    'warehouse_inventories.serial_number',
                    'from_wh.name as from_warehouse',
                    'to_wh.name as to_warehouse',
                    'equipment_trackings.created_at as date',
                )
                ->get()
                ->toArray();

            return [
                'inbound' => $inbound,
                'outbound' => $outbound,
            ];
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getInboundOutbound failed', [
                'warehouse_id' => $warehouseId,
                'error' => $e->getMessage(),
            ]);

            return ['inbound' => [], 'outbound' => []];
        }
    }

    public function getStockAtDate(int $warehouseId, string $date): array
    {
        try {
            $rows = EquipmentTracking::where('equipment_trackings.warehouse_id', $warehouseId)
                ->where('equipment_trackings.created_at', '<=', $date)
                ->select(
                    'equipment_trackings.equipment_tracking_status_id',
                    DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
                )
                ->groupBy('equipment_trackings.equipment_tracking_status_id')
                ->pluck('net', 'equipment_tracking_status_id')
                ->toArray();

            return [
                'total' => array_sum($rows),
                'available' => (int) ($rows[self::STORED_AVAILABLE] ?? 0),
                'in_use' => (int) ($rows[self::IN_USE] ?? 0),
                'in_quarantine' => (int) ($rows[self::IN_QUARANTINE] ?? 0),
                'in_transit' => (int) ($rows[self::IN_TRANSIT] ?? 0),
                'destroyed' => (int) ($rows[self::DESTROYED] ?? 0),
                'missing' => (int) ($rows[self::MISSING_LOST] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getStockAtDate failed', [
                'warehouse_id' => $warehouseId,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'available' => 0,
                'in_use' => 0,
                'in_quarantine' => 0,
                'in_transit' => 0,
                'destroyed' => 0,
                'missing' => 0,
            ];
        }
    }

    public function getSupplierDefectRate(int $supplierId): array
    {
        try {
            $total = WarehouseInventory::where('warehouse_inventories.supplier_id', $supplierId)->count();

            if ($total === 0) {
                return ['total' => 0, 'defective' => 0, 'defect_rate' => 0.0];
            }

            // Condition codes D=Poor, E=Quarantined, X=Lost/Stolen
            $defective = WarehouseInventory::where('warehouse_inventories.supplier_id', $supplierId)
                ->join('inventory_statuses', 'warehouse_inventories.inventory_status_id', '=', 'inventory_statuses.id')
                ->whereIn('inventory_statuses.code', ['D', 'E', 'X'])
                ->count();

            return [
                'total' => $total,
                'defective' => $defective,
                'defect_rate' => round(($defective / $total) * 100, 2),
            ];
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getSupplierDefectRate failed', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage(),
            ]);

            return ['total' => 0, 'defective' => 0, 'defect_rate' => 0.0];
        }
    }

    public function getMovementHistory(int $warehouseInventoryId): array
    {
        try {
            return EquipmentTracking::where('equipment_trackings.warehouse_inventories_id', $warehouseInventoryId)
                ->join('equipment_tracking_statuses', 'equipment_trackings.equipment_tracking_status_id', '=', 'equipment_tracking_statuses.id')
                ->leftJoin('warehouses', 'equipment_trackings.warehouse_id', '=', 'warehouses.id')
                ->leftJoin('users', 'equipment_trackings.authority_user_id', '=', 'users.id')
                ->select(
                    'equipment_trackings.*',
                    'equipment_tracking_statuses.name as status_name',
                    'warehouses.name as warehouse_name',
                    'users.name as user_name',
                )
                ->orderBy('equipment_trackings.created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('StockLevelService::getMovementHistory failed', [
                'warehouse_inventory_id' => $warehouseInventoryId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sum ledger (in - out - claimed) for a warehouse and specific tracking status.
     */
    protected function sumLedgerByStatus(int $warehouseId, int $statusId): int
    {
        $result = EquipmentTracking::where('equipment_trackings.warehouse_id', $warehouseId)
            ->where('equipment_trackings.equipment_tracking_status_id', $statusId)
            ->selectRaw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as net')
            ->value('net');

        return (int) $result;
    }

    /**
     * Count inbound items heading to this warehouse.
     */
    protected function countInbound(int $warehouseId): int
    {
        $latestTrackingSub = EquipmentTracking::select('warehouse_inventories_id', DB::raw('MAX(equipment_trackings.id) as max_id'))
            ->whereNotNull('equipment_trackings.warehouse_inventories_id')
            ->groupBy('warehouse_inventories_id');

        return DB::table('equipment_trackings')
            ->joinSub($latestTrackingSub, 'latest', function ($join) {
                $join->on('equipment_trackings.id', '=', 'latest.max_id');
            })
            ->where('equipment_trackings.next_warehouse_id', $warehouseId)
            ->whereIn('equipment_trackings.equipment_tracking_status_id', [self::IN_TRANSIT, self::OUT_FOR_DELIVERY])
            ->count();
    }

    /**
     * Count outbound items leaving this warehouse.
     */
    protected function countOutbound(int $warehouseId): int
    {
        $latestTrackingSub = EquipmentTracking::select('warehouse_inventories_id', DB::raw('MAX(equipment_trackings.id) as max_id'))
            ->whereNotNull('equipment_trackings.warehouse_inventories_id')
            ->groupBy('warehouse_inventories_id');

        return DB::table('equipment_trackings')
            ->joinSub($latestTrackingSub, 'latest', function ($join) {
                $join->on('equipment_trackings.id', '=', 'latest.max_id');
            })
            ->where('equipment_trackings.last_location_id', $warehouseId)
            ->where('equipment_trackings.equipment_tracking_status_id', self::IN_TRANSIT)
            ->where('equipment_trackings.warehouse_id', '!=', $warehouseId)
            ->count();
    }

    /**
     * Count equipment types where available stock is below reorder point.
     */
    protected function countLowStock(int $warehouseId): int
    {
        $stockByEquipment = EquipmentTracking::where('equipment_trackings.warehouse_id', $warehouseId)
            ->where('equipment_trackings.equipment_tracking_status_id', self::STORED_AVAILABLE)
            ->select(
                'equipment_trackings.inventory_equipment_id',
                DB::raw('COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) as available')
            )
            ->groupBy('equipment_trackings.inventory_equipment_id')
            ->get();

        if ($stockByEquipment->isEmpty()) {
            return 0;
        }

        $equipmentIds = $stockByEquipment->pluck('inventory_equipment_id')->toArray();

        $overrides = WarehouseEquipmentStockSetting::where('warehouse_id', $warehouseId)
            ->whereIn('inventory_equipment_id', $equipmentIds)
            ->pluck('reorder_point', 'inventory_equipment_id');

        $globals = InventoryEquipment::whereIn('id', $equipmentIds)
            ->pluck('reorder_point', 'id');

        $lowCount = 0;
        foreach ($stockByEquipment as $row) {
            $eqId = $row->inventory_equipment_id;
            $available = (int) $row->available;
            $reorderPoint = $overrides[$eqId] ?? $globals[$eqId] ?? 0;

            if ($available < $reorderPoint) {
                $lowCount++;
            }
        }

        return $lowCount;
    }
}
