<?php

namespace App\Http\Requests\module\inventory\inventory_item;

use App\Http\Requests\module\base\CrudModalValidationRequest;


class InventoryItemCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        $rules = [
            'name' => 'required',
            'initial_stock' => 'required|min:1',
            'inventory_item_type_id' => 'required',
            'inventory_store_id' => 'required',
            'high_limit' => 'required|min:2',
            'middle_limit' => 'required|min:1'
        ];
        if ($request->serial_number_enable) {
            $rules['serial_number'] = 'required';
        }
        if ($request->status_item_enable) {
            $rules['status_item'] = 'required';
        }

        $rules['middle_limit'] = function ($attribute, $value, $fail) use ($request) {
            if ($value > $request->high_limit) {
                $fail('El límite medio no puede ser mayor que el límite alto.');
            }
        };

        return $rules;
    }
    public function storeMessageRules()
    {
        return [
            'name.required' => 'El campo Nombre de Artículo es obligatorio.',
            'initial_stock.required' => 'El campo cantidad inicial es obligatorio.',
            'initial_stock.min' => 'La cantidad inicial debe ser mayor o igual a 1.',
            'inventory_item_type_id.required' => 'El campo Tipo de Artículo es obligatorio.',
            'serial_number.required' => 'El campo Numero de Serie es obligatorio.',
            'status_item.required' => 'El campo Estado es obligatorio.',
            'inventory_store_id.required' => 'El campo Almacen es obligatorio',
            'middle_limit.required' => 'El limite medio es requerido.',
            'middle_limit.min' => 'El limite medio debe ser mayor o igual a 1.',
            'high_limit.required' => 'El limite alto es requerido.',
            'high_limit.min' => 'El limite alto debe ser mayor o igual a 2.'
        ];
    }
}
