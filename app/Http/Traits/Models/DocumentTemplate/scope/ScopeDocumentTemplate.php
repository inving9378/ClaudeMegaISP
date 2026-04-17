<?php

namespace App\Http\Traits\Models\DocumentTemplate\scope;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Repository\DocumentTypeTemplateRepository;

trait ScopeDocumentTemplate
{
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if ($value == 'type') {
                        $query->orWhereHas('type_template', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    }
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        } elseif (!empty($filter)) {
            $query->where(function ($query) use ($filter, $search, $columns) {
                foreach ($filter as $key => $values) {
                    foreach ($values as $keyV => $val) {
                        if ($val == 'null') {
                            $query = $query;
                            break;
                        }
                        $query->where($keyV, $val)
                            ->where(function ($query) use ($search, $columns) {
                                foreach (
                                    collect($columns)->filter(function ($value) {
                                        return $value != 'action';
                                    })->toArray() as $value
                                ) {
                                    $query->orWhere($value, 'like', '%' . $search . '%');
                                }
                            });
                    }
                }
            });
        }
        return $query;
    }

    public function scopeTypeClient($query)
    {
        $documentTypeTemplateRepository = new DocumentTypeTemplateRepository();
        $typeId = $documentTypeTemplateRepository->getIdByName(ComunConstantsController::DOCUMENT_TYPE_TEMPLATE_CLIENT);
        return $query->where('type', $typeId);
    }

    public function scopeTypeEmail($query)
    {
        $documentTypeTemplateRepository = new DocumentTypeTemplateRepository();
        $typeId = $documentTypeTemplateRepository->getIdByName(ComunConstantsController::DOCUMENT_TYPE_TEMPLATE_EMAIL);
        return $query->where('type', $typeId);
    }
}
