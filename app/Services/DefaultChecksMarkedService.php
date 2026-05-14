<?php

namespace App\Services;

use App\Modules\Core\Configuracion\Repositories\DefaultValueRepository;

class DefaultChecksMarkedService
{
    public function getDefultValueForThisUserIfExist()
    {
        $defalutValueRepository = new DefaultValueRepository();
        $defaultValues = $defalutValueRepository->getDefaultValueFilteredByAuthUser();
        return $defaultValues;
    }
}
