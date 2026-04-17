<?php

namespace App\Rules;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\NetworkIpRepository;
use App\Http\Repository\NetworkRepository;
use App\Http\Repository\RouterRepository;
use Illuminate\Contracts\Validation\Rule;

class ValidateIpv4PollImportPertenceAlRouter implements Rule
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

        return $this->validateIpV4Pool($attribute);
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

    public function validateIpV4Pool($attribute)
    {

        if (!$this->input[$attribute] && $this->input['ipv4_assignment'] == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP) {
            $this->message = 'Este Campo es Requerido';
            return false;
        }
        $networkRepository = new NetworkRepository();
        $network = $networkRepository->getNetworkByNetwork($this->input['ipv4_pool']);
        if (!$network) {
            $this->message = "No existen Redes pool para este ipv4_pool. Verifique el nombre";
            return false;
        }
        $routerTowhichNetworkBelongs = $network->router_id;
        $routerRepository = new RouterRepository();
        $routerInput = $routerRepository->getRouterByTitle($this->input['router_id']);
        $routerInputId = $routerInput->id ?? null;
        if ($routerTowhichNetworkBelongs != $routerInputId) {
            $this->message = "Esta Red no pretenece al Router " . $this->input['router_id'];
            return false;
        }
        $networkIpRepository = new NetworkIpRepository();
        $redesDisponibles = $networkIpRepository->getPoolIp($network->id, $routerInputId);

        if (!$redesDisponibles->count() > 0) {
            $this->message = "No hay redes disponibles para este ipv4_pool";
            return false;
        }


        return true;
    }
}
