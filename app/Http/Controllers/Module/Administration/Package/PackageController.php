<?php

namespace App\Http\Controllers\Module\Administration\Package;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\package\PackageDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class PackageController extends CrudModalController
{
    public function __construct(PackageDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\Package';
        $this->data['url'] = 'meganet.module.administration.package';
        $this->data['module'] = 'Package';
    }
}
