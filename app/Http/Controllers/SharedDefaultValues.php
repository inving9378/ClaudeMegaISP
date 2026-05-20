<?php

namespace App\Http\Controllers;

use App\Modules\Core\Configuracion\Repositories\DefaultValueRepository;
use App\Modules\Core\Configuracion\Models\DefaultValue;
use App\Modules\Core\Configuracion\Services\DefaultValueService;
use Illuminate\Http\Request;

class SharedDefaultValues
{
    public function saveOrDeleteDefaultValue(Request $request)
    {
        new DefaultValueService($request);
    }

    public function getDefaultFieldsValue(Request $request)
    {
        $defaultRepository = new DefaultValueRepository();
        $register = $defaultRepository->getDefaultValueByRequest($request);
        if ($register) {
            return response()->json([
                'success' => true,
                'value' => $register->value
            ]);
        }
    }
}
