<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos OLT permanentes para los roles de mostrador (decisión Irving).
 *
 * Contexto: el rol "Mostrador" opera el alta de ONT ("Mirar / Fijar") pero NO
 * tenía los permisos OLT → POST /olts/onus/get-by-client/{id} devolvía 403
 * (CheckRoutePermission corta; getOLTData enmascara el 403 como []).
 *
 * No existe un mapa declarativo rol→permiso: PermissionSyncService solo reparte
 * los .view (y estos son olt_view/onu_add/onu_edit, guion bajo, NO .view) y da
 * TODO a super-administrator/DESARROLLADOR. Por eso el grant permanente vive aquí,
 * como migración idempotente (mismo patrón que portal.colaborador / grant_apply_
 * payments_to_megaisp). Corre en cada deploy (migrate --force), sobrevive re-syncs
 * (el sync nunca revoca) y es portable (resuelve rol y permiso por NOMBRE, nunca
 * por id — los ids de dev y prod divergen).
 *
 * Aditivo/idempotente: givePermissionTo solo si falta. NUNCA syncRoles/syncPermissions.
 * Forward-only: down() vacío (no se revierte un permiso operativo en uso).
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles    = ['Mostrador', 'SUPERVISOR_MOSTRADOR'];
        $permisos = ['olt_view', 'onu_add', 'onu_edit'];

        foreach ($roles as $rn) {
            $rol = Role::where('name', $rn)->where('guard_name', 'web')->first();
            if (! $rol) {
                continue; // portable: si el rol no existe en el entorno, no rompe
            }

            foreach ($permisos as $pn) {
                $perm = Permission::where('name', $pn)->where('guard_name', 'web')->first();
                if ($perm && ! $rol->hasPermissionTo($perm)) {
                    $rol->givePermissionTo($perm); // ADITIVO
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte un permiso operativo en uso.
    }
};
