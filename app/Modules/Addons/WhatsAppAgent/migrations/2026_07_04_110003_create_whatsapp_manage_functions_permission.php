<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * FASE 3 — Permiso del catálogo de funciones WhatsApp.
 *
 * whatsapp_manage_functions — crear/editar/togglear exclusiva/borrar funciones del
 * catálogo. (Asignar/quitar función↔línea usa whatsapp_manage_instances, ya existente.)
 * Se asigna a los MISMOS roles que ya tienen los otros permisos de WhatsApp.
 * Aditivo (givePermissionTo, nunca sync) e idempotente. Al crearse, el submenú
 * "Funciones" del sidebar (Fase 4a) se auto-revela para estos roles.
 */
return new class extends Migration
{
    private const PERMISSION = 'whatsapp_manage_functions';

    private const ROLES = [
        'super-administrator',
        'Super Administrador',
        'Administrador',
        'DESARROLLADOR',
        'ADMINISTRADOR_COMPLETO',
    ];

    public function up(): void
    {
        $perm = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->first();
        if ($perm) {
            $perm->delete(); // quita el permiso y sus asignaciones
        }
    }
};
