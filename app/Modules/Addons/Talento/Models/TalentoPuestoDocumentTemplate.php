<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoPuestoDocumentTemplate extends BaseModel
{
    protected $table = 'talento_puesto_document_templates';

    protected $fillable = [
        'puesto', 'puesto_id', 'template_id',
    ];

    public function template()
    {
        return $this->belongsTo(TalentoDocumentTemplate::class, 'template_id');
    }

    public function puesto()
    {
        return $this->belongsTo(TalentoPuesto::class, 'puesto_id');
    }
}
