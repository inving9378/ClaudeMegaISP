<?php

namespace App\Modules\Addons\Talento\Console;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use Illuminate\Console\Command;

class SyncColaboradoresCommand extends Command
{
    protected $signature   = 'talento:sync-colaboradores';
    protected $description = 'Sincroniza colaboradores a partir del rol de puesto → department. Idempotente.';

    private const SYSTEM_ROLES = ['super-administrator', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR'];

    // Solo roles de PUESTO (no capacidades transversales como Vendedor).
    private const PRIORITY = ['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO', 'Mostrador'];

    /**
     * Colaborador de prueba a proteger de la sincronización.
     * PORTABLE entre entornos: se resuelve por login_user (estable), NO por id
     * hardcodeado (los ids de dev y prod divergen — un id de dev es otra persona
     * en prod). En prod este login no existe → protectedId=null → no protege a nadie.
     */
    private const PROTECTED_TEST_LOGIN = 'Meganet6503bee0';

    public function handle(): int
    {
        // Resuelto una sola vez; null si el colaborador de prueba no existe (p.ej. prod).
        $protectedId = User::where('login_user', self::PROTECTED_TEST_LOGIN)->value('id');

        $mappings = TalentoRoleDepartment::pluck('department', 'role_name')->all();

        if (empty($mappings)) {
            $this->error('La tabla talento_role_departments está vacía. Corre las migraciones.');
            return self::FAILURE;
        }

        $mappedRoles = array_keys($mappings);

        // ── Fase 1: alta/update de usuarios con rol de puesto ─────────────────
        $this->info('Fase 1 — Procesando usuarios con rol de puesto…');

        $headers = ['user_id', 'Nombre', 'Roles', 'Resultado', 'Department', 'Motivo'];
        $rows    = [];
        $totalAltas   = 0;
        $totalUpdates = 0;
        $totalSkipped = 0;

        $usersWithPuestoRole = User::whereHas('roles', fn($q) => $q->whereIn('name', $mappedRoles))
            ->with('roles')
            ->get();

        foreach ($usersWithPuestoRole as $user) {
            $roleNames = $user->roles->pluck('name')->all();

            if (!empty(array_intersect($roleNames, self::SYSTEM_ROLES))) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Tiene rol de sistema'];
                continue;
            }

            if ($protectedId !== null && $user->id === $protectedId) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Colaborador de prueba protegido'];
                continue;
            }

            $department = $this->resolveDepartment($roleNames, $mappings);
            if ($department === null) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Sin rol de puesto mapeado'];
                continue;
            }

            // Nota de conflicto entre puestos (raro, pero posible)
            $puestoRoles  = array_filter($roleNames, fn($r) => isset($mappings[$r]));
            $puestoDepts  = array_unique(array_map(fn($r) => $mappings[$r], $puestoRoles));
            $conflict     = count($puestoDepts) > 1
                ? 'Conflicto resuelto por prioridad: ' . implode(' vs ', $puestoDepts)
                : '';

            $existing = TalentoColaborador::withTrashed()->where('user_id', $user->id)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $totalSkipped++;
                    $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Soft-deleted manualmente'];
                    continue;
                }
                $changed = $existing->department !== $department;
                if ($changed) {
                    $existing->update(['department' => $department]);
                    $result = in_array($existing->status, ['inactive', 'suspended'])
                        ? 'department actualizado (status conservado)'
                        : 'department actualizado';
                    $totalUpdates++;
                } else {
                    $result = 'sin cambios';
                    $totalSkipped++;
                }
            } else {
                TalentoColaborador::create([
                    'user_id'    => $user->id,
                    'type'       => 'interno',
                    'department' => $department,
                    'status'     => 'active',
                ]);
                $result = 'alta nueva';
                $totalAltas++;
            }

            $rows[] = [$user->id, $user->name, implode(',', $roleNames), $result, $department, $conflict];
        }

        $this->table($headers, $rows);
        $this->info("Fase 1 — Altas: {$totalAltas} | Actualizados: {$totalUpdates} | Omitidos/sin cambios: {$totalSkipped}");

        // ── Fase 2: inactivar colaboradores sin rol de puesto ─────────────────
        $this->info('');
        $this->info('Fase 2 — Buscando colaboradores sin rol de puesto…');

        $userIdsConPuesto = $usersWithPuestoRole->pluck('id')->all();

        // Colaboradores activos cuyo user_id NO tiene ningún rol de puesto mapeado
        $sinPuesto = TalentoColaborador::where('status', 'active')
            ->when($protectedId !== null, fn ($q) => $q->where('user_id', '!=', $protectedId))
            ->whereNotIn('user_id', $userIdsConPuesto)
            ->with('user.roles')
            ->get();

        $totalInactivados = 0;
        $fase2Rows = [];

        foreach ($sinPuesto as $col) {
            $roles = $col->user?->roles->pluck('name')->implode(',') ?? '—';
            $col->update(['status' => 'inactive']);
            $totalInactivados++;
            $fase2Rows[] = [$col->user_id, $col->user?->name ?? '—', $roles, 'inactive', $col->department ?? '—', 'Sin rol de puesto'];
        }

        if ($fase2Rows) {
            $this->table(['user_id', 'Nombre', 'Roles', 'Nuevo status', 'Department', 'Motivo'], $fase2Rows);
        }

        $this->info("Fase 2 — Inactivados: {$totalInactivados}");

        return self::SUCCESS;
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
}
