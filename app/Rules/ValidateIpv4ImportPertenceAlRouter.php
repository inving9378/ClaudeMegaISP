<?php

namespace App\Rules;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Repository\NetworkRepository;
use App\Http\Repository\RouterRepository;
use Illuminate\Contracts\Validation\Rule;

class ValidateIpv4ImportPertenceAlRouter implements Rule
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
        $this->message = 'Esta Ip no pertenece a este router.';
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
        return $this->validateIpV4($attribute);
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

    public function validateIpV4($attribute)
    {

        if (!$attribute && $this->input['ipv4_assignment'] == ComunConstantsController::IPV4_ASSIGNMENT_STATIC) {
            $this->message = 'Este Campo es Requerido';
            return false;
        }
        $networkIpRepository = new NetworkIpRepository();
        $networkIp = $networkIpRepository->getNetworkIpByIp($this->input['ipv4']);

        if (!$networkIp) {
            $this->message = 'No existen Ip con este valor';
            return false;
        }

        /*  if ($networkIp->used) {
            $this->message = 'Esta ip Esta siendo usada';
            return false;
        } */

        $network = $networkIp->network;
        $router = $network->router_id;
        $routerRepository = new RouterRepository();
        $routerInput = $routerRepository->getRouterByTitle($this->input['router_id']);
        if (!$routerInput) {
            $this->message = 'No se encontro el Router';
            return false;
        }

        if ($router != $routerInput->id) {
            return false;
        }

        return true;
    }    
}
