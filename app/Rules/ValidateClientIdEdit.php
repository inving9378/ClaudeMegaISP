<?php

namespace App\Rules;

use App\Models\Client;
use Illuminate\Contracts\Validation\Rule;

class ValidateClientIdEdit implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $message;
    public function __construct()
    {

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
        return $this->validateId($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    public function validateId($id)
    {
        $clientWithSoftDeletes = Client::onlyTrashed()->find($id);
        if ($clientWithSoftDeletes) {
            $this->message = "El Id no esta disponible Esta siendo usado por un cliente previamente eliminado";
            return false;
        }
        $client = Client::find($id);
        if ($client) {
            $this->message = "El Id no esta disponible";
            return false;
        }
        return true;
    }
}
