<?php

namespace App\Http\Controllers\Module\Shared;

use App\Http\Controllers\Controller;
use App\Models\ClientMainInformation;
use App\Models\CompanyInformation;
use App\Models\CrmMainInformation;
use Illuminate\Http\Request;

class ComponentSelectStateMunicipalityAndColonyController extends Controller
{
    public function getValueDB(Request $request)
    {
        $model = $request->model;
        $field = $request->field;
        $id = $request->id;
        $fieldDB = $this->getFieldDBByModel($model);
        $data = $model::where($fieldDB, $id)->select($field)->first();
        if ($data) return $data->toArray();
    }

    public function getFieldDBByModel($model)
    {
        if (CrmMainInformation::class === $model) return 'crm_id';
        if (ClientMainInformation::class === $model) return 'client_id';
        if (CompanyInformation::class === $model) return 'id';
    }
}
