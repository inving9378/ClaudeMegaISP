<?php

namespace App\Http\Requests\module\inventory\supplier_vendor;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class SupplierVendorCreateRequest extends CrudModalValidationRequest{
    public function storeRules()
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'name'    => 'required|string|max:255',
            'last_name'     => 'nullable|string|max:50',
            'phone'   => 'nullable|string|max:30',
            'email'   => 'nullable|nullable|email|max:255',
            'position' => 'nullable|string|max:500',
            'status'  => 'required|in:active,inactive',
        ];
    }

    public function storeMessageRules()
    {
        return [
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'name.required'          => 'El nombre del vendedor es obligatorio.',
            'name.max'               => 'El nombre no puede exceder los 255 caracteres.',
            'phone.max'              => 'El teléfono no puede exceder los 30 caracteres.',
            'email.email'            => 'El correo electrónico no es válido.',
            'email.max'               => 'El correo no puede exceder los 255 caracteres.',
            'status.required'        => 'El estatus es obligatorio.',
            'status.in'              => 'El estatus seleccionado no es válido.',
        ];
    }

    public function updateRules()
    {
        return $this->storeRules();
    }

    public function updateMessageRules()
    {
        return $this->storeMessageRules();
    }
}
