<?php

namespace App\Http\Requests\module\inventory\supplier_product_price;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class SupplierProductPriceCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        return [
            'supplier_id'       => 'required|exists:suppliers,id',
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'base_price'        => 'required|numeric|min:0',
            'bulk_price'        => 'nullable|numeric|min:0',
            'bulk_min_quantity' => 'nullable|integer|min:1',
            'is_active'         => 'required|boolean',
        ];
    }

    public function storeMessageRules()
    {
        return [
            'supplier_id.required'       => 'El proveedor es obligatorio.',
            'supplier_id.exists'         => 'El proveedor seleccionado no existe.',
            'inventory_item_id.required' => 'El artículo es obligatorio.',
            'inventory_item_id.exists'   => 'El artículo seleccionado no existe.',
            'base_price.required'        => 'El precio base es obligatorio.',
            'base_price.numeric'         => 'El precio base debe ser un número.',
            'base_price.min'             => 'El precio base debe ser mayor o igual a 0.',
            'bulk_price.numeric'         => 'El precio por volumen debe ser un número.',
            'bulk_price.min'             => 'El precio por volumen debe ser mayor o igual a 0.',
            'bulk_min_quantity.integer'  => 'La cantidad mínima debe ser un número entero.',
            'bulk_min_quantity.min'      => 'La cantidad mínima debe ser mayor o igual a 1.',
            'is_active.required'         => 'El estado es obligatorio.',
            'is_active.boolean'          => 'El estado debe ser verdadero o falso.',
        ];
    }

    public function updateRules()
    {
        return [
            'base_price'        => 'required|numeric|min:0',
            'bulk_price'        => 'nullable|numeric|min:0',
            'bulk_min_quantity' => 'nullable|integer|min:1',
            'is_active'         => 'nullable|boolean',
        ];
    }

    public function updateMessageRules()
    {
        return [
            'base_price.required'       => 'El precio base es obligatorio.',
            'base_price.numeric'        => 'El precio base debe ser un número.',
            'base_price.min'            => 'El precio base debe ser mayor o igual a 0.',
            'bulk_price.numeric'        => 'El precio por volumen debe ser un número.',
            'bulk_price.min'            => 'El precio por volumen debe ser mayor o igual a 0.',
            'bulk_min_quantity.integer' => 'La cantidad mínima debe ser un número entero.',
            'bulk_min_quantity.min'     => 'La cantidad mínima debe ser mayor o igual a 1.',
            'is_active.boolean'         => 'El estado debe ser verdadero o falso.',
        ];
    }
}
