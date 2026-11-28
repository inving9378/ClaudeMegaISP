<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;

/**
 * Única fuente de verdad de la regla "¿este usuario debe tener fila en
 * talento_colaboradores, y con qué department?" — compartida por
 * SyncColaboradoresCommand (barrido periódico/manual) y por el gancho en
 * vivo de UserController::store()/update() (item: conexión automática
 * Administradores → Talento). Nunca duplicar esta lógica en otro lado.
 *
 * Puramente aditivo: crea o actualiza el department de un colaborador
 * cuando corresponde, pero JAMÁS desactiva ni borra — eso sigue siendo
 * exclusivo de la Fase 2 (manual) de SyncColaboradoresCommand.
 *
 * Crear la fila dispara solo, vía TalentoColaboradorObserver::created()
 * (ya registrado en ModuleServiceProvider), la sincronización de permisos,
 * la generación de documentos de RH y el reclamo de extensión SIP — no hay
 * que repetir nada de eso aquí.
 */
class TalentoColaboradorSyncService
{
    private const SYSTEM_ROLES = ['super-administrator', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR'];

    // Solo roles de PUESTO (no capacidades transversales como Vendedor).
    private const PRIORITY = ['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO', 'Mostrador'];

    /**
     * Colaborador de prueba a proteger de la sincronización.
     * PORTABLE entre entornos: se resuelve por login_user (estable), NO por id
     * hardcodeado (los ids de dev y prod divergen). En prod este login no
     * existe → no protege a nadie.
     */
    private const PROTECTED_TEST_LOGIN = 'Meganet6503bee0';

    // Roles que no son personal de la empresa: con solo estos no se entra a la app de campo.
    private const NO_PERSONAL = ['client', 'Solo lectura'];

    // Departamento de la ficha que se crea al entrar a la app cuando no hay rol de puesto.
    private const DEPARTAMENTO_SIN_PUESTO = [
        'Vendedor'             => 'Ventas',
        'SUPERVISOR_MOSTRADOR' => 'Mostrador',
    ];

    /**
     * @param  string[]  $roleNames
     */
    public function esPersonal(array $roleNames): bool
    {
        return array_diff($roleNames, self::NO_PERSONAL) !== [];
    }

    /**
     * Ficha con la que una cuenta entra a la app de campo (19-nov-2026, decisión de David: todo el personal
     * activo entra y lo que ve depende de su rol). Si no tiene ficha se crea aquí, sin el alta de RH que hace
     * TalentoColaboradorObserver (documentos, extensión, permisos del portal): eso sigue siendo manual mientras
     * `talento.autosync_usuarios` esté apagado. Una ficha inactiva o dada de baja no se reactiva sola, porque
     * la decisión fue de quien la dio de baja.
     *
     * @return array{colaborador: ?TalentoColaborador, motivo: ?string}  motivo: 'no_personal' | 'inactivo'
     */
    public function perfilParaApp(User $user): array
    {
        $activo = TalentoColaborador::where('user_id', $user->id)->where('status', 'active')->first();
        if ($activo) {
            return ['colaborador' => $activo, 'motivo' => null];
        }

        $user->unsetRelation('roles');
        $roleNames = $user->roles()->pluck('name')->all();
        if (! $this->esPersonal($roleNames)) {
            return ['colaborador' => null, 'motivo' => 'no_personal'];
        }

        if (TalentoColaborador::withTrashed()->where('user_id', $user->id)->exists()) {
            return ['colaborador' => null, 'motivo' => 'inactivo'];
        }

        $mappings = TalentoRoleDepartment::pluck('department', 'role_name')->all();
        $department = $this->resolveDepartment($roleNames, $mappings);
        foreach (self::DEPARTAMENTO_SIN_PUESTO as $rol => $sinPuesto) {
            if ($department === null && in_array($rol, $roleNames, true)) {
                $department = $sinPuesto;
            }
        }

        $colaborador = TalentoColaborador::withoutEvents(fn () => TalentoColaborador::create([
            'user_id'    => $user->id,
            'type'       => 'interno',
            'department' => $department,
            'status'     => 'active',
        ]));

        return ['colaborador' => $colaborador, 'motivo' => null];
    }

    /**
     * 28-nov-2026 (David): al crear o editar un usuario o admin en Administración ya sale como colaborador, sin esperar
     * a que entre a la app; así aparece para asignarle órdenes. Misma regla que perfilParaApp(). Si su ficha no tenía
     * departamento y ahora su rol lo dice (p. ej. le dieron el rol de planta externa), se lo pone; uno ya puesto no se
     * cambia.
     */
    public function asegurarFicha(User $user): ?TalentoColaborador
    {
        if ($user->isNotActive()) {
            return null;
        }
        $colaborador = $this->perfilParaApp($user)['colaborador'];
        if ($colaborador && blank($colaborador->department)) {
            $department = $this->resolveDepartment($user->roles()->pluck('name')->all(), TalentoRoleDepartment::pluck('department', 'role_name')->all());
            if ($department !== null) {
                $colaborador->department = $department;
                $colaborador->saveQuietly();
            }
        }

        return $colaborador;
    }

    /**
     * @return array{action: string, reason: ?string, department: ?string, colaborador: ?TalentoColaborador}
     */
    public function sync(User $user): array
    {
        // Lectura fresca: nunca fiarse de una relación cacheada de antes de
        // un assignRole()/removeRole() reciente sobre esta misma instancia.
        $user->unsetRelation('roles');
        $roleNames = $user->roles()->pluck('name')->all();

        if (!empty(array_intersect($roleNames, self::SYSTEM_ROLES))) {
            return $this->result('skipped', 'Tiene rol de sistema');
        }

        if ($user->login_user === self::PROTECTED_TEST_LOGIN) {
            return $this->result('skipped', 'Colaborador de prueba protegido');
        }

        $mappings = TalentoRoleDepartment::pluck('department', 'role_name')->all();
        $department = $this->resolveDepartment($roleNames, $mappings);
        if ($department === null) {
            return $this->result('skipped', 'Sin rol de puesto mapeado');
        }

        $existing = TalentoColaborador::withTrashed()->where('user_id', $user->id)->first();

        if ($existing) {
            if ($existing->trashed()) {
                return $this->result('skipped', 'Soft-deleted manualmente', $department, $existing);
            }
            if ($existing->department !== $department) {
                $existing->update(['department' => $department]);
                return $this->result('updated', null, $department, $existing);
            }
            return $this->result('skipped', 'Sin cambios', $department, $existing);
        }

        $colaborador = TalentoColaborador::create([
            'user_id'    => $user->id,
            'type'       => 'interno',
            'department' => $department,
            'status'     => 'active',
        ]);

        return $this->result('created', null, $department, $colaborador);
    }

    private function resolveDepartment(array $roleNames, array $mappings): ?string
    {
        foreach (self::PRIORITY as $priorityRole) {
            if (in_array($priorityRole, $roleNames) && isset($mappings[$priorityRole])) {
                return $mappings[$priorityRole];
            }
        }
        foreach ($roleNames as $role) {
            if (isset($mappings[$role])) {
                return $mappings[$role];
            }
        }
        return null;
    }

    private function result(string $action, ?string $reason, ?string $department = null, ?TalentoColaborador $colaborador = null): array
    {
        return [
            'action'      => $action,
            'reason'      => $reason,
            'department'  => $department,
            'colaborador' => $colaborador,
        ];
    }
}
