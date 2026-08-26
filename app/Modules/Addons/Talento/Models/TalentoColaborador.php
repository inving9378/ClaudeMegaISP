<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoColaborador extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_colaboradores';

    protected $fillable = [
        'user_id', 'type', 'department', 'supervisor_id', 'level_id',
        'hire_date', 'status', 'base_salary', 'notes',
        // Expediente RH (item #199 — Hijo A)
        'birth_date', 'curp', 'nss', 'emergency_contact_name', 'emergency_contact_phone',
        'job_title', 'relation_type', 'relation_end_date', 'pay_frequency', 'work_location',
        'shift_start', 'shift_end', 'work_days',
    ];

    protected $casts = [
        'hire_date'         => 'date',
        'base_salary'       => 'decimal:2',
        'birth_date'        => 'date',
        'relation_end_date' => 'date',
    ];

    /**
     * Datos personales sensibles del expediente (item #199): CURP, RFC*, NSS, salario y
     * domicilio* exigen el permiso propio 'talento.expediente.view', distinto de 'talento.view'
     * (la ficha normal). (*RFC/domicilio viven en users, se gatean aparte en el controller que
     * los expone). El resto del bloque laboral se agrupa aqui por ser parte del mismo expediente.
     */
    public const EXPEDIENTE_FIELDS = [
        'birth_date', 'curp', 'nss', 'emergency_contact_name', 'emergency_contact_phone',
        'job_title', 'relation_type', 'relation_end_date', 'pay_frequency', 'work_location',
        'base_salary', 'shift_start', 'shift_end', 'work_days',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(TalentoColaborador::class, 'supervisor_id');
    }

    public function subordinados()
    {
        return $this->hasMany(TalentoColaborador::class, 'supervisor_id');
    }

    public function devices()
    {
        return $this->hasMany(TalentoDevice::class, 'user_id', 'user_id');
    }

    // Nivel vigente (columna level_id, actualizada por LevelService::promote()).
    // talento_level_assignments es el histórico de asignaciones, no la fuente del nivel actual.
    public function level()
    {
        return $this->belongsTo(TalentoLevel::class, 'level_id');
    }
}
