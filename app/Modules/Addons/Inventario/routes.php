<?php

use App\Modules\Addons\Inventario\Controllers\InventoryItem\InventoryItemController;
use App\Modules\Addons\Inventario\Controllers\InventoryItemCustom\InventoryItemCustomController;
use App\Modules\Addons\Inventario\Controllers\InventoryItemCustom\InventoryItemCustomModelController;
use App\Modules\Addons\Inventario\Controllers\InventoryItemStock\InventoryItemStockController;
use App\Modules\Addons\Inventario\Controllers\InventoryItemType\InventoryItemTypeController;
use App\Modules\Addons\Inventario\Controllers\InventoryMovement\InventoryMovementController;
use App\Modules\Addons\Inventario\Controllers\InventoryStore\InventoryStoreController;
use App\Modules\Addons\Inventario\Controllers\StoreZone\StoreZoneController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-inventario.
 *
 * Inventory: items, types, stocks, movements, stores, zones, custom items.
 * Sub-namespaces internos preservados (uno por entidad) — el módulo es
 * grande y el sub-namespace ayuda al mantenimiento.
 *
 * URL prefix `inventory/*` preservado para compat con frontend.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('inventory')->group(function () {

    Route::prefix('inventory_item')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index']);
        Route::post('/add', [InventoryItemController::class, 'store']);
        Route::post('/add-custom', [InventoryItemController::class, 'storeCustom']);
        Route::get('/editar/{id}', [InventoryItemController::class, 'edit']);
        Route::post('/update/{id}', [InventoryItemController::class, 'update']);
        Route::post('/destroy/{id}', [InventoryItemController::class, 'destroy']);
        Route::post('/assign_to_user/{id}', [InventoryItemController::class, 'assignToUser']);
        Route::post('/change_store/{id}', [InventoryItemController::class, 'changeStore']);
        Route::post('/add_movement', [InventoryItemController::class, 'addMovement']);
        Route::post('/table', [InventoryItemController::class, 'table']);
    });

    Route::prefix('inventory_item_type')->group(function () {
        Route::get('/', [InventoryItemTypeController::class, 'index']);
        Route::post('/add', [InventoryItemTypeController::class, 'store']);
        Route::get('/editar/{id}', [InventoryItemTypeController::class, 'edit']);
        Route::post('/update/{id}', [InventoryItemTypeController::class, 'update']);
        Route::post('/destroy/{id}', [InventoryItemTypeController::class, 'destroy']);
        Route::post('/table', [InventoryItemTypeController::class, 'table']);
    });

    Route::prefix('inventory_item_stock')->group(function () {
        Route::get('/', [InventoryItemStockController::class, 'index']);
        Route::post('/add', [InventoryItemStockController::class, 'store']);
        Route::post('/change_stock', [InventoryItemStockController::class, 'changeStock']);
        Route::get('/editar/{id}', [InventoryItemStockController::class, 'edit']);
        Route::post('/update/{id}', [InventoryItemStockController::class, 'update']);
        Route::post('/destroy/{id}', [InventoryItemStockController::class, 'destroy']);
        Route::post('/table', [InventoryItemStockController::class, 'table']);
        Route::post('/get_items_by_user/{id}', [InventoryItemStockController::class, 'getItemsByUser']);
        Route::post('/accept_item_by_movement/{id}', [InventoryItemStockController::class, 'acceptItemByMovement']);
        Route::post('/reject_item_by_movement/{id}', [InventoryItemStockController::class, 'rejectItemByMovement']);
        Route::post('/get_items_by_store/{id}', [InventoryItemStockController::class, 'getItemsByStore']);
        Route::post('/get_items_by_client/{id}', [InventoryItemStockController::class, 'getItemsByClient']);
        Route::get('/get_media_by_item/{id}', [InventoryItemStockController::class, 'getMedia']);
        Route::post('/upload_media', [InventoryItemStockController::class, 'uploadMedia']);
        Route::delete('/delete_media/{id}', [InventoryItemStockController::class, 'deleteMedia']);
    });

    Route::prefix('inventory_movement')->group(function () {
        Route::get('/', [InventoryMovementController::class, 'index']);
        Route::post('/add', [InventoryMovementController::class, 'store']);
        Route::get('/editar/{id}', [InventoryMovementController::class, 'edit']);
        Route::post('/update/{id}', [InventoryMovementController::class, 'update']);
        Route::post('/destroy/{id}', [InventoryMovementController::class, 'destroy']);
        Route::post('/table', [InventoryMovementController::class, 'table']);
    });

    Route::prefix('inventory_store')->group(function () {
        Route::get('/', [InventoryStoreController::class, 'index']);
        Route::post('/add', [InventoryStoreController::class, 'store']);
        Route::get('/editar/{id}', [InventoryStoreController::class, 'edit']);
        Route::post('/update/{id}', [InventoryStoreController::class, 'update']);
        Route::post('/destroy/{id}', [InventoryStoreController::class, 'destroy']);
        Route::post('/table', [InventoryStoreController::class, 'table']);
        Route::get('/my-store/{id}', [InventoryStoreController::class, 'myStore'])->name('inventory.inventory_store.my-store');
        Route::get('/get-all', [InventoryStoreController::class, 'getAll']);
        Route::get('/get-by-id/{id}', [InventoryStoreController::class, 'getById']);
    });

    Route::prefix('store_zone')->group(function () {
        Route::get('/', [StoreZoneController::class, 'index']);
        Route::post('/add', [StoreZoneController::class, 'store']);
        Route::get('/editar/{id}', [StoreZoneController::class, 'edit']);
        Route::post('/update/{id}', [StoreZoneController::class, 'update']);
        Route::post('/destroy/{id}', [StoreZoneController::class, 'destroy']);
        Route::post('/table', [StoreZoneController::class, 'table']);
        Route::get('/get-store-zones-by-store/{id}', [StoreZoneController::class, 'getStoreZonesByStore']);
        Route::get('/show-zones-by-store/{id}', [StoreZoneController::class, 'showZonesByStore']);
        Route::get('/search', [StoreZoneController::class, 'search']);
        Route::get('/get-by-id/{id}', [StoreZoneController::class, 'getById']);
        Route::post('/update-zone', [StoreZoneController::class, 'updateZone']);
    });

    Route::prefix('inventory_item_custom_model')->group(function () {
        Route::get('/', [InventoryItemCustomModelController::class, 'index']);
        Route::post('/add', [InventoryItemCustomModelController::class, 'store']);
        Route::post('/table', [InventoryItemCustomModelController::class, 'table']);
    });

    Route::prefix('inventory_item_custom')->group(function () {
        Route::get('/items/{id}', [InventoryItemCustomController::class, 'getItemsByCustomModelId']);
        Route::post('/table', [InventoryItemCustomController::class, 'table']);
    });
});
