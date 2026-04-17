<?php

namespace App\Http\Controllers\Module\Administration\Municipality;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\municipality\MunicipalityDatatableHelper;
use App\Http\Requests\module\administration\municipality\MunicipalityArrayCrudRequest;

class MunicipalityController extends CrudModalController
{
    public function __construct(MunicipalityDatatableHelper $helper)
    {
        parent::__construct($helper, new MunicipalityArrayCrudRequest());
        $this->data['model'] = 'App\Models\Municipality';
        $this->data['url'] = 'meganet.module.administration.municipality';
        $this->data['module'] = 'Municipality';
        $this->data['filters'] = $this->filters();
    }
    public function filters()
    {
        $filters[] = json_encode(
            [
                'search' =>
                [
                    'label' => 'Estado',
                    'field' => 'state_id',
                    'model' => 'App\Models\State',
                    'id' => 'id',
                    'text' => 'name'
                ]
            ]

        );
        return $filters;
    }
}
