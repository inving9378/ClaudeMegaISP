<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reforma de permisos B2 — adopción por prevalencia + limpieza total de directos del staff.
 *
 * El rol es la única fuente de verdad. Este comando:
 *   B2.1 Pre-flight (read-only): staff, directos totales, huérfanos.
 *   B2.2 Adopción por prevalencia: si un permiso huérfano lo tienen >=50% de los
 *        usuarios de un rol -> se AGREGA al rol (additive). Excluye super-administrator,
 *        DESARROLLADOR y client.
 *   B2.3 Limpieza: se vacían TODOS los permisos directos de los usuarios con rol staff
 *        (>= 1 rol != client). NO toca usuarios cuyo único rol es client (espejos).
 *   B2.4 Post-flight: directos del staff = 0; conteo por rol antes/después.
 *
 * Idempotente: por defecto DRY-RUN (no escribe). Con --apply ejecuta dentro de una
 * transacción. Re-correrlo tras aplicar no adopta ni limpia nada (directos ya en 0).
 *
 *   php artisan permisos:reforma-b2            # dry-run (evidencia, sin escribir)
 *   php artisan permisos:reforma-b2 --apply    # aplica adopción + limpieza
 */
class PermisosReformaB2Command extends Command
{
    protected $signature = 'permisos:reforma-b2 {--apply : Ejecuta los cambios (por defecto es dry-run)}';

    protected $description = 'Reforma de permisos B2: adopción por prevalencia de huérfanos + limpieza total de directos del staff';

    /** Roles que NO participan de la adopción (ya cubren todo, o no aplican). */
    private const ROLES_EXCLUIDOS_ADOPCION = ['super-administrator', 'DESARROLLADOR', 'client'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line('');
        $this->info('=== Reforma de permisos B2 — ' . ($apply ? 'APPLY (escribe)' : 'DRY-RUN (no escribe)') . ' ===');

        // Cargar staff = usuarios con >= 1 rol != client
        $staff = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->with(['roles.permissions', 'permissions'])
            ->get();

        // ---------- B2.1 Pre-flight ----------
        $directosTotales = $staff->sum(fn ($u) => $u->permissions->count());
        $orphanRows = 0;
        foreach ($staff as $u) {
            $orphanRows += $this->orphansOf($u)->count();
        }
        $this->line('');
        $this->line('B2.1 Pre-flight:');
        $this->line('  usuarios staff (rol != client): ' . $staff->count());
        $this->line('  permisos directos del staff (total): ' . $directosTotales);
        $this->line('  filas huérfanas (directo no cubierto por ningún rol): ' . $orphanRows);

        // ---------- B2.2 Adopción por prevalencia ----------
        $roles = Role::whereNotIn('name', self::ROLES_EXCLUIDOS_ADOPCION)->get();
        $adopciones = [];   // rol => [permisos]
        foreach ($roles as $role) {
            $miembros = User::role($role->name)->with(['roles.permissions', 'permissions'])->get();
            $total = $miembros->count();
            if ($total === 0) {
                continue;
            }
            $rolePerms = $role->permissions->pluck('name')->flip();
            $conteo = [];   // permiso => # miembros para quienes es huérfano
            foreach ($miembros as $m) {
                foreach ($this->orphansOf($m) as $p) {
                    $conteo[$p] = ($conteo[$p] ?? 0) + 1;
                }
            }
            foreach ($conteo as $perm => $c) {
                if (isset($rolePerms[$perm])) {
                    continue; // ya está en el rol
                }
                if ($c / $total >= 0.5) {
                    $adopciones[$role->name][] = $perm;
                }
            }
        }

        $this->line('');
        $this->line('B2.2 Adopción por prevalencia (>=50% de los usuarios del rol):');
        if (empty($adopciones)) {
            $this->line('  (ninguna adopción — no hay huérfanos que alcancen el umbral)');
        } else {
            foreach ($adopciones as $rol => $perms) {
                $miembros = User::role($rol)->count();
                $this->line("  ROL {$rol} (miembros={$miembros}) adopta " . count($perms) . ' permisos:');
                foreach ($perms as $p) {
                    $this->line("     + {$p}");
                }
            }
        }

        // Conteo por rol ANTES (para la tabla antes/después)
        $antesRol = [];
        foreach ($roles as $r) {
            $antesRol[$r->name] = $r->permissions()->count();
        }

        if (! $apply) {
            $this->line('');
            $this->warn('DRY-RUN: no se escribió nada. Ejecuta con --apply para aplicar.');
            return self::SUCCESS;
        }

        // ---------- APPLY ----------
        DB::beginTransaction();
        try {
            // B2.2 aplicar adopciones (additive)
            foreach ($adopciones as $rol => $perms) {
                Role::findByName($rol, 'web')->givePermissionTo($perms);
            }

            // B2.3 limpieza total de directos del staff
            foreach ($staff as $u) {
                $u->syncPermissions([]); // vacía SOLO permisos directos; roles intactos
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('ERROR — rollback: ' . $e->getMessage());
            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ---------- B2.4 Post-flight ----------
        $staffPost = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))
            ->with('permissions')->get();
        $directosPost = $staffPost->sum(fn ($u) => $u->permissions->count());

        $this->line('');
        $this->line('B2.4 Post-flight:');
        $this->line('  permisos directos del staff (total): ' . $directosPost . '  (esperado 0)');
        $this->line('');
        $this->line('  Conteo de permisos por rol (antes -> después):');
        foreach ($roles as $r) {
            $despues = $r->fresh()->permissions()->count();
            $delta = $despues - ($antesRol[$r->name] ?? 0);
            if ($delta !== 0 || in_array($r->name, array_keys($adopciones), true)) {
                $this->line("     {$r->name}: {$antesRol[$r->name]} -> {$despues} (" . ($delta >= 0 ? "+{$delta}" : $delta) . ')');
            }
        }

        $this->line('');
        $this->info($directosPost === 0 ? 'OK — directos del staff = 0. Reforma B2 aplicada.' : 'ATENCIÓN — quedaron directos > 0, revisar.');
        return self::SUCCESS;
    }

    /**
     * Permisos DIRECTOS del usuario que NINGUNO de sus roles cubre (huérfanos).
     */
    private function orphansOf(User $u)
    {
        $rolePerms = $u->roles->flatMap->permissions->pluck('name')->unique();
        return $u->permissions->pluck('name')->diff($rolePerms);
    }
}
