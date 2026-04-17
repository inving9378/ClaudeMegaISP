<?php

namespace App\Http\Traits;

trait MultipleRelationTrait
{
    public function saveRelationMultipleIfExist($model, $eloquent_model, $request, $action = 'attach')
    {
        if (defined($model . '::MULTIPLE_RELATIONS')) {
            foreach ($model::MULTIPLE_RELATIONS as $key => $val) {
                if ($request->$key == null) {
                    $request->$key = [];
                } elseif (!is_array($request->$key)) {
                    $request->$key = [$request->$key];
                }
                if (isset($request->$key) && is_array($request->$key)) {
                    $eloquent_model->$val()->$action($request->$key);
                }
            }
        }
    }
}
