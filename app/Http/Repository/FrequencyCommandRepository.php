<?php

namespace App\Http\Repository;
use App\Models\FrequencyCommand;

class FrequencyCommandRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = FrequencyCommand::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function getIdFilterByName($name)
    {
        $frequency = $this->model->where('name', $name)->first();
        if($frequency){
            return $frequency->id;
        }
        return null;
    }

    public function getNameFrequencyFilterById($id)
    {
        $frequency = $this->model->findOrFail($id);
        return $frequency->name;
    }

    public function getArrayAllIdHasTimeFrequencies()
    {
        return $this->model->pluck('has_time','id');
    }




    // SETTERS




}
