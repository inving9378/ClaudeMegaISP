<?php

namespace App\Http\Controllers\Module\Administration\Colony;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\colony\ColonyDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class ColonyController extends CrudModalController
{
    public function __construct(ColonyDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\Colony';
        $this->data['url'] = 'meganet.module.administration.colony';
        $this->data['module'] = 'Colony';
        $this->data['filters'] = $this->filters();
    }

    public function filters()
    {
        $filters[] = json_encode(
            [
                'search' =>
                [
                    'label'=>'Municipio',
                    'field' => 'municipality_id',
                    'model' => 'App\Models\Municipality',
                    'id' => 'id',
                    'text' => 'name'
                ]
            ]

        );
        return $filters;
    }
}
