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
        'user_id', 'type', 'categoria_externo', 'department', 'supervisor_id',
        'hire_date', 'status', 'base_salary', 'notes',
        // Expediente RH (item #199 — Hijo A)
        'birth_date', 'curp', 'nss', 'emergency_contact_name', 'emergency_contact_phone',
        'relation_type', 'relation_end_date', 'pay_frequency', 'work_location',
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
        'relation_type', 'relation_end_date', 'pay_frequency', 'work_location',
        'base_salary', 'shift_start', 'shift_end', 'work_days',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // El puesto es el rol del usuario en Administración: el catálogo propio (talento_puestos) y el
    // texto libre job_title se retiraron el 2026-10-21. Los roles de sistema no son puesto.
    public const ROLES_QUE_NO_SON_PUESTO = ['super-administrator', 'Super Administrador', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR', 'client'];

    // Vendedor va al final: es una función que se suma a otro puesto (mismo criterio que
    // talento:sync-colaboradores), así que solo es el puesto de quien no tiene otro rol.
    private const ORDEN_PUESTO = ['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO', 'SUPERVISOR_MOSTRADOR', 'Mostrador'];

    private const NOMBRE_PUESTO = [
        'TECNICO_PLANTA'       => 'Técnico de planta',
        'TECNICO_INSTALADOR'   => 'Técnico instalador',
        'TECNICO'              => 'Técnico',
        'SUPERVISOR_MOSTRADOR' => 'Supervisor de mostrador',
        'Almacen'              => 'Almacén',
    ];

    public static function nombrePuesto(string $rol): string
    {
        return self::NOMBRE_PUESTO[$rol] ?? $rol;
    }

    /** Roles del usuario que cuentan como puesto, el principal primero. */
    public function rolesDePuesto(): array
    {
        $roles = $this->user?->roles->pluck('name')->diff(self::ROLES_QUE_NO_SON_PUESTO) ?? collect();

        $orden = fn ($rol) => $rol === 'Vendedor' ? 99 : (array_search($rol, self::ORDEN_PUESTO, true) === false ? 50 : array_search($rol, self::ORDEN_PUESTO, true));

        return $roles->sortBy($orden)->values()->all();
    }

    public function puestoNombre(): ?string
    {
        $rol = $this->rolesDePuesto()[0] ?? null;

        return $rol ? self::nombrePuesto($rol) : null;
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
}
