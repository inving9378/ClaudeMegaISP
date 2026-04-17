<?php

namespace App\Http\Controllers\Module\Sellers\Seller;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\sellers\seller\SellerDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class SellerController extends CrudModalController
{
    public function __construct(SellerDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\Seller';
        $this->data['url'] = 'meganet.module.sellers.seller';
        $this->data['module'] = 'Seller';
    }
}
