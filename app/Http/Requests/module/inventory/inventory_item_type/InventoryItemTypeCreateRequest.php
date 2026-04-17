<?php

namespace App\Http\Requests\module\inventory\inventory_item_type;

use App\Http\Repository\InventoryItemRepository;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Foundation\Http\FormRequest;


class InventoryItemTypeCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        return [
            'name' => 'required',
            'type' => 'required'
        ];
    }
    public function storeMessageRules()
    {
        return [
            'name.required' => 'El campo Nombre de Tipo de Articulo es obligatorio.',
        ];
    }
}
