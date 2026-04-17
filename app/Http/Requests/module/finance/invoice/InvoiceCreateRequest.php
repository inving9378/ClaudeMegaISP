<?php

namespace App\Http\Requests\module\finance\invoice;

use App\Http\Requests\module\base\CrudModalValidationRequest;


class InvoiceCreateRequest extends CrudModalValidationRequest
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
