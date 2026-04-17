<?php

namespace App\Http\Traits;

use App\Models\Module;

trait SingleRelationTrait {

    public function saveSingleRelationWithoutModel($modelToSave, $relation_name, $key_relation, $local_relation, $eloquent_model, $request){
        $input = $request->all();
        $input[$key_relation] = $eloquent_model->$local_relation;

        $eloquent_model->$relation_name ?
            $modelToSave::find($eloquent_model->$relation_name->id)->update($input) :
            $modelToSave::create($input);
    }

    public function saveSingleRelationIfExist($model, $eloquent_model, $request, $action = 'update')
    {
        if (defined($model . '::SINGLE_RELATIONS')){
            foreach ($model::SINGLE_RELATIONS as $modelName => $val){
                $module = Module::where('name', $modelName)->first();
                $key = $module->fields()->get()->pluck('name')->toArray();
                $input = \Illuminate\Support\Arr::only($request->all(), $key);

                $relation_name = $val['relation_name'];
                $relation = $val['relation_field'];
                $local_relation =  $val['local_relation'];
                $modelToSave = 'App\Models\\' . $modelName;
                //TODO ARREGLAR DESPUES
                if (isset($request->location_id) && $relation_name == 'crm_main_information') {
                    $input['location_id'] = null;
                }

                $input[$relation] = $eloquent_model->$local_relation;
                $action == 'update' ?
                    $modelToSave::find($eloquent_model->$relation_name->id)->$action($input) :
                    $modelToSave::$action($input);

            }
        }
        return true;
    }
}
