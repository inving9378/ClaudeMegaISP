<?php

namespace App\Http\Requests\module\inventory\supplier_invoice;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class SupplierInvoiceCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        return [
            'supplier_id'                => 'required|exists:suppliers,id',
            'date'                       => 'required|date',
            'items'                      => 'required|array|min:1',
            'items.*.inventory_item_id'  => 'required|exists:inventory_items,id',
            'items.*.purchase_type'      => 'required|in:unit,bulk',
            'items.*.quantity'           => 'required|numeric|min:0.01',
            'items.*.store_price'        => 'required|numeric|min:0',
            'items.*.bulk_quantity'      => 'nullable|integer|min:1',
        ];
    }

    public function storeMessageRules()
    {
        return [
            'supplier_id.required'                => 'El proveedor es obligatorio.',
            'date.required'                       => 'La fecha es obligatoria.',
            'items.required'                      => 'Debe agregar al menos un producto.',
            'items.*.inventory_item_id.required'  => 'Seleccione el artículo.',
            'items.*.purchase_type.required'      => 'Seleccione el tipo de compra.',
            'items.*.purchase_type.in'            => 'El tipo de compra debe ser unit o bulk.',
            'items.*.quantity.required'           => 'La cantidad es obligatoria.',
            'items.*.store_price.required'        => 'El precio es obligatorio.',
        ];
    }

    public function updateRules()
    {
        return [
            'date' => 'required|date',
        ];
    }

    public function updateMessageRules()
    {
        return [
            'date.required' => 'La fecha es obligatoria.',
        ];
    }
}
