<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #582 — permiso gestion-red.provision.preview (vista previa de aprovisionamiento ONU).
 *
 * Declarado en el module.json de GestionRed y usado por OLTsProvisionController, pero ausente de la
 * tabla `permissions`. Se crea aquí y se da SOLO a super-administrator y DESARROLLADOR. Idempotente.
 */
return new class extends Migration
{
    private const PERMISO = 'gestion-red.provision.preview';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        $extra = [];
        if (Schema::hasColumn('permissions', 'description') && ! $permiso->description) {
            $extra['description'] = 'Vista previa del aprovisionamiento de una ONU';
        }
        if (Schema::hasColumn('permissions', 'context') && ! $permiso->context) {
            $extra['context'] = 'panel';
        }
        if ($extra) {
            $permiso->forceFill($extra)->save();
        }

        foreach (['super-administrator', 'DESARROLLADOR'] as $nombre) {
            $rol = Role::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($rol && ! $rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // A propósito vacío: el permiso pudo asignarse después a otros roles desde la pantalla de
        // Roles; quitarlo al revertir les borraría esa asignación.
    }
};
