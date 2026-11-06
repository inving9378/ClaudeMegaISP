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
