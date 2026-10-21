<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoPuestoDocumentTemplate extends BaseModel
{
    protected $table = 'talento_puesto_document_templates';

    // `puesto` guarda el nombre del rol de Administración (ver TalentoColaborador::rolesDePuesto()).
    protected $fillable = [
        'puesto', 'template_id', 'obligatorio', 'vigencia_meses',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(TalentoDocumentTemplate::class, 'template_id');
    }
}
