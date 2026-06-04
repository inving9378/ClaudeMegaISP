<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCajaInspection extends BaseModel
{
    protected $table = 'talento_caja_inspections';

    public $timestamps = false; // solo created_at manual

    protected $fillable = [
        'caja_ref', 'project_id', 'inspected_by', 'photo_path',
        'captured_lat', 'captured_lng', 'captured_in_app',
        'fusion_loss_measured', 'power_measured', 'aesthetic_score',
        'ia_flags', 'overall_result', 'supervisor_validated', 'notes',
        'created_by', 'created_at',
    ];

    protected $casts = [
        'captured_in_app'      => 'boolean',
        'supervisor_validated' => 'boolean',
        'ia_flags'             => 'array',
        'fusion_loss_measured' => 'float',
        'power_measured'       => 'float',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'inspected_by');
    }

    public function project()
    {
        return $this->belongsTo(TalentoProject::class, 'project_id');
    }

    public function scopeForCaja($q, string $ref)
    {
        return $q->where('caja_ref', $ref);
    }
}
