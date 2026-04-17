<?php


namespace App\Http\HelpersModule\module\administration\colony;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Colony;

class ColonyDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Colony::class, 'Colony');
    }
}
