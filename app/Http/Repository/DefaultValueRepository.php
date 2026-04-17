<?php

namespace App\Http\Repository;

use App\Models\DefaultValue;

class DefaultValueRepository
{
    protected $model;
    protected $request;


    public function __construct()
    {
        $this->model = DefaultValue::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS
    public function getDefaultValueByRequest($request)
    {
        $userId = auth()->user()->id;
        return $this->model->where('user_id', $userId)
            ->where('module_id', $request->module_id)
            ->where('field', $request->field)
            ->first();
    }

    public function  getDefaultValueFilteredByModuleIdAndField($module_id, $field)
    {
        $userId = auth()->user()->id;
        return $this->model->where('user_id', $userId)
            ->where('module_id', $module_id)
            ->where('field', $field)
            ->first();
    }

    public function getDefaultValueFilteredByAuthUser()
    {
        $userId = auth()->user()->id;
        $register = $this->model->where('user_id', $userId)->get();
        if (count($register) > 0) {
            return $register->pluck('value', 'field')->toArray();
        }
        return [];
    }

    public function getDefaultValueFilteredByAuthUserAndModuleId($module_id)
    {
        $userId = auth()->user()->id;
        $register = $this->model->where('user_id', $userId)->where('module_id', $module_id)->get();
        if (count($register) > 0) {
            return $register->pluck('value', 'field')->toArray();
        }
        return [];
    }

    public function create($request)
    {
        $userId = auth()->user()->id;
        $value = is_array($request->value) ? json_encode($request->value) : $request->value;
        if ($value != null && $value != 'null' && !empty($value)) {
            return $this->model->create([
                "user_id" => $userId,
                "module_id" => $request->module_id,
                "field" => $request->field,
                "value" => $value,
            ]);
        }
    }

    public function update($request)
    {
        $value = $request->value;
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $field = $request->field;
        if ($field == 'colony_id') {
            $value = is_array($request->value) ? json_encode($request->value) : $request->value;
        }
        if ($value != null && $value != 'null' && !empty($value)) {
            return $this->model->update([
                "value" => $value,
            ]);
        }
    }
}
