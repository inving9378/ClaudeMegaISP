<?php


namespace App\Http\HelpersModule\module\administration\municipality;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Municipality;

class MunicipalityDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Municipality::class, 'Municipality');
    }
}
