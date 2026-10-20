<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vuelve a dar a Vendedor y Mostrador los permisos de Talento que ya les dieron
 * migraciones anteriores (2026-09-21 comisiones, 09-28 Mostrador, 10-08/10-10/10-14
 * Vendedor, 10-13 Mostrador admin-lite). En la copia de producción del 2026-10-15
 * esas migraciones figuran como corridas pero los permisos no están en los roles
 * (ya había pasado el 10-oct con Vendedor), así que no vuelven solas: hace falta una
 * migración nueva que corra en la siguiente actualización.
 *
 * Sin estos permisos un vendedor o un mostrador no abre su ficha de Talento.
 * Solo agrega (givePermissionTo); no quita nada. Idempotente: un rol que ya tiene el
 * permiso se salta. Roles por nombre, nunca por id (dev y prod no coinciden).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'Vendedor' => [
            'talento.view',
            'talento.ventas.view',
            'talento.comisiones.view',
        ],
        'Mostrador' => [
            'talento.view',
            'talento.work_orders.view',
            'talento.work_orders.manage',
            'talento.custody.view',
            'talento.comisiones.view',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $roleName => $names) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }
            $agregados = [];
            foreach ($names as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                    $agregados[] = $name;
                }
            }
            if ($agregados) {
                Log::info("Talento: permisos devueltos al rol {$roleName}", ['permisos' => $agregados]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * No se revierte: son permisos que estos roles ya debían tener por migraciones
     * anteriores; quitarlos al hacer rollback los dejaría sin acceso a su ficha.
     */
    public function down(): void
    {
    }
};
