<?php

namespace App\Services;

use App\Models\EquipmentTracking;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EquipmentTrackingValidationService
{
    protected const REGISTERED = 1;
    protected const IN_TRANSIT = 3;
    protected const IN_USE = 4;
    protected const IN_QUARANTINE = 5;
    protected const STORED_AVAILABLE = 6;
    protected const DESTROYED = 7;
    protected const DELIVERED = 10;

    public function runAllChecks(): array
    {
        return [
            $this->checkNegativeStock(),
            $this->checkDoubleCountedStock(),
            $this->checkOrphanedInTransit(),
            $this->checkStuckInTransit(),
            $this->checkMissingLedgerFields(),
            $this->checkGhostItems(),
            $this->checkNegativeWarehouseStock(),
            $this->checkBookedOutNeverReceived(),
        ];
    }

    protected function checkNegativeStock(): array
    {
        try {
            $items = DB::select('
                SELECT warehouse_inventories_id,
                       COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) AS net
                FROM equipment_trackings
                WHERE warehouse_inventories_id IS NOT NULL
                GROUP BY warehouse_inventories_id
                HAVING COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) < 0
            ');

            return [
                'name' => 'Negative Stock Per Item',
                'severity' => 'critical',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                    'net_stock' => (int) $row->net,
                ], $items),
                'fixable' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('checkNegativeStock failed', ['error' => $e->getMessage()]);
            return ['name' => 'Negative Stock Per Item', 'severity' => 'critical', 'count' => -1, 'items' => [], 'fixable' => false];
        }
    }

    protected function checkDoubleCountedStock(): array
    {
        try {
            $items = DB::select('
                WITH ordered_tracking AS (
                    SELECT id, warehouse_inventories_id, equipment_tracking_status_id,
                           "in", "out", claimed, created_at,
                           LAG(equipment_tracking_status_id) OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_status,
                           LAG("in") OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_in,
                           LAG("out") OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_out
                    FROM equipment_trackings
                    WHERE warehouse_inventories_id IS NOT NULL
                )
                SELECT id, warehouse_inventories_id, equipment_tracking_status_id,
                       prev_status, "in", prev_in, prev_out
                FROM ordered_tracking
                WHERE "in" = 1 AND prev_in = 1 AND (prev_out IS NULL OR prev_out = 0)
                  AND prev_status != equipment_tracking_status_id
            ');

            return [
                'name' => 'Double-Counted Stock',
                'severity' => 'critical',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'tracking_id' => $row->id,
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                    'status_id' => $row->equipment_tracking_status_id,
                    'prev_status' => $row->prev_status,
                ], $items),
                'fixable' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('checkDoubleCountedStock failed', ['error' => $e->getMessage()]);
            return ['name' => 'Double-Counted Stock', 'severity' => 'critical', 'count' => -1, 'items' => [], 'fixable' => true];
        }
    }

    protected function checkOrphanedInTransit(): array
    {
        try {
            $latestSub = DB::select('
                SELECT et.warehouse_inventories_id, et.equipment_tracking_status_id, et.next_warehouse_id
                FROM equipment_trackings et
                INNER JOIN (
                    SELECT warehouse_inventories_id, MAX(id) AS max_id
                    FROM equipment_trackings
                    WHERE warehouse_inventories_id IS NOT NULL
                    GROUP BY warehouse_inventories_id
                ) latest ON et.id = latest.max_id
                WHERE et.equipment_tracking_status_id = ?
                  AND et.next_warehouse_id IS NULL
            ', [self::IN_TRANSIT]);

            return [
                'name' => 'Orphaned In-Transit (No Destination)',
                'severity' => 'warning',
                'count' => count($latestSub),
                'items' => array_map(fn ($row) => [
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                ], $latestSub),
                'fixable' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('checkOrphanedInTransit failed', ['error' => $e->getMessage()]);
            return ['name' => 'Orphaned In-Transit (No Destination)', 'severity' => 'warning', 'count' => -1, 'items' => [], 'fixable' => false];
        }
    }

    protected function checkStuckInTransit(): array
    {
        try {
            $items = DB::select('
                SELECT et.warehouse_inventories_id, et.created_at, et.next_warehouse_id
                FROM equipment_trackings et
                INNER JOIN (
                    SELECT warehouse_inventories_id, MAX(id) AS max_id
                    FROM equipment_trackings
                    WHERE warehouse_inventories_id IS NOT NULL
                    GROUP BY warehouse_inventories_id
                ) latest ON et.id = latest.max_id
                WHERE et.equipment_tracking_status_id = ?
                  AND et.created_at < NOW() - INTERVAL \'30 days\'
            ', [self::IN_TRANSIT]);

            return [
                'name' => 'Stuck In Transit 30+ Days',
                'severity' => 'warning',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                    'transit_since' => $row->created_at,
                    'next_warehouse_id' => $row->next_warehouse_id,
                ], $items),
                'fixable' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('checkStuckInTransit failed', ['error' => $e->getMessage()]);
            return ['name' => 'Stuck In Transit 30+ Days', 'severity' => 'warning', 'count' => -1, 'items' => [], 'fixable' => false];
        }
    }

    protected function checkMissingLedgerFields(): array
    {
        try {
            $items = DB::select('
                SELECT id, warehouse_inventories_id, equipment_tracking_status_id, created_at
                FROM equipment_trackings
                WHERE "in" = 0 AND "out" = 0 AND claimed = 0
                  AND equipment_tracking_status_id != ?
            ', [self::REGISTERED]);

            return [
                'name' => 'Missing Ledger Fields (in/out/claimed all zero)',
                'severity' => 'warning',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'tracking_id' => $row->id,
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                    'status_id' => $row->equipment_tracking_status_id,
                ], $items),
                'fixable' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('checkMissingLedgerFields failed', ['error' => $e->getMessage()]);
            return ['name' => 'Missing Ledger Fields (in/out/claimed all zero)', 'severity' => 'warning', 'count' => -1, 'items' => [], 'fixable' => true];
        }
    }

    protected function checkGhostItems(): array
    {
        try {
            $items = DB::select('
                SELECT wi.id, wi.inventory_equipment_id, wi.warehouse_id
                FROM warehouse_inventories wi
                LEFT JOIN equipment_trackings et ON et.warehouse_inventories_id = wi.id
                WHERE et.id IS NULL AND wi.deleted_at IS NULL
            ');

            return [
                'name' => 'Ghost Items (No Tracking Records)',
                'severity' => 'info',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'warehouse_inventories_id' => $row->id,
                    'inventory_equipment_id' => $row->inventory_equipment_id,
                    'warehouse_id' => $row->warehouse_id,
                ], $items),
                'fixable' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('checkGhostItems failed', ['error' => $e->getMessage()]);
            return ['name' => 'Ghost Items (No Tracking Records)', 'severity' => 'info', 'count' => -1, 'items' => [], 'fixable' => true];
        }
    }

    protected function checkNegativeWarehouseStock(): array
    {
        try {
            $items = DB::select('
                SELECT warehouse_id,
                       COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) AS net
                FROM equipment_trackings
                WHERE warehouse_id IS NOT NULL
                GROUP BY warehouse_id
                HAVING COALESCE(SUM("in"), 0) - COALESCE(SUM("out"), 0) - COALESCE(SUM(claimed), 0) < 0
            ');

            return [
                'name' => 'Negative Warehouse Stock',
                'severity' => 'critical',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'warehouse_id' => $row->warehouse_id,
                    'net_stock' => (int) $row->net,
                ], $items),
                'fixable' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('checkNegativeWarehouseStock failed', ['error' => $e->getMessage()]);
            return ['name' => 'Negative Warehouse Stock', 'severity' => 'critical', 'count' => -1, 'items' => [], 'fixable' => false];
        }
    }

    protected function checkBookedOutNeverReceived(): array
    {
        try {
            $items = DB::select('
                SELECT et_out.id AS tracking_id,
                       et_out.warehouse_inventories_id,
                       et_out.warehouse_id AS source_warehouse_id,
                       et_out.next_warehouse_id AS dest_warehouse_id,
                       et_out.book_out_date
                FROM equipment_trackings et_out
                WHERE et_out."out" = 1
                  AND et_out.next_warehouse_id IS NOT NULL
                  AND et_out.book_out_date < NOW() - INTERVAL \'7 days\'
                  AND NOT EXISTS (
                      SELECT 1 FROM equipment_trackings et_in
                      WHERE et_in.warehouse_inventories_id = et_out.warehouse_inventories_id
                        AND et_in."in" = 1
                        AND et_in.warehouse_id = et_out.next_warehouse_id
                        AND et_in.created_at > et_out.created_at
                  )
            ');

            return [
                'name' => 'Booked Out Never Received (7+ Days)',
                'severity' => 'warning',
                'count' => count($items),
                'items' => array_map(fn ($row) => [
                    'tracking_id' => $row->tracking_id,
                    'warehouse_inventories_id' => $row->warehouse_inventories_id,
                    'source_warehouse_id' => $row->source_warehouse_id,
                    'dest_warehouse_id' => $row->dest_warehouse_id,
                    'book_out_date' => $row->book_out_date,
                ], $items),
                'fixable' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('checkBookedOutNeverReceived failed', ['error' => $e->getMessage()]);
            return ['name' => 'Booked Out Never Received (7+ Days)', 'severity' => 'warning', 'count' => -1, 'items' => [], 'fixable' => false];
        }
    }

    public function fixDoubleCountedStock(): array
    {
        try {
            // Insert balancing out=1 rows for the previous status where double-counting occurred
            $affected = DB::affectingStatement('
                INSERT INTO equipment_trackings (
                    warehouse_inventories_id, inventory_equipment_id, equipment_tracking_status_id,
                    warehouse_id, rig_id, authority_user_id,
                    "in", "out", claimed, notes, created_at, updated_at
                )
                SELECT ot.warehouse_inventories_id, ot.inventory_equipment_id, ot.prev_status,
                       ot.warehouse_id, ot.rig_id, ot.authority_user_id,
                       0, 1, 0, \'Auto-fix: balancing out for double-counted stock\', NOW(), NOW()
                FROM (
                    SELECT id, warehouse_inventories_id, inventory_equipment_id,
                           equipment_tracking_status_id, warehouse_id, rig_id, authority_user_id,
                           "in", "out",
                           LAG(equipment_tracking_status_id) OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_status,
                           LAG("in") OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_in,
                           LAG("out") OVER (
                               PARTITION BY warehouse_inventories_id ORDER BY created_at, id
                           ) AS prev_out
                    FROM equipment_trackings
                    WHERE warehouse_inventories_id IS NOT NULL
                ) ot
                WHERE ot."in" = 1 AND ot.prev_in = 1 AND (ot.prev_out IS NULL OR ot.prev_out = 0)
                  AND ot.prev_status != ot.equipment_tracking_status_id
            '); //IOC IMPLEMENTED

            return ['fixed_count' => $affected, 'message' => "Inserted {$affected} balancing out=1 rows."];
        } catch (\Throwable $e) {
            Log::error('fixDoubleCountedStock failed', ['error' => $e->getMessage()]);
            return ['fixed_count' => 0, 'message' => 'Fix failed: ' . $e->getMessage()];
        }
    }

    public function fixMissingLedgerFields(): array
    {
        try {
            // Arrival statuses get in=1
            $arrivalStatuses = [self::IN_QUARANTINE, self::STORED_AVAILABLE, self::IN_USE, self::DELIVERED];
            $arrivalFixed = DB::update('
                UPDATE equipment_trackings
                SET "in" = 1
                WHERE "in" = 0 AND "out" = 0 AND claimed = 0
                  AND equipment_tracking_status_id IN (' . implode(',', $arrivalStatuses) . ')
            '); //IOC IMPLEMENTED

            // Departure statuses get out=1
            $departureStatuses = [self::IN_TRANSIT, self::DESTROYED];
            $departureFixed = DB::update('
                UPDATE equipment_trackings
                SET "out" = 1
                WHERE "in" = 0 AND "out" = 0 AND claimed = 0
                  AND equipment_tracking_status_id IN (' . implode(',', $departureStatuses) . ')
            '); //IOC IMPLEMENTED

            $total = $arrivalFixed + $departureFixed;

            return ['fixed_count' => $total, 'message' => "Fixed {$arrivalFixed} arrival rows (in=1), {$departureFixed} departure rows (out=1)."];
        } catch (\Throwable $e) {
            Log::error('fixMissingLedgerFields failed', ['error' => $e->getMessage()]);
            return ['fixed_count' => 0, 'message' => 'Fix failed: ' . $e->getMessage()];
        }
    }

    public function fixGhostItems(): array
    {
        try {
            $userId = Auth::id() ?? 1;

            $affected = DB::affectingStatement('
                INSERT INTO equipment_trackings (
                    warehouse_inventories_id, inventory_equipment_id, equipment_tracking_status_id,
                    warehouse_id, authority_user_id,
                    "in", "out", claimed, notes, created_at, updated_at
                )
                SELECT wi.id, wi.inventory_equipment_id, ?,
                       wi.warehouse_id, ?,
                       0, 0, 0, \'Auto-fix: initial registration for ghost item\', NOW(), NOW()
                FROM warehouse_inventories wi
                LEFT JOIN equipment_trackings et ON et.warehouse_inventories_id = wi.id
                WHERE et.id IS NULL AND wi.deleted_at IS NULL
            ', [self::REGISTERED, $userId]); //IOC IMPLEMENTED

            return ['fixed_count' => $affected, 'message' => "Created {$affected} initial registration tracking rows."];
        } catch (\Throwable $e) {
            Log::error('fixGhostItems failed', ['error' => $e->getMessage()]);
            return ['fixed_count' => 0, 'message' => 'Fix failed: ' . $e->getMessage()];
        }
    }
}
