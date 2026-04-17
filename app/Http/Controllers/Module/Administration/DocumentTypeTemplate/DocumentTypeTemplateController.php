<?php

namespace App\Http\Controllers\Module\Administration\DocumentTypeTemplate;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\administration\document_type_template\DocumentTypeTemplateDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;

class DocumentTypeTemplateController extends CrudModalController
{
    public function __construct(DocumentTypeTemplateDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\DocumentTypeTemplate';
        $this->data['url'] = 'meganet.module.administration.document_type_template';
        $this->data['module'] = 'DocumentTypeTemplate';
    }
}
