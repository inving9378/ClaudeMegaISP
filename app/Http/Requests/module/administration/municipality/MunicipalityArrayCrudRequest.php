<?php

namespace App\Http\Requests\module\administration\municipality;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class MunicipalityArrayCrudRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        return [
            'state_id' => 'required'
        ];
    }
    public function storeMessageRules()
    {
        return [
            'state_id.required' => 'El campo estado es requerido'
        ];
    }
}
