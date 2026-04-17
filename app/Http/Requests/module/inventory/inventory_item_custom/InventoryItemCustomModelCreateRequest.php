<?php

namespace App\Http\Requests\module\inventory\inventory_item_custom;

use App\Http\Requests\module\base\CrudModalValidationRequest;


class InventoryItemCustomModelCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        $rules = [

        ];

        return $rules;
    }
    public function storeMessageRules()
    {
        return [

        ];
    }
}
