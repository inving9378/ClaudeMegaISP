<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #1225 (Irving, 2026-11-09): editar y eliminar una orden de trabajo mientras está Pendiente.
 * Dos permisos independientes (eliminar no implica editar). Por defecto solo los roles base
 * (super-administrator + DESARROLLADOR, vía PermissionSyncService); los demás roles se configuran
 * a mano en Administración › Roles. Aditivo e idempotente.
 */
return new class extends Migration
{
    private const PERMISOS = ['talento.ordenes.editar', 'talento.ordenes.eliminar'];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($nombre);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Borrar el permiso quita también sus asignaciones a roles y usuarios (cascada de Spatie).
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
