<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Models\Client;
use App\Models\TypeBilling;

class IfChangeRecurrentToOtherTypeOfBilling implements Rule
{

    protected $clientId;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($client_id)
    {
       $this->clientId = $client_id;
    }


    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$value) return true;
        return $this->ValidateifChangeRecurrentToOtherTypeOfBilling($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Para cambiar, debe eliminar el paquete activo del cliente';
    }

    public function ValidateifChangeRecurrentToOtherTypeOfBilling($value)
    {
     $client = Client::with('client_main_information', 'bundle_service')
        ->where('id', $this->clientId)->first();

        $clientTypeOfBilling = $client->client_main_information->type_of_billing_id;

      if ($clientTypeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT && $clientTypeOfBilling != $value) {
        return  !$client->bundle_service->count() > 0 ;
       }

       return true ;

    }

}
