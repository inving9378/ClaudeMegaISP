<?php

namespace App\Services;

use App\Http\Repository\DefaultValueRepository;

class DefaultChecksMarkedService
{
    public function getDefultValueForThisUserIfExist()
    {
        $defalutValueRepository = new DefaultValueRepository();
        $defaultValues = $defalutValueRepository->getDefaultValueFilteredByAuthUser();
        return $defaultValues;
    }
}
