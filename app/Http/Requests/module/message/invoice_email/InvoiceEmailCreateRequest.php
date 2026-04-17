<?php

namespace App\Http\Requests\module\message\invoice_email;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class InvoiceEmailCreateRequest extends CrudModalValidationRequest
{
    public function storeRules()
    {
        $request = request();
        return [

        ];
    }
    public function storeMessageRules()
    {
        return [

        ];
    }
}
