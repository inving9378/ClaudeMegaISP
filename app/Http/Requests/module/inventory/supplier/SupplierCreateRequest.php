<?php

namespace App\Http\Requests\module\inventory\supplier;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class SupplierCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        return [
            'name'    => 'required|string|max:255',
            'rfc'     => 'required|string|max:50',
            'phone'   => 'required_without:email|nullable|string|max:30',
            'email'   => 'required_without:phone|nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'status'  => 'required|in:active,inactive',
        ];
    }

    public function storeMessageRules()
    {
        return [
            'name.required'          => 'El nombre del proveedor es obligatorio.',
            'name.max'               => 'El nombre no puede exceder los 255 caracteres.',
            'rfc.required'           => 'El RFC es obligatorio.',
            'rfc.max'                => 'El RFC no puede exceder los 50 caracteres.',
            'phone.required_without' => 'Debe proporcionar al menos un teléfono o un correo electrónico.',
            'phone.max'              => 'El teléfono no puede exceder los 30 caracteres.',
            'email.required_without' => 'Debe proporcionar al menos un correo electrónico o un teléfono.',
            'email.email'            => 'El correo electrónico no es válido.',
            'email.max'               => 'El correo no puede exceder los 255 caracteres.',
            'address.max'            => 'La dirección no puede exceder los 500 caracteres.',
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
