<?php

namespace App\Rules;

use App\Models\NetworkIp;
use Illuminate\Contracts\Validation\Rule;

class ValidateIpv4IfNotUsed implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

    protected $clientId;
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
        $ip = NetworkIp::find($value);  

        if ($ip && $ip->used_by == $value) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return 'Esta IP está siendo utilizada.';
    }
}
