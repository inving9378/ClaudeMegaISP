<?php

namespace App\Observers;

use App\Http\Repository\ColonyRepository;
use App\Http\Repository\MunicipalityRepository;
use App\Http\Repository\StateRepository;
use App\Models\CrmMainInformation;

class CrmMainInformationObserver
{
    public function creating(CrmMainInformation $crmMainInformation)
    {
        $crmMainInformation->high_date = date('Y-m-d');
    }


    public function updating(CrmMainInformation $crmMainInformation)
    {
        $this->setAddressClient($crmMainInformation);
    }
    protected function setAddressClient(CrmMainInformation $crmMainInformation)
    {
        $colonyRepository = new ColonyRepository();
        $stateRepository = new StateRepository();
        $muninipalityRepository = new MunicipalityRepository();

        $street = $crmMainInformation->street;
        $internalNumber = $crmMainInformation->internal_number;
        $externalNumber = $crmMainInformation->external_number;
        $colonyName = $colonyRepository->getColonyNameById($crmMainInformation->colony_id);
        $muninipalityName = $muninipalityRepository->getMunicipalityNameById($crmMainInformation->municipality_id);
        $stateName = $stateRepository->getStateNameById($crmMainInformation->state_id);

        $crmMainInformation->address = $street . ' ' . $internalNumber . ' ' . $externalNumber . ' ' . $colonyName . ' ' . $muninipalityName . ' ' . $stateName;
    }
}
