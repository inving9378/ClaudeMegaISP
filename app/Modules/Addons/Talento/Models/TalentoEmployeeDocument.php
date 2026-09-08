<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoEmployeeDocument extends BaseModel
{
    protected $table = 'talento_employee_documents';

    protected $fillable = [
        'colaborador_id', 'template_id', 'template_version_id',
        'rendered_html', 'status', 'generated_at',
        'signature_path', 'signed_at', 'signed_by', 'signature_method',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function template()
    {
        return $this->belongsTo(TalentoDocumentTemplate::class, 'template_id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(TalentoDocumentTemplateVersion::class, 'template_version_id');
    }
}
