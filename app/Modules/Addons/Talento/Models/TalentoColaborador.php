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
        'user_id', 'type', 'categoria_externo', 'department', 'supervisor_id', 'equipo',
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
    private const ORDEN_PUESTO = \App\Modules\Addons\Talento\Support\Actor::ROLES_POR_PRIORIDAD;

    private const NOMBRE_PUESTO = [
        'TECNICO_PLANTA'       => 'Técnico de planta',
        'TECNICO_INSTALADOR'   => 'Técnico instalador',
        'TECNICO'              => 'Técnico',
        'SUPERVISOR_MOSTRADOR' => 'Supervisor de mostrador',
        'Almacen'              => 'Almacén',
        'DESARROLLADOR'        => 'Desarrollador',
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

    /**
     * Activos para mostrar (listas, selectores, conteos y rankings): activo en Talento y con su
     * cuenta del sistema activa. Deja fuera a quien se dio de baja en Administración sin pasarlo a
     * inactivo aquí, y a MEGAISP (cuenta de sistema, siempre inactiva). Decisión del 2026-10-22.
     * La lógica (login de la app, reglas semanales, liquidación) sigue mirando solo status.
     */
    public function scopeActivos($query)
    {
        return $query->where('status', 'active')
            ->whereHas('user', fn ($u) => $u->where('estado', 'activo'));
    }

    /** Lo contrario de activos(): inactivos, suspendidos y activos con la cuenta desactivada. */
    public function scopeNoActivos($query)
    {
        return $query->where(fn ($q) => $q->where('status', '<>', 'active')
            ->orWhereDoesntHave('user', fn ($u) => $u->where('estado', 'activo')));
    }

    public function devices()
    {
        return $this->hasMany(TalentoDevice::class, 'user_id', 'user_id');
    }
}
