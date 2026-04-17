<?php

namespace App\Http\Requests\module\message\payment_email;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class PaymentEmailCreateRequest extends CrudModalValidationRequest
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
