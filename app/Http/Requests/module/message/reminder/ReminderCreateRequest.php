<?php

namespace App\Http\Requests\module\message\reminder;

use App\Http\Requests\module\base\CrudModalValidationRequest;

class ReminderCreateRequest extends CrudModalValidationRequest
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
