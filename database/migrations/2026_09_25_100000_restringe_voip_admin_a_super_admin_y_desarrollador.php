<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MegaVoz — la configuración de VoIP (troncales, extensiones, grupos de
 * timbrado, bot IA) queda visible SOLO para super-administrator/DESARROLLADOR
 * (decisión de David, 25-sep-2026: "los usuarios que no sean irving o
 * desarrolladores no deberían poder ver las extensiones ni editarlas ni ver
 * troncales ni nada, solo tener disponible las llamadas").
 *
 * Las acciones (create/edit/delete/provision/test) YA estaban correctamente
 * acotadas a esos 2 roles desde que se creó el módulo — el hueco era que los
 * `.view` (más `voip.view`, el que abre el módulo en el sidebar) se habían
 * quedado repartidos de forma amplia (Mostrador/Vendedor/TECNICO/Almacen/
 * Administrador/etc.) — probablemente de cuando `auto_grant_view_base_roles`
 * todavía era `true` (hoy es `false` por defecto, item #309, así que un
 * `permissions:sync-roles` futuro ya NO vuelve a ensanchar esto solo).
 *
 * El mini-teléfono (`/voip/mi-telefono/*`, `MiTelefonoController`) NO usa
 * ningún permiso `voip.*` — resuelve siempre por `auth()->id()` — así que
 * cualquier staff con extensión asignada sigue pudiendo llamar/contestar sin
 * ver nada de esta configuración.
 *
 * Idempotente: solo revoca lo que cada rol tiene hoy; re-ejecutable sin
 * error, portable a prod (no falla si algún rol/permiso no existe ahí).
 * down() a propósito NO re-otorga: la restricción queda aplicada.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'voip.view',
        'voip.troncales.view',
        'voip.extensiones.view',
        'voip.grupos.view',
        'voip.ia-bot.view',
    ];

    private const ROLES_PERMITIDOS = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        foreach (self::PERMISOS as $permName) {
            $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', $permName))
                ->whereNotIn('name', self::ROLES_PERMITIDOS)
                ->get();

            foreach ($roles as $role) {
                $role->revokePermissionTo($permName);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito: la restricción de visibilidad se queda aplicada.
    }
};
