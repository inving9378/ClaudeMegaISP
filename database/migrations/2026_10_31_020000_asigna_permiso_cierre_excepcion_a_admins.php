<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Item #1103: asigna talento.ordenes.cierre_excepcion (creado en 2026_10_31_010000) a super-administrator y
// DESARROLLADOR. Aditivo e idempotente (givePermissionTo, nunca sync); no toca ningún otro rol.
return new class extends Migration {
    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        $permiso = Permission::firstOrCreate(['name' => 'talento.ordenes.cierre_excepcion', 'guard_name' => 'web']);

        foreach (self::ROLES as $nombre) {
            $rol = Role::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($rol && ! $rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permiso = Permission::where('name', 'talento.ordenes.cierre_excepcion')->where('guard_name', 'web')->first();
        if (! $permiso) {
            return;
        }

        foreach (self::ROLES as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'web')->first()?->revokePermissionTo($permiso);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
