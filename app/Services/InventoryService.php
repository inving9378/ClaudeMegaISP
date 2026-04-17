<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\InventoryItemStockRepository;
use App\Http\Repository\InventoryMovementRepository;
use App\Models\InventoryItemStock;
use App\Models\InventoryReservation;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function addMovementInventoryItemByType($inventoryItemId, $quantity, $type, $description, $movementableToId, $movementableToType, $movementableFromId, $movementableFromType, $storeZoneId = null, $isInitial = false, $status = 'pending')
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        $data = [
            'inventory_item_id' => $inventoryItemId,
            'quantity' => $quantity,
            'type' => $type,
            'description' => $description,
            'movementable_to_id' => $movementableToId,
            'movementable_to_type' => $movementableToType,
            'movementable_from_id' => $movementableFromId,
            'movementable_from_type' => $movementableFromType,
            'store_zone_id' => $storeZoneId,
            'is_initial' => $isInitial,
            'status' => $status
        ];
        return $inventoryMovementRepository->create_function($data);
    }



    public function updateInventoryStock($inventoryItemId, $quantity, $type, $movementableToId, $movementableToType, $movementableFromId, $movementableFromType, $storeZoneId = null, $isInitial = false)
    {
        try {
            if ($type == ComunConstantsController::INVENTORY_MOVEMENT_TYPE_ENTRADA) {
                $this->updateInventoryStockEntrada($inventoryItemId, $quantity, $movementableToId, $movementableToType, $isInitial);
                $this->updateQuantityInventoryStoreByZoneEntrada($inventoryItemId, $quantity, $movementableToId, $movementableToType, $storeZoneId, $isInitial);
            } else {
                $this->updateInventoryStockSalida($inventoryItemId, $quantity, $movementableFromId, $movementableFromType, $isInitial, $movementableToType);
                $this->updateQuantityInventoryStoreByZoneSalida($inventoryItemId, $quantity, $movementableFromId, $movementableFromType, $storeZoneId, $isInitial);
            }
        } catch (\Exception $e) {
            // Log del error para facilitar la depuración
            Log::error("Error en updateInventoryStock: " . $e->getMessage());
            throw $e; // Relanzar la excepción para que el llamador la maneje
        }
    }

    protected function updateInventoryStockEntrada($inventoryItemId, $quantity, $movementableToId, $movementableToType, $isInitial = false)
    {
        $inventoryStock = $this->findOrCreateInventoryStock($inventoryItemId, $movementableToId, $movementableToType);

        if ($inventoryStock->wasRecentlyCreated) {
            $mediaService = new InventoryItemMediaService();
            $mediaService->changeMediaToItemForItemStock($inventoryItemId, $inventoryStock->id);
        }
        // Aumentar el stock
        $inventoryStock->increment('current_stock', $quantity);
    }

    public function updateQuantityInventoryStoreByZoneEntrada($inventoryItemId, $quantity, $movementableToId, $movementableToType, $storeZoneId, $isInitial = false, $increment = true)
    {
        if ($storeZoneId && $movementableToType == 'App\Models\InventoryStore') {
            $inventoryStoreZone = $this->findOrCreateInventoryStoreZone($inventoryItemId, $storeZoneId, $movementableToId);
            if ($increment) {
                $inventoryStoreZone->increment('quantity', $quantity);
            }
        }
    }

    protected function updateInventoryStockSalida($inventoryItemId, $quantity, $movementableFromId, $movementableFromType, $isInitial = false)
    {
        if (!$isInitial) {
            $inventoryStock = $this->findInventoryStock($inventoryItemId, $movementableFromId, $movementableFromType);

            // Verificar si hay suficiente stock
            if ($inventoryStock->current_stock < $quantity) {
                throw new \Exception("No hay suficiente stock para realizar la salida. Stock disponible: {$inventoryStock->current_stock}, cantidad solicitada: {$quantity}.");
            }
            // Reducir el stock
            $inventoryStock->decrement('current_stock', $quantity);
            // Recargar el modelo para obtener los valores actualizados
            $inventoryStock->refresh();
            // Verificar si el stock llegó a 0 y eliminarlo en ese caso
            if ($inventoryStock->current_stock <= 0 && $movementableFromType != 'App\Models\InventoryStore') {
                $inventoryStock->delete();
            }
        }
    }

    protected function updateQuantityInventoryStoreByZoneSalida($inventoryItemId, $quantity, $movementableFromId, $movementableFromType, $storeZoneId, $isInitial = false)
    {

        if ($storeZoneId && $movementableFromType == 'App\Models\InventoryStore' && !$isInitial) {
            $inventoryStoreZone = $this->findOrCreateInventoryStoreZone($inventoryItemId, $storeZoneId, $movementableFromId);
            $inventoryStoreZone->decrement('quantity', $quantity);
        }
    }

    protected function findInventoryStock($inventoryItemId, $modelableId, $modelableType,)
    {
        $inventoryStock = \App\Models\InventoryItemStock::where([
            'inventory_item_id' => $inventoryItemId,
            'modelable_id' => $modelableId,
            'modelable_type' => $modelableType,
        ])->first();

        if (!$inventoryStock) {
            throw new \Exception("No existe registro de stock para este artículo en la ubicación especificada.");
        }

        return $inventoryStock;
    }

    protected function findOrCreateInventoryStock($inventoryItemId, $modelableId, $modelableType)
    {
        return \App\Models\InventoryItemStock::firstOrCreate(
            [
                'inventory_item_id' => $inventoryItemId,
                'modelable_id' => $modelableId,
                'modelable_type' => $modelableType,
            ],
            ['current_stock' => 0]
        );
    }




    public function findOrCreateInventoryStoreZone($inventoryItemId, $storeZoneId, $inventoryStoreId)
    {
        return \App\Models\InventoryItemStoreZone::firstOrCreate(
            [
                'inventory_item_id' => $inventoryItemId,
                'store_zone_id' => $storeZoneId,
                'inventory_store_id' => $inventoryStoreId,
            ],
            ['quantity' => 0] // Valor inicial si se crea un nuevo registro
        );
    }


    public function  getItemsPendingByUser($id)
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        return $inventoryMovementRepository->getItemsPendingByUser($id);
    }

    public function getItemsAcceptedByUser($id)
    {
        $inventoryMovementRepository = new InventoryItemStockRepository();
        return $inventoryMovementRepository->getItemsByUser($id);
    }


    public function getItemsAcceptedByClient($id)
    {
        $inventoryMovementRepository = new InventoryItemStockRepository();
        return $inventoryMovementRepository->getItemsByClient($id);
    }


    public function getLastActionsByUser($id)
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        return $inventoryMovementRepository->getLastActionsByUser($id);
    }


    public function  getItemsPendingByStore($id)
    {
        $inventoryMovementRepository = new InventoryMovementRepository();
        return $inventoryMovementRepository->getItemsPendingByStore($id);
    }

    public function getItemsAcceptedByStore($id)
    {
        $inventoryMovementRepository = new InventoryItemStockRepository();
        return $inventoryMovementRepository->getItemsByStore($id);
    }

    public function reserveStock($itemId, $quantity, $fromId, $fromType, $movementId)
    {
        // Solo registra la reserva (no modifica stock)
        InventoryReservation::create([
            'inventory_item_id' => $itemId,
            'movement_id' => $movementId,
            'quantity' => $quantity,
            'modelable_id' => $fromId,
            'modelable_type' => $fromType,
            'expires_at' => now()->addDays(5),
        ]);
    }


    public function releaseReservation($movementId)
    {
        // Elimina el registro de reserva
        InventoryReservation::where('movement_id', $movementId)->delete();
    }

    public function transferStock($itemId, $quantity, $fromId, $fromType, $toId, $toType)
    {
        // 1. Restar del origen
        $fromStock = InventoryItemStock::where([
            'inventory_item_id' => $itemId,
            'modelable_id' => $fromId,
            'modelable_type' => $fromType,
        ])->first();

        if ($fromStock) {
            if ($fromStock->current_stock < $quantity) {
                throw new \Exception("Stock insuficiente en origen.");
            }
            $fromStock->decrement('current_stock', $quantity);

            if ($fromStock->current_stock <= 0 && $fromType != 'App\Models\InventoryStore') {
                $fromStock->delete();
            } elseif ($fromStock->current_stock <= 0 && $fromType == 'App\Models\InventoryStore' && !is_null($fromStock->inventory_item->inventory_item_custom_model_id)) {
                $fromStock->delete();
            }
        }


        // 2. Sumar al destino
        $toStock = InventoryItemStock::firstOrCreate(
            [
                'inventory_item_id' => $itemId,
                'modelable_id' => $toId,
                'modelable_type' => $toType,
            ],
            ['current_stock' => 0]
        );
        $toStock->increment('current_stock', $quantity);
    }
}
