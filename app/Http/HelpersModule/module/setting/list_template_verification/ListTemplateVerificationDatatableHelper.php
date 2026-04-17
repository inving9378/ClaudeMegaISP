<?php


namespace App\Http\HelpersModule\module\setting\list_template_verification;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\ListTemplateVerification;

class ListTemplateVerificationDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(ListTemplateVerification::class, 'ListTemplateVerification');
    }
}
