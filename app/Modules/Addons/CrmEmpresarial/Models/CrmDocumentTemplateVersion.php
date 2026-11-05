<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

class CrmDocumentTemplateVersion extends Model
{
    protected $table = 'document_template_versions';
    protected $guarded = ['id'];
    protected $casts = ['variables_requeridas' => 'array', 'campos_firma' => 'array', 'publicado_at' => 'datetime'];
}
