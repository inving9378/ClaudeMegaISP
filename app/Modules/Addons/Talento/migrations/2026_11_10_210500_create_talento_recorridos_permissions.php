<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #1311 (Recorridos, fase a): ver recorridos, importarlos/editarlos y editar el catálogo de
 * categorías de hallazgo. Por defecto solo los roles base (super-administrator + DESARROLLADOR,
 * vía PermissionSyncService); los demás roles se configuran en Administración › Roles.
 * Aditivo e idempotente.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'talento.recorridos.view',
        'talento.recorridos.manage',
        'talento.recorridos.categorias.manage',
    ];

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
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
