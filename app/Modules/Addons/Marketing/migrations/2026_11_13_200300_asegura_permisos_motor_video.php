<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketing IA 360 · Fase 2 (F2-6) — permisos del motor de video (Video MVM).
 *
 * En dev y prod ya existen (los creó `database/seeders/Marketing/Phase4PermissionsSeeder.php`), así que ahí esta
 * migración NO cambia nada: ni crea ni reparte. En una base creada desde migraciones (`megaisp_test`) no existían y
 * las pantallas y APIs de video respondían 403 a todos. Solo cuando ESTA migración crea el permiso, se lo da
 * (ADITIVO, `givePermissionTo`) a super-administrator y DESARROLLADOR. Nunca syncRoles/syncPermissions.
 * Idempotente. `down()` vacío: catálogo de permisos, mismo criterio que las migraciones de Fase 0 y 1.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'view-video-templates',
        'view-video-content',
        'generate-video-content',
        'delete-video-content',
        'download-video',
    ];

    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', self::ROLES)->get();

        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);

            if (! $permiso->wasRecentlyCreated) {
                continue;
            }

            foreach ($roles as $rol) {
                if (! $rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intencionalmente vacío: ver el comentario de la clase.
    }
};
