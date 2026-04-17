<?php

namespace App\Rules;

use App\Models\Client;
use App\Models\Crm;
use Illuminate\Contracts\Validation\Rule;

class ValidateSameNameInCrmOrClientTable implements Rule
{
    protected $request;
    protected $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request->all();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $request = $this->request;
        $crm = Crm::with('crm_main_information')->whereHas('crm_main_information', function ($query) use ($request) {
            $query->where('name', $request['name'])
                ->where('father_last_name', $request['father_last_name'])
                ->where('mother_last_name', $request['mother_last_name']);
        })->first();
        if ($crm) {
            $this->message = 'Ya existe un usuario crm en el sistema con los datos proporcionados. <a href="/crm/editar/' . $crm->id . '">' . $crm->crm_main_information->name . '</a>';
        }

        $client = Client::with('client_main_information')->whereHas('client_main_information', function ($query) use ($request) {
            $query->where('name', $request['name'])
                ->where('father_last_name', $request['father_last_name'])
                ->where('mother_last_name', $request['mother_last_name']);
        })->first();
        if ($client) {
            $this->message = 'Ya existe un usuario client en el sistema con los datos proporcionados. <a href="/cliente/editar/' . $client->id . '">' . $client->client_main_information->name . '</a>';
        }
        if ($crm && isset($request['enable_same_name_or_rfc']) || $client && isset($request['enable_same_name_or_rfc'])) {
            return $request['enable_same_name_or_rfc'];
        }
        return true;
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
}
