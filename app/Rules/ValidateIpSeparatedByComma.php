<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateIpSeparatedByComma implements Rule
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
        return 'Existen IP con el formato incorrecto.';
    }

    public function separatedByCommas($argIp)
    {
        $items = explode(',', $argIp);
        foreach ($items as $item) {
            $trimmedItem = trim($item);
            if (filter_var($trimmedItem, FILTER_VALIDATE_IP) === false && filter_var($trimmedItem, FILTER_VALIDATE_URL) === false) {
                return false;
            }
        }
        return true;
    }
}
