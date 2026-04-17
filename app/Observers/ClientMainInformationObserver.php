<?php

namespace App\Observers;

use App\Http\Controllers\HelperController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ColonyRepository;
use App\Http\Repository\MunicipalityRepository;
use App\Http\Repository\StateRepository;
use App\Http\Repository\UserRepository;
use App\Jobs\PupulateUserColumnsDatatableModuleDefaultsJob;
use App\Models\ClientMainInformation;
use App\Models\ClientUser;
use App\Models\TypeBilling;
use App\Models\User;
use App\Services\ClientService\BillingExpirationService;
use App\Services\ClientService\BillingPaymentDateService;
use App\Services\ClientService\SuspendService;
use Illuminate\Support\Facades\Log;

class ClientMainInformationObserver
{
    public function creating(ClientMainInformation $clientMainInformation)
    {
        $this->setAddressClient($clientMainInformation);
        if (!$clientMainInformation->user) {
            $helper = new HelperController();
            $clientMainInformation->user = $helper->getGenerateUser($clientMainInformation->client_id, 1);
        }
    }

    public function created(ClientMainInformation $clientMainInformation)
    {
        $this->createNewUserRoleClient($clientMainInformation);
    }


    public function updating(ClientMainInformation $clientMainInformation)
    {
        $this->setAddressClient($clientMainInformation);
        $this->updateUserIfIsDifferent($clientMainInformation);
    }


    public function updated(ClientMainInformation $clientMainInformation)
    {

        $previousEstado = $clientMainInformation->getOriginal('estado');
        $clientMainInformation->load('client');
        if (
            $previousEstado === ComunConstantsController::STATE_BLOCKED &&
            $clientMainInformation->estado === ComunConstantsController::STATE_ACTIVE
        ) {
            if (is_null($clientMainInformation->client->fecha_pago)) {
                // New Fecha Pago
                $service = new BillingPaymentDateService();
                $fechaPago = $service->getNewFechaPagoByClient($clientMainInformation->client);

                $repository = new ClientRepository();
                $repository->setFechaPago($clientMainInformation->client, $fechaPago);
            }

            $clientRepository = new ClientRepository();
            $clientRepository->removePeriodoGracia($clientMainInformation->client);
        }

        $previousEstado = $clientMainInformation->getOriginal('estado');
        if (
            $previousEstado === ComunConstantsController::STATE_ACTIVE &&
            $clientMainInformation->estado === ComunConstantsController::STATE_BLOCKED &&
            $clientMainInformation->type_of_billing_id != TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT
        ) {
            $repository = new ClientRepository();
            $repository->setFechaPago($clientMainInformation->client, null);
        }

        $suspendService = new SuspendService();
        $suspendService->ifClientChangeToBlockedRemoveDateCorte($clientMainInformation);
        $suspendService = new SuspendService();
        $suspendService->ifClientChangeToInactiveRemoveDateCorteAndDatePayment($clientMainInformation);


    }

    public function deleting(ClientMainInformation $clientMainInformation)
    {
        //
    }

    protected function setAddressClient(ClientMainInformation $clientMainInformation)
    {
        $colonyRepository = new ColonyRepository();
        $stateRepository = new StateRepository();
        $muninipalityRepository = new MunicipalityRepository();

        $street = $clientMainInformation->street;
        $internalNumber = $clientMainInformation->internal_number;
        $externalNumber = $clientMainInformation->external_number;
        $colonyName = $clientMainInformation->colony_id != '' ? $colonyRepository->getColonyNameById($clientMainInformation->colony_id) : "";
        $muninipalityName = $clientMainInformation->municipality_id != '' ? $muninipalityRepository->getMunicipalityNameById($clientMainInformation->municipality_id) : "";
        $stateName = $clientMainInformation->state_id != '' ? $stateRepository->getStateNameById($clientMainInformation->state_id) : "";
        $clientMainInformation->address = $street . ' ' . $internalNumber . ' ' . $externalNumber . ' ' . $colonyName . ' ' . $muninipalityName . ' ' . $stateName;
    }

    public function createNewUserRoleClient($clientMainInformation)
    {
        $user = new User();
        $user->name = $clientMainInformation->name;
        $user->email = $clientMainInformation->email;
        $user->father_last_name = $clientMainInformation->father_last_name;
        $user->mother_last_name = $clientMainInformation->mother_last_name;
        $user->phone = $clientMainInformation->phone;
        $user->location = $clientMainInformation->location;
        $user->login_user = $clientMainInformation->user;
        $user->password = base64_encode($clientMainInformation->password);
        $user->client_id = $clientMainInformation->client_id;
        $user->save();

        $role = \Spatie\Permission\Models\Role::findByName('client');
        $user->assignRole($role);
    }


    public function updateUserIfIsDifferent($clientMainInformation)
    {
        $user = User::where('client_id', $clientMainInformation->client_id)->first();
        if ($user) {
            $user->name = $clientMainInformation->name;
            $user->email = $clientMainInformation->email;
            $user->father_last_name = $clientMainInformation->father_last_name;
            $user->mother_last_name = $clientMainInformation->mother_last_name;
            $user->phone = $clientMainInformation->phone;
            $user->location = $clientMainInformation->location;
            $user->login_user = $clientMainInformation->user;
            $user->password = base64_encode($clientMainInformation->password);
            $user->save();
        }
    }
}
