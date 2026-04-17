<?php

namespace App\Rules;

use App\Http\Repository\ClientUserRepository;
use App\Models\NetworkIp;
use Illuminate\Contracts\Validation\Rule;

class ValidateIfUserIsDisponible implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

    protected $clientId;
    protected $router_id;
    public function __construct($router_id)
    {   
        $this->router_id = $router_id;
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
        $clientUserRepository = new ClientUserRepository();
        $clientUsers = $clientUserRepository->getClientUserByUserAndRouterId($value, $this->router_id);
        if ($clientUsers) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return 'Este Nombre No esta disponible.';
    }
}
