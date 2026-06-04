<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoLevelAssignment extends Model
{
    protected $table = 'talento_level_assignments';

    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'level_id', 'reason',
        'certifications_snapshot', 'assigned_by', 'assigned_at', 'created_at',
    ];

    protected $casts = [
        'certifications_snapshot' => 'array',
        'assigned_at'             => 'datetime',
        'created_at'              => 'datetime',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function level()
    {
        return $this->belongsTo(TalentoLevel::class, 'level_id');
    }
}
