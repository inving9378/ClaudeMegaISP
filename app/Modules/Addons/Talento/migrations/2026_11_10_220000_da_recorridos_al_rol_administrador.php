<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #1316 (decisión 1 de Irving, 2026-11-10): el rol Administrador ve e importa recorridos y edita
 * el catálogo de categorías de hallazgo. TECNICO sigue sin acceso hasta la app (#1312).
 * Aditivo (givePermissionTo) e idempotente; se resuelve por nombre de rol, nunca por id. Si el rol no
 * existe en el entorno, no hace nada.
 */
return new class extends Migration
{
    private const ROL = 'Administrador';

    private const PERMISOS = [
        'talento.recorridos.view',
        'talento.recorridos.manage',
        'talento.recorridos.categorias.manage',
    ];

    public function up(): void
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if (! $rol) {
            return;
        }
        foreach (self::PERMISOS as $nombre) {
            $rol->givePermissionTo(Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if ($rol) {
            foreach (self::PERMISOS as $nombre) {
                if (Permission::where('name', $nombre)->where('guard_name', 'web')->exists()) {
                    $rol->revokePermissionTo($nombre);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
