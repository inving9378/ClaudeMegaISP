<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ValidateUrlSeparatedByComma implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        return $this->separatedByCommas($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Existen url con el formato incorrecto.';
    }

    public function separatedByCommas($argUrl)
    {
        $urls = explode(',' ,$argUrl);
        foreach ($urls as $url) {
            $url_ = filter_var($url, FILTER_SANITIZE_URL);
            if (filter_var($url_, FILTER_VALIDATE_URL) === false) {
               return false ;
            }
        }
        return true;
    }
}
