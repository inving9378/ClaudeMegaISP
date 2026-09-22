<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Hasta hoy `empresa_manual_view` solo lo tenían super-administrator y
 * DESARROLLADOR — el resto del staff ni siquiera podía ABRIR el manual. Con
 * la visibilidad por sección (visible_roles, migración hermana de este mismo
 * lote) ya es seguro abrirlo a todo el staff operativo: cada quien solo ve
 * las secciones etiquetadas para su rol (o todas, las que no tengan
 * restricción). `client` (portal del cliente) queda fuera a propósito — este
 * es el manual interno. Roles muertos (PUBLICADOR/Socio, 0 usuarios reales
 * salvo el legado de comisiones) también quedan fuera.
 *
 * Solo se otorga `empresa_manual_view` (lectura). Crear/editar/eliminar/
 * publicar siguen siendo exclusivos de super-administrator/DESARROLLADOR.
 */
return new class extends Migration
{
    private const PERMISSION = 'empresa_manual_view';

    private const ROLES_STAFF = [
        'Administrador',
        'ADMINISTRADOR_COMPLETO',
        'Super Administrador',
        'Almacen',
        'CONTADOR',
        'Mostrador',
        'SUPERVISOR_MOSTRADOR',
        'TECNICO',
        'TECNICO_INSTALADOR',
        'TECNICO_PLANTA',
        'Vendedor',
        'conductor',
    ];

    public function up(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->first();
        if (!$perm) {
            return;
        }

        foreach (self::ROLES_STAFF as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->first();
        if (!$perm) {
            return;
        }

        foreach (self::ROLES_STAFF as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
