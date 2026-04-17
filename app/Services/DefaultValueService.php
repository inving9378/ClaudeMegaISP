<?php

namespace App\Services;

use App\Http\Repository\DefaultValueRepository;

class DefaultValueService
{
    protected $defaultValueRepository;
    protected $request;
    public function __construct($request)
    {
        $value = $request->value;
        $this->defaultValueRepository = new DefaultValueRepository();
        $this->request = $request;
        if ($value) {
            $this->createOrUpdate();
        } else {
            $this->delete();
        }
    }

    public function createOrUpdate()
    {
        $register =  $this->defaultValueRepository->getDefaultValueByRequest($this->request);
        if ($register) {
            $this->defaultValueRepository->update($this->request);
        } else {
            $this->defaultValueRepository->create($this->request);
        }
    }

    public function delete()
    {
        $register =  $this->defaultValueRepository->getDefaultValueByRequest($this->request);
        if ($register) {
            $register->delete();
        }
    }
}
