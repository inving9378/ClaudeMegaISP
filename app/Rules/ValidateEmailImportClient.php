<?php

namespace App\Rules;

use App\Models\ClientMainInformation;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class ValidateEmailImportClient implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $input;
    protected $message;
    public function __construct($input)
    {
        $this->input = $input;
        $this->message = 'Ya existen Usuarios Iguales.';
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
        return $this->validateEmail($value);
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

    public function validateEmail($value)
    {
        $users = User::where('email', $value)->get();
        if ($users->count()) {
            foreach ($users as $client) {
                if ($this->emailIsNull() && $this->isEqualParameter($client, 'name') && $this->isEqualParameter($client, 'father_last_name') && $this->isEqualParameter($client, 'mother_last_name') && $this->isEqualParameterClientId($client)) {
                    $this->message = 'Ya existen Usuarios Iguales en la Tabla Users';
                    return false;
                } elseif ($this->isEqualEmail($client) && $this->emailIsDifferentNull()) {
                    $this->message = 'Ya existen Usuarios Iguales en la Tabla Users';
                    return false;
                };
            }
        }

       /*  $clients = ClientMainInformation::where('email', $value)->get();
        if ($clients->count()) {
            foreach ($clients as $client) {
                if ($this->emailIsNull() && $this->isEqualParameter($client, 'name') && $this->isEqualParameter($client, 'father_last_name') && $this->isEqualParameter($client, 'mother_last_name') && $this->isEqualParameter($client, 'nif_pasaport') && $this->isEqualParameterClientId($client)) {
                    $this->message = 'Ya existen Usuarios Iguales en la Tabla ClientMainInformation';
                    return false;
                } elseif ($this->isEqualParameter($client, 'email') && $this->emailIsDifferentNull()) {
                    $this->message = 'Ya existen Usuarios Iguales en la Tabla ClientMainInformation';
                    return false;
                };
            }
        } */

        return true;
    }

    public function isEqualEmail($client)
    {
        return $this->input['email'] == $client->email;
    }

    public function emailIsDifferentNull()
    {
        return  $this->input['email'] != null;
    }

    public function emailIsNull()
    {
        return  $this->input['email'] == null;
    }

    public function isEqualParameter($client, $value)
    {
        return $this->input[$value] == $client->$value;
    }

    public function isEqualParameterClientId($client)
    {
        return $this->input['client_id_old'] == $client->client_id;
    }
}
