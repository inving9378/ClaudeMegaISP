<?php

namespace App\Http\Requests\module\inventory\store_zone;

use App\Http\Requests\module\base\CrudModalValidationRequest;
use Illuminate\Validation\Rule;

class StoreZoneCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        return [
            'name' => [
                'required',
                Rule::unique('store_zones')->where(function ($query) use ($request) {
                    return $query->where('store_id', $request->store_id);
                }),
            ],
            'store_id' => 'required',
        ];
    }
    public function storeMessageRules()
    {
        return [
            'name.required' => 'El campo Nombre es obligatorio.',
            'store_id.required' => 'El campo Almacen es obligatorio.',
            'name.unique' => 'El campo Nombre debe ser único.',
        ];
    }
}
