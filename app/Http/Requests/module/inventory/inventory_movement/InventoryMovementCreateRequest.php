<?php

namespace App\Http\Requests\module\inventory\inventory_movement;

use App\Http\Repository\InventoryItemRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;


class InventoryMovementCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        $rules = [
            'inventory_item_id' => 'required',
            'type' => 'required|in:Entrada,Salida',
            'quantity' => 'required|min:1',
        ];

        // Convertir valores de 'user_id_enable' e 'inventory_store_id_enable' en booleanos
        $userIdEnable = filter_var($request->user_id_enable, FILTER_VALIDATE_BOOLEAN);
        $inventoryStoreIdEnable = filter_var($request->inventory_store_id_enable, FILTER_VALIDATE_BOOLEAN);

        // Si el tipo es 'Salida' o 'Entrada', debe tener un proveedor
        if (!empty($request->type) && !$userIdEnable && !$inventoryStoreIdEnable) {
            $rules['user_id_enable'] = 'required|accepted';
            $rules['inventory_store_id_enable'] = 'required|accepted';
        }

        // Si es salida, verificar que el artículo tenga stock suficiente
        if ($request->type === 'Salida') {
            $inventoryItemRepository = new InventoryItemRepository();
            $inventoryItem = $inventoryItemRepository->getModelById($request->inventory_item_id);
            $rules['quantity'] = 'lte:' . $inventoryItem->current_stock;
        }

        // Si el proveedor es un usuario, debe tener un usuario
        if ($userIdEnable) {
            $rules['user_id'] = 'required';
        }

        // Si el proveedor es un almacén, debe tener un almacén
        if ($inventoryStoreIdEnable) {
            $rules['inventory_store_id'] = 'required';
        }

        return $rules;
    }

    public function storeMessageRules()
    {
        $request = request();
        $inventoryItemRepository = new InventoryItemRepository();
        $inventoryItem = $inventoryItemRepository->getModelById($request->inventory_item_id);
        $actualStock = $inventoryItem->current_stock;
        return [
            'inventory_item_id.required' => 'El campo Articulo es obligatorio.',
            'type.required' => 'El campo Tipo es obligatorio.',
            'type.in' => 'El campo Tipo debe ser Entrada o Salida.',
            'quantity.required' => 'El campo Cantidad es obligatorio.',
            'quantity.min' => 'La cantidad debe ser mayor o igual a 1.',
            'user_id_enable.required' => 'Debe seleccionar un proveedor.',
            'inventory_store_id_enable.required' => 'Debe seleccionar un proveedor.',
            'user_id.required' => 'Debe seleccionar un usuario.',
            'inventory_store_id.required' => 'Debe seleccionar un almacen.',
            'quantity.lte' => 'La cantidad debe ser menor o igual al stock disponible. Actual: ' . $actualStock,
        ];
    }
}
