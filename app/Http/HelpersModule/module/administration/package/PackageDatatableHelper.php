<?php


namespace App\Http\HelpersModule\module\administration\package;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Package;

class PackageDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Package::class, 'Package');
    }
}
