<?php

namespace App\Http\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Log;

trait IncludeFieldsTrait
{
    public function includeFields($model, array $fields, object $eloquent_model)
    {
        $keys = collect($fields)->keys()->toArray();
        $eloquent_model_array = $eloquent_model->toArray();
        foreach ($fields as $key => $val) {
            if (isset($eloquent_model_array[$key])) {
                $fields[$key]['value'] = (string) $eloquent_model_array[$key];
                $has_inputs_depend = isset($fields[$key]['inputs_depend']);

                if ($has_inputs_depend) {
                    foreach ($fields[$key]['inputs_depend'] as $keyInput => $valueInput) {
                        $fields[$key]['inputs_depend']->$keyInput->value = (string) $eloquent_model_array[$keyInput];
                    }
                }

                //if field is password y model is User
                if ($this->fieldIsPasswordAndModelIsUser($key, $eloquent_model)) {
                    $fields[$key]['value'] = $this->getValue($eloquent_model[$key]);
                }
            }
        }

        if (defined($model . '::FILE_FIELDS')) {
            foreach ($model::FILE_FIELDS as $key => $val) {
                $fields[$key]['value'] = $eloquent_model->$val()->first();
            }
        }

        if (defined($model . '::BOOTSTRAP_SELECT_FIELDS')) {
            foreach ($model::BOOTSTRAP_SELECT_FIELDS as $key => $val) {
                $fields[$key]['value'] = $eloquent_model->$val()->get()->pluck('id');
            }
        }

        if (defined($model . '::MULTIPLE_RELATIONS')) {
            foreach ($model::MULTIPLE_RELATIONS as $key => $val) {
                $fields[$key]['value'] = $eloquent_model->$val()->get()->pluck('id');
            }
        }

        if (defined($model . '::RELATIONS_MULTIPLE_WITH_CANT')) {
            foreach ($model::RELATIONS_MULTIPLE_WITH_CANT as $key => $val) {
                $fields[$key]['value'] = [];
                foreach ($eloquent_model->$val()->get() as $value) {
                    if (isset($value->pivot->cant)) {
                        $fields[$key]['value'][] = [
                            $value->id => [
                                'cant' => $value->pivot->cant
                            ]
                        ];
                    }
                }
            }
        }
        return collect($fields)->only($keys);
    }

    public function getValue($value)
    {
        return base64_decode($value);
    }

    public function fieldIsPasswordAndModelIsUser($key, $eloquent_model)
    {
        return $key == 'password' && $eloquent_model instanceof User;
    }
}
