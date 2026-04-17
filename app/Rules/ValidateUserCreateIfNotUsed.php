<?php

namespace App\Rules;

use App\Models\ClientUser;
use App\Models\NetworkIp;
use Illuminate\Contracts\Validation\Rule;

class ValidateUserCreateIfNotUsed implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

    protected $user;
    public function __construct($user)
    {
        $this->user = $user;
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
        $userExists = ClientUser::where('user', $this->user)->first();        
        if ($userExists) {
            return false;
        }
        return true;
    }

    public function message()
    {
        return 'Este usuario ya esta en uso';
    }
}
