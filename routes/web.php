<?php

use App\Http\Controllers\GroupRequirementController;
use App\Http\Controllers\InventoryEquipmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseInventoryController;
use App\Http\Controllers\WarehousesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Warehouses
    Route::get('/warehouses', [WarehousesController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/{id}', [WarehousesController::class, 'show'])->name('warehouses.show');
    Route::post('/warehouses', [WarehousesController::class, 'store'])->name('warehouses.store');
    Route::put('/warehouses/{id}', [WarehousesController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{id}', [WarehousesController::class, 'destroy'])->name('warehouses.destroy');
    Route::get('/warehouses/{id}/stats', [WarehousesController::class, 'dashboardStats'])->name('warehouses.stats');
    Route::get('/warehouse_stat_detail/{id}/{statType}', [WarehousesController::class, 'statDetailItems'])->name('warehouses.stat-detail');
    Route::post('/warehouse/global-search', [WarehousesController::class, 'globalSearch'])->name('warehouses.global-search');

    // Warehouse Inventory Operations
    Route::post('/warehouse/{warehouseId}/equipment', [WarehouseInventoryController::class, 'storeEquipment'])->name('inventory.store-equipment');
    Route::post('/warehouse/{warehouseId}/ppe', [WarehouseInventoryController::class, 'storeEquipmentPPE'])->name('inventory.store-ppe');
    Route::post('/warehouse/{warehouseId}/kit', [WarehouseInventoryController::class, 'storeKit'])->name('inventory.store-kit');
    Route::post('/warehouse/{warehouseId}/ccu', [WarehouseInventoryController::class, 'storeCcu'])->name('inventory.store-ccu');
    Route::post('/warehouse/{warehouseId}/book-out', [WarehouseInventoryController::class, 'bookOutEquipment'])->name('inventory.book-out');
    Route::post('/warehouse/{warehouseId}/receive', [WarehouseInventoryController::class, 'receiveEquipment'])->name('inventory.receive');
    Route::post('/warehouse/quarantine/approve/{id}', [WarehouseInventoryController::class, 'approveQuarantine'])->name('inventory.approve-quarantine');
    Route::post('/warehouse/quarantine/approve-bulk', [WarehouseInventoryController::class, 'approveQuarantineBulk'])->name('inventory.approve-quarantine-bulk');
    Route::put('/inventory/{id}/condition', [WarehouseInventoryController::class, 'updateCondition'])->name('inventory.update-condition');
    Route::post('/inventory/{id}/destroy', [WarehouseInventoryController::class, 'destroyItem'])->name('inventory.destroy');
    Route::post('/inventory/{id}/missing', [WarehouseInventoryController::class, 'markMissing'])->name('inventory.missing');
    Route::post('/rig/{rigId}/equipment', [WarehouseInventoryController::class, 'storeEquipmentOnRig'])->name('inventory.deploy-to-rig');
    Route::post('/rig/{rigId}/kit', [WarehouseInventoryController::class, 'storeKitOnRig'])->name('inventory.deploy-kit-to-rig');

    // Catalogue (Group Requirements)
    Route::get('/catalogue', [GroupRequirementController::class, 'index'])->name('catalogue.index');
    Route::get('/catalogue/search', [GroupRequirementController::class, 'search'])->name('catalogue.search');
    Route::get('/catalogue/{id}/children', [GroupRequirementController::class, 'children'])->name('catalogue.children');
    Route::post('/catalogue', [GroupRequirementController::class, 'store'])->name('catalogue.store');
    Route::put('/catalogue/{id}', [GroupRequirementController::class, 'update'])->name('catalogue.update');
    Route::delete('/catalogue/{id}', [GroupRequirementController::class, 'destroy'])->name('catalogue.destroy');

    // Equipment Registry
    Route::get('/equipment', [InventoryEquipmentController::class, 'index'])->name('equipment.index');
    Route::get('/equipment/{id}', [InventoryEquipmentController::class, 'show'])->name('equipment.show');
    Route::post('/equipment', [InventoryEquipmentController::class, 'store'])->name('equipment.store');
    Route::put('/equipment/{id}', [InventoryEquipmentController::class, 'update'])->name('equipment.update');
    Route::post('/equipment/{id}/stock-settings', [InventoryEquipmentController::class, 'stockSettings'])->name('equipment.stock-settings');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Internal RFQ Requests
    Route::get('/rfq/internal', [SupplierController::class, 'internalRequestsIndex'])->name('rfq.internal.index');
    Route::post('/rfq/internal/create', [SupplierController::class, 'storeInternalRequest'])->name('rfq.internal.store');
    Route::post('/rfq/internal/{id}/approve', [SupplierController::class, 'approveInternalRequest'])->name('rfq.internal.approve');
    Route::post('/rfq/internal/{id}/reject', [SupplierController::class, 'rejectInternalRequest'])->name('rfq.internal.reject');

    // Supplier RFQ Pipeline
    Route::get('/rfq', [SupplierController::class, 'rfqPipelineIndex'])->name('rfq.pipeline');
    Route::post('/rfq/create-from-internal/{internalRfqId}', [SupplierController::class, 'createRfqFromInternalRequest'])->name('rfq.create-from-internal');
    Route::get('/rfq/compare/{internalRfqId}', [SupplierController::class, 'compareQuotes'])->name('rfq.compare');
    Route::get('/rfq/{id}', [SupplierController::class, 'rfqShow'])->name('rfq.show');
    Route::post('/rfq/{id}/quote', [SupplierController::class, 'storeSupplierQuote'])->name('rfq.store-quote');
    Route::post('/rfq/{id}/select-winner', [SupplierController::class, 'selectWinningQuote'])->name('rfq.select-winner');
    Route::post('/rfq/{id}/upload-po', [SupplierController::class, 'uploadPO'])->name('rfq.upload-po');
    Route::post('/rfq/{id}/upload-file', [SupplierController::class, 'uploadFile'])->name('rfq.upload-file');

    // Admin
    Route::get('/admin/tracking-validation', [WarehouseInventoryController::class, 'trackingValidation'])->name('admin.tracking-validation');
    Route::post('/admin/tracking-validation/run', [WarehouseInventoryController::class, 'runValidation'])->name('admin.run-validation');
    Route::post('/admin/tracking-validation/fix/{checkName}', [WarehouseInventoryController::class, 'runFix'])->name('admin.run-fix');
});

require __DIR__.'/auth.php';
