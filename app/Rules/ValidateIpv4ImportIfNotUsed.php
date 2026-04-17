<?php

namespace App\Rules;

use App\Http\Repository\NetworkIpRepository;
use Illuminate\Contracts\Validation\Rule;

class ValidateIpv4ImportIfNotUsed implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $input;
    public function __construct($input)
    {
        $this->input = $input;
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
        return $this->validateIp();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Ya esa ip ya esta en uso.';
    }

    public function validateIp()
    {
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpById($this->input['ipv4']);
        if ($networkIp->used) {
            return false;
        }
        return true;
    }
}
