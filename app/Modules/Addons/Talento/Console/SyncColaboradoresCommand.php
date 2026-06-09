<?php

namespace App\Modules\Addons\Talento\Console;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncColaboradoresCommand extends Command
{
    protected $signature   = 'talento:sync-colaboradores';
    protected $description = 'Sincroniza (alta/update) colaboradores a partir del rol Spatie → department. Idempotente.';

    private const SYSTEM_ROLES = ['super-administrator', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR'];

    // Prioridad de roles: índice menor = mayor prioridad
    private const PRIORITY = ['TECNICO_PLANTA', 'TECNICO_INSTALADOR', 'TECNICO', 'Mostrador', 'Vendedor'];

    // user_id del colaborador de prueba — nunca se toca
    private const PROTECTED_USER_ID = 4818;

    public function handle(): int
    {
        $mappings = TalentoRoleDepartment::pluck('department', 'role_name')->all();

        if (empty($mappings)) {
            $this->error('La tabla talento_role_departments está vacía. Corre las migraciones.');
            return self::FAILURE;
        }

        $mappedRoles  = array_keys($mappings);
        $headers      = ['user_id', 'Nombre', 'Roles', 'Resultado', 'Department', 'Motivo'];
        $rows         = [];
        $totalAltas   = 0;
        $totalUpdates = 0;
        $totalSkipped = 0;

        // Load users that have at least one mapped role via Spatie model_has_roles + roles tables
        $usersWithMappedRoles = User::whereHas('roles', fn($q) => $q->whereIn('name', $mappedRoles))
            ->with('roles')
            ->get();

        foreach ($usersWithMappedRoles as $user) {
            $roleNames = $user->roles->pluck('name')->all();

            // Skip sistema
            $hasSystemRole = !empty(array_intersect($roleNames, self::SYSTEM_ROLES));
            if ($hasSystemRole) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Tiene rol de sistema'];
                continue;
            }

            // Skip colaborador de prueba
            if ($user->id === self::PROTECTED_USER_ID) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Colaborador de prueba protegido'];
                continue;
            }

            // Determinar department por prioridad
            $department = $this->resolveDepartment($roleNames, $mappings);
            if ($department === null) {
                $totalSkipped++;
                $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Sin rol mapeado'];
                continue;
            }

            // Determinar conflicto (múltiples roles mapeados con distintos departments)
            $mappedForUser = array_filter($roleNames, fn($r) => isset($mappings[$r]));
            $departments   = array_unique(array_map(fn($r) => $mappings[$r], $mappedForUser));
            $conflict      = count($departments) > 1
                ? 'Conflicto resuelto por prioridad: ' . implode(' vs ', $departments)
                : '';

            // Upsert colaborador
            $existing = TalentoColaborador::withTrashed()->where('user_id', $user->id)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    // Soft-deleted manualmente → NO restaurar; solo reportar
                    $totalSkipped++;
                    $rows[] = [$user->id, $user->name, implode(',', $roleNames), 'omitido', '—', 'Soft-deleted manualmente; restauración requiere intervención manual'];
                    continue;
                }
                // inactive / suspended → respeta el status manual; solo actualiza department si cambió
                $changed = $existing->department !== $department;
                if ($changed) {
                    $existing->update(['department' => $department]);
                    $result = in_array($existing->status, ['inactive', 'suspended'])
                        ? 'department actualizado (status manual conservado)'
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
        $this->info("Altas nuevas/restaurados: {$totalAltas} | Department actualizado: {$totalUpdates} | Omitidos: {$totalSkipped}");

        return self::SUCCESS;
    }

    private function resolveDepartment(array $roleNames, array $mappings): ?string
    {
        // Recorre la lista de prioridad y devuelve el department del primer rol que el usuario tenga
        foreach (self::PRIORITY as $priorityRole) {
            if (in_array($priorityRole, $roleNames) && isset($mappings[$priorityRole])) {
                return $mappings[$priorityRole];
            }
        }
        // Fallback: cualquier rol mapeado que no esté en PRIORITY
        foreach ($roleNames as $role) {
            if (isset($mappings[$role])) {
                return $mappings[$role];
            }
        }
        return null;
    }
}
