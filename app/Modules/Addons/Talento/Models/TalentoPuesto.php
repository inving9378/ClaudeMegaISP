<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoPuesto extends BaseModel
{
    protected $table = 'talento_puestos';

    protected $fillable = [
        'nombre', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function colaboradores()
    {
        return $this->hasMany(TalentoColaborador::class, 'puesto_id');
    }

    public function documentTemplates()
    {
        return $this->hasMany(TalentoPuestoDocumentTemplate::class, 'puesto_id');
    }
}
