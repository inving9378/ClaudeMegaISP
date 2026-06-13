<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Aplica la matriz de revocación de permisos de módulos internos por rol.
 * Idempotente: puede correrse múltiples veces sin efecto secundario.
 *
 * Decisión Irving 2026-06-12: los roles de acceso mixto/portal-cliente
 * no deben tener permisos de lectura sobre módulos internos de operación.
 */
class RolePermissionRevocationSeeder extends Seeder
{
    // ── Permisos de propio perfil que los técnicos SÍ conservan ────────────────
    private const TALENTO_OWN_PROFILE = [
        'talento.view',           // gate del módulo (sidebar)
        'talento.dashboard.view', // dashboard propio
        'talento.attendance.view',// asistencia propia
        'talento.academy.view',   // academia/cursos
        'talento.levels.view',    // nivel propio
        'talento.work_orders.view',  // sus OTs
        'talento.work_sites.view',   // sitios donde trabaja
        'talento.devices.view',      // su dispositivo asignado
        'talento.location.view',     // su ubicación GPS
        'talento.media.view',        // media de sus OTs
        'talento.signatures.view',   // firmas de sus OTs
        'talento.health_bonus.view', // bono de salud de red
        'talento.warranty.view',     // garantías de sus instalaciones
        'talento.custody.view',      // equipo en custodia propia
        'talento.embajadores.view',  // si también es embajador
    ];

    public function run(): void
    {
        // ── Resolver permisos reales desde BD por módulo ───────────────────────
        $allCobranza    = $this->permsLike('cobranza.%');
        $allEmbajadores = $this->permsLike('embajadores.%');
        $allFleet       = $this->permsLike('fleet.%');
        $allTalento     = $this->permsLike('talento.%');
        $allWarroom     = $this->permsLike('warroom.%');
        $allIa          = $this->permsExact(['ia_view_chat', 'ia_view_prompts']);
        $voipBasicos    = $this->permsExact(['voip.view', 'voip.troncales.view']);
        $allMegafamilia = $this->permsLike('megafamilia%');

        // Talento admin = todo talento minus los de propio perfil
        $talentoAdmin = array_diff($allTalento, self::TALENTO_OWN_PROFILE);

        // ── Matriz: rol => lista de permisos a revocar ────────────────────────
        $matrix = [

            // Clientes ISP: solo conservan megafamilia_view
            'client' => $this->merge(
                $allCobranza, $allEmbajadores, $allFleet, $allTalento,
                $allWarroom, $voipBasicos, $allIa
                // NO incluimos $allMegafamilia — megafamilia_view se conserva
            ),

            // Vendedor: conserva embajadores.view + megafamilia_view
            'Vendedor' => $this->merge(
                $allCobranza,
                $this->except($allEmbajadores, ['embajadores.view']),
                $allFleet, $allTalento, $allWarroom, $voipBasicos, $allIa
            ),

            // Mostrador: igual que Vendedor
            'Mostrador' => $this->merge(
                $allCobranza,
                $this->except($allEmbajadores, ['embajadores.view']),
                $allFleet, $allTalento, $allWarroom, $voipBasicos, $allIa
            ),

            // SUPERVISOR_MOSTRADOR: igual que Vendedor
            'SUPERVISOR_MOSTRADOR' => $this->merge(
                $allCobranza,
                $this->except($allEmbajadores, ['embajadores.view']),
                $allFleet, $allTalento, $allWarroom, $voipBasicos, $allIa
            ),

            // Técnicos: conservan fleet.* completo + talento propio perfil + megafamilia_view
            'TECNICO' => $this->merge(
                $allCobranza, $allEmbajadores,
                // NO revocamos fleet
                $talentoAdmin,
                $allWarroom, $voipBasicos, $allIa
            ),
            'TECNICO_INSTALADOR' => $this->merge(
                $allCobranza, $allEmbajadores,
                $talentoAdmin,
                $allWarroom, $voipBasicos, $allIa
            ),
            'TECNICO_PLANTA' => $this->merge(
                $allCobranza, $allEmbajadores,
                $talentoAdmin,
                $allWarroom, $voipBasicos, $allIa
            ),

            // CONTADOR: conserva cobranza.view + megafamilia_view
            'CONTADOR' => $this->merge(
                $this->except($allCobranza, ['cobranza.view']),
                $allEmbajadores, $allFleet, $allTalento, $allWarroom, $voipBasicos, $allIa
            ),

            // Almacen: conserva fleet.* completo + megafamilia_view
            'Almacen' => $this->merge(
                $allCobranza, $allEmbajadores,
                // NO revocamos fleet
                $allTalento, $allWarroom, $voipBasicos, $allIa
            ),

            // Roles sin usuarios activos: revocar todo lo interno incluyendo megafamilia
            'conductor'  => $this->merge(
                $allCobranza, $allEmbajadores, $allFleet, $allTalento,
                $allWarroom, $voipBasicos, $allIa, $allMegafamilia
            ),
            'PUBLICADOR' => $this->merge(
                $allCobranza, $allEmbajadores, $allFleet, $allTalento,
                $allWarroom, $voipBasicos, $allIa, $allMegafamilia
            ),
            'Socio' => $this->merge(
                $allCobranza, $allEmbajadores, $allFleet, $allTalento,
                $allWarroom, $voipBasicos, $allIa, $allMegafamilia
            ),
        ];

        // ── Aplicar revocaciones ───────────────────────────────────────────────
        $this->command->info('Aplicando revocaciones de permisos por rol...');

        foreach ($matrix as $roleName => $permsToRevoke) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                $this->command->warn("  AVISO: Rol no encontrado en BD — {$roleName}");
                continue;
            }

            $revoked = 0;
            foreach (array_unique($permsToRevoke) as $permName) {
                $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                if ($perm && $role->hasPermissionTo($perm)) {
                    $role->revokePermissionTo($perm);
                    $revoked++;
                }
            }

            $this->command->info(sprintf(
                '  %-26s → %d permisos revocados (de %d elegibles)',
                $roleName,
                $revoked,
                count(array_unique($permsToRevoke))
            ));
        }

        // ── Limpiar caché Spatie ───────────────────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('Caché de permisos Spatie limpiada.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Obtiene nombres de permisos que coinciden con un patrón LIKE. */
    private function permsLike(string $pattern): array
    {
        return Permission::where('guard_name', 'web')
            ->where('name', 'like', $pattern)
            ->pluck('name')
            ->toArray();
    }

    /** Filtra permisos de una lista que existen en BD. */
    private function permsExact(array $names): array
    {
        return Permission::where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('name')
            ->toArray();
    }

    /** Merge de múltiples arrays (variadic). */
    private function merge(array ...$arrays): array
    {
        return array_merge(...$arrays);
    }

    /** Excluye ciertos nombres de un array. */
    private function except(array $perms, array $exclude): array
    {
        return array_values(array_diff($perms, $exclude));
    }
}
