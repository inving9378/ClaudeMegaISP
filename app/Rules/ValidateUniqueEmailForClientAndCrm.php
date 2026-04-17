<?php

namespace App\Rules;

use App\Models\ClientMainInformation;
use App\Models\CrmMainInformation;
use Illuminate\Contracts\Validation\Rule;

class ValidateUniqueEmailForClientAndCrm implements Rule
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
        $this->request = $request;
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
        if ($this->request->email_is_required == true && empty($value)) {
            $this->message = 'El campo correo es requerido';
            return false;
        } else if ($this->request->email_is_required == false && empty($value)) {
            return true;
        } else {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !empty($value)) {
                $this->message = 'El valor proporcionado no es un correo electrónico válido';
                return false;
            }
            $crms = CrmMainInformation::where('email', $value)->pluck('name');
            if ($crms->count() > 0) {
                $this->message = 'Ya existen los siguientes usuarios registrados con el mismo correo en crm: <ul>';
                foreach ($crms as $name) {
                    $this->message .= '<li>' . $name . '</li>';
                }
                $this->message .= '</ul>';
            }

            $clients = ClientMainInformation::where('email', $value)->pluck('name');
            if ($clients->count() > 0) {
                $this->message .= 'Ya existen los siguientes usuarios registrados con el mismo correo en cliente: <ul>';
                foreach ($clients as $name) {
                    $this->message .= '<li>' . $name . '</li>';
                }
                $this->message .= '</ul>';
            }

            if ($crms->count() > 0 || $clients->count() > 0) {
                return false;
            }
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
