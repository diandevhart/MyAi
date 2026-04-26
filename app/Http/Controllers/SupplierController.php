<?php

namespace App\Http\Controllers;

use App\Models\FilesSupplierQuoteRequestPo;
use App\Models\GroupRequirement;
use App\Models\InternalRfqRequest;
use App\Models\InternalRfqRequestItem;
use App\Models\InventoryEquipment;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Services\StockLevelService;
use App\Traits\GeneratesPartNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SupplierController extends Controller
{
    use GeneratesPartNumbers;

    public function __construct(
        protected StockLevelService $stockService
    ) {}

    // ─── SUPPLIER CRUD ───────────────────────────────────────

    public function index(Request $request): InertiaResponse
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $term = '%' . mb_strtolower($request->input('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(contact_person) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }

        $suppliers = $query->withCount('supplierQuoteRequests')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $suppliers->getCollection()->transform(function ($supplier) {
            $supplier->active_rfq_count = $supplier->supplierQuoteRequests()
                ->whereIn('status', ['draft', 'sent', 'quoted'])
                ->count();
            return $supplier;
        });

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(int $id): InertiaResponse
    {
        $supplier = Supplier::withCount('supplierQuoteRequests')->findOrFail($id);

        $orderHistory = SupplierQuoteRequest::where('supplier_id', $id)
            ->with(['internalRfqRequest', 'items'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $defectRate = $this->stockService->getSupplierDefectRate($id);

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'orderHistory' => $orderHistory,
            'defectRate' => $defectRate,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Supplier::create([
            ...$request->only([
                'name', 'contact_person', 'email', 'phone',
                'address', 'city', 'country', 'tax_number',
                'payment_terms', 'notes',
            ]),
            'is_active' => true,
        ]);

        return redirect()->route('suppliers.index');
    }

    public function update(Request $request, int $id)
    {
        $supplier = Supplier::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($request->only([
            'name', 'contact_person', 'email', 'phone',
            'address', 'city', 'country', 'tax_number',
            'payment_terms', 'is_active', 'rating', 'notes',
        ]));

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return redirect()->route('suppliers.index');
    }

    // ─── INTERNAL RFQ REQUESTS ───────────────────────────────

    public function internalRequestsIndex(Request $request): InertiaResponse
    {
        $query = InternalRfqRequest::with(['requester', 'warehouse', 'approver', 'items.inventoryEquipment'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('RFQ/InternalRequests', [
            'requests' => $requests,
            'filters' => $request->only(['status']),
        ]);
    }

    public function storeInternalRequest(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'urgency' => 'required|in:low,medium,high,critical',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.inventory_equipment_id' => 'nullable|exists:inventory_equipment,id',
            'items.*.new_item_name' => 'nullable|string|max:255',
            'items.*.new_item_description' => 'nullable|string',
            'items.*.new_item_unit' => 'nullable|string|max:50',
            'items.*.new_item_estimated_budget' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $rfqRequest = InternalRfqRequest::create([
                'requester_user_id' => Auth::id(),
                'warehouse_id' => $request->input('warehouse_id'),
                'urgency' => $request->input('urgency'),
                'status' => 'pending',
                'notes' => $request->input('notes'),
            ]);

            foreach ($request->input('items') as $item) {
                InternalRfqRequestItem::create([
                    'internal_rfq_request_id' => $rfqRequest->id,
                    'inventory_equipment_id' => $item['inventory_equipment_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'new_item_name' => $item['new_item_name'] ?? null,
                    'new_item_description' => $item['new_item_description'] ?? null,
                    'new_item_unit' => $item['new_item_unit'] ?? null,
                    'new_item_estimated_budget' => $item['new_item_estimated_budget'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('rfq.internal.index');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeInternalRequest failed', ['error' => $e->getMessage()]);
            return redirect()->back()->withErrors(['message' => 'Failed to create internal request.']);
        }
    }

    public function approveInternalRequest(int $id): JsonResponse
    {
        $rfqRequest = InternalRfqRequest::findOrFail($id);

        if ($rfqRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $rfqRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Internal request approved.']);
    }

    public function rejectInternalRequest(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $rfqRequest = InternalRfqRequest::findOrFail($id);

        if ($rfqRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $rfqRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json(['message' => 'Internal request rejected.']);
    }

    // ─── SUPPLIER RFQ ────────────────────────────────────────

    public function rfqPipelineIndex(Request $request): InertiaResponse
    {
        $query = SupplierQuoteRequest::with(['supplier', 'internalRfqRequest'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rfqs = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('RFQ/Pipeline', [
            'rfqs' => $rfqs,
            'filters' => $request->only(['status']),
            'approvedInternalRequests' => InternalRfqRequest::where('status', 'approved')
                ->with(['requester', 'warehouse', 'items'])
                ->orderByDesc('created_at')
                ->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function createRfqFromInternalRequest(Request $request, int $internalRfqId): JsonResponse
    {
        $request->validate([
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        $internalRequest = InternalRfqRequest::with('items')->findOrFail($internalRfqId);

        if ($internalRequest->status !== 'approved') {
            return response()->json(['message' => 'Internal request must be approved first.'], 422);
        }

        $createdIds = [];

        try {
            DB::beginTransaction();

            foreach ($request->input('supplier_ids') as $supplierId) {
                $rfqNumber = $this->generateRfqNumber();

                $rfq = SupplierQuoteRequest::create([
                    'internal_rfq_request_id' => $internalRequest->id,
                    'supplier_id' => $supplierId,
                    'rfq_number' => $rfqNumber,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                foreach ($internalRequest->items as $item) {
                    SupplierQuoteRequestItem::create([
                        'supplier_quote_request_id' => $rfq->id,
                        'inventory_equipment_id' => $item->inventory_equipment_id,
                        'quantity' => $item->quantity,
                        'new_item_name' => $item->new_item_name,
                        'new_item_description' => $item->new_item_description,
                    ]);
                }

                $createdIds[] = $rfq->id;
            }

            DB::commit();

            return response()->json([
                'message' => count($createdIds) . ' RFQ(s) created and sent.',
                'rfq_ids' => $createdIds,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('createRfqFromInternalRequest failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create supplier RFQs.'], 500);
        }
    }

    public function rfqShow(int $id): InertiaResponse
    {
        $rfq = SupplierQuoteRequest::with([
            'supplier',
            'internalRfqRequest.requester',
            'items.inventoryEquipment',
            'files',
        ])->findOrFail($id);

        return Inertia::render('RFQ/Show', [
            'rfq' => $rfq,
        ]);
    }

    public function storeSupplierQuote(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.supplier_quote_request_item_id' => 'required|exists:supplier_quote_request_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.lead_time_days' => 'nullable|integer|min:0',
            'items.*.notes' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $rfq = SupplierQuoteRequest::findOrFail($id);

        try {
            DB::beginTransaction();

            foreach ($request->input('items') as $itemData) {
                $item = SupplierQuoteRequestItem::where('id', $itemData['supplier_quote_request_item_id'])
                    ->where('supplier_quote_request_id', $rfq->id)
                    ->firstOrFail();

                $item->update([
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['unit_price'] * $item->quantity,
                    'lead_time_days' => $itemData['lead_time_days'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $updateData = ['status' => 'quoted'];
            if ($request->filled('due_date')) {
                $updateData['due_date'] = $request->input('due_date');
            }
            $rfq->update($updateData);

            DB::commit();

            return response()->json(['message' => 'Supplier quote recorded.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeSupplierQuote failed', ['rfq_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to record quote.'], 500);
        }
    }

    public function compareQuotes(int $internalRfqId): InertiaResponse
    {
        $internalRequest = InternalRfqRequest::with('items.inventoryEquipment')
            ->findOrFail($internalRfqId);

        $supplierQuotes = SupplierQuoteRequest::where('internal_rfq_request_id', $internalRfqId)
            ->with(['supplier', 'items'])
            ->get();

        // Build comparison matrix: items (rows) × suppliers (columns)
        $comparison = [];
        foreach ($internalRequest->items as $requestItem) {
            $row = [
                'item_id' => $requestItem->id,
                'name' => $requestItem->inventoryEquipment
                    ? $requestItem->inventoryEquipment->name
                    : $requestItem->new_item_name,
                'quantity' => $requestItem->quantity,
                'suppliers' => [],
            ];

            foreach ($supplierQuotes as $quote) {
                $quoteItem = $quote->items->first(function ($qi) use ($requestItem) {
                    if ($requestItem->inventory_equipment_id) {
                        return $qi->inventory_equipment_id === $requestItem->inventory_equipment_id;
                    }
                    return $qi->new_item_name === $requestItem->new_item_name;
                });

                $row['suppliers'][] = [
                    'supplier_id' => $quote->supplier_id,
                    'supplier_name' => $quote->supplier->name,
                    'rfq_id' => $quote->id,
                    'rfq_number' => $quote->rfq_number,
                    'status' => $quote->status,
                    'unit_price' => $quoteItem?->unit_price,
                    'total_price' => $quoteItem?->total_price,
                    'lead_time_days' => $quoteItem?->lead_time_days,
                    'notes' => $quoteItem?->notes,
                ];
            }

            $comparison[] = $row;
        }

        return Inertia::render('RFQ/Compare', [
            'internalRequest' => $internalRequest,
            'supplierQuotes' => $supplierQuotes,
            'comparison' => $comparison,
        ]);
    }

    public function selectWinningQuote(int $id): JsonResponse
    {
        $rfq = SupplierQuoteRequest::findOrFail($id);

        if (!in_array($rfq->status, ['quoted', 'sent'])) {
            return response()->json(['message' => 'Only quoted or sent RFQs can be awarded.'], 422);
        }

        try {
            DB::beginTransaction();

            $rfq->update([
                'status' => 'awarded',
                'awarded_at' => now(),
            ]);

            // Cancel all other RFQs for the same internal request
            SupplierQuoteRequest::where('internal_rfq_request_id', $rfq->internal_rfq_request_id)
                ->where('id', '!=', $rfq->id)
                ->whereNotIn('status', ['cancelled'])
                ->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json(['message' => 'RFQ awarded to supplier.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('selectWinningQuote failed', ['rfq_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to award RFQ.'], 500);
        }
    }

    public function uploadPO(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'notes' => 'nullable|string',
        ]);

        $rfq = SupplierQuoteRequest::with('items')->findOrFail($id);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $path = $file->store('po-files', 'public');

            $poFile = FilesSupplierQuoteRequestPo::create([
                'supplier_quote_request_id' => $rfq->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => 'po',
                'uploaded_by' => Auth::id(),
            ]);

            // Auto-create equipment for new items
            $createdEquipment = [];
            $newPurchasesGroup = GroupRequirement::where('name', 'New Purchases')->first();

            foreach ($rfq->items as $rfqItem) {
                if ($rfqItem->inventory_equipment_id === null && $rfqItem->new_item_name) {
                    $groupId = $newPurchasesGroup?->id;

                    $equipment = InventoryEquipment::create([
                        'name' => $rfqItem->new_item_name,
                        'group_requirement_id' => $groupId,
                        'type' => 'item',
                        'description' => $rfqItem->new_item_description,
                    ]);

                    // Update the supplier quote request item
                    $rfqItem->update(['inventory_equipment_id' => $equipment->id]);

                    // Also update the original internal request item
                    $internalItem = InternalRfqRequestItem::where('internal_rfq_request_id', $rfq->internal_rfq_request_id)
                        ->where('new_item_name', $rfqItem->new_item_name)
                        ->whereNull('inventory_equipment_id')
                        ->first();

                    if ($internalItem) {
                        $internalItem->update(['inventory_equipment_id' => $equipment->id]);
                    }

                    $createdEquipment[] = $equipment;
                }
            }

            if ($request->filled('notes')) {
                $rfq->update(['notes' => $request->input('notes')]);
            }

            DB::commit();

            return response()->json([
                'message' => 'PO uploaded successfully.',
                'file' => $poFile,
                'created_equipment' => $createdEquipment,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('uploadPO failed', ['rfq_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to upload PO.'], 500);
        }
    }

    public function uploadFile(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'file_type' => 'required|in:quote,invoice,attachment',
        ]);

        $rfq = SupplierQuoteRequest::findOrFail($id);

        $file = $request->file('file');
        $path = $file->store('rfq-files', 'public');

        $record = FilesSupplierQuoteRequestPo::create([
            'supplier_quote_request_id' => $rfq->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $request->input('file_type'),
            'uploaded_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'file' => $record,
        ]);
    }

    // ─── HELPERS ─────────────────────────────────────────────

    protected function generateRfqNumber(): string
    {
        $datePrefix = 'RFQ-' . now()->format('Ymd') . '-';

        $lastToday = SupplierQuoteRequest::where('rfq_number', 'like', $datePrefix . '%')
            ->orderByDesc('rfq_number')
            ->value('rfq_number');

        if ($lastToday) {
            $lastSeq = (int) substr($lastToday, -4);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $datePrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
