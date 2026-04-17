<?php

namespace App\Http\Requests\module\inventory\inventory_item_stock;

use App\Http\Requests\module\base\CrudModalValidationRequest;


class InventoryItemStockCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        return [
            'name' => 'required',
        ];
    }
    public function storeMessageRules()
    {
        return [
            'name.required' => 'El campo Nombre de Tipo de Articulo es obligatorio.',
        ];
    }
}
