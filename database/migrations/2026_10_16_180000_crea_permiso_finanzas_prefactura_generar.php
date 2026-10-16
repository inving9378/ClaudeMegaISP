<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item #480/#498 — permiso del botón «Prefactura actualizada» (finanzas_prefactura_generar).
 *
 * Está declarado en el module.json de Finanzas, pero el deploy remoto de prod NO corre
 * `permissions:sync-roles`; sin esto el permiso no existiría allá y el botón no le saldría a nadie.
 * Se crea aquí y se da SOLO a super-administrator y DESARROLLADOR, sin el auto-grant de `.view` a
 * otros roles ni la matriz de revocación que trae `permissions:sync-roles`. Idempotente.
 */
return new class extends Migration
{
    private const PERMISO = 'finanzas_prefactura_generar';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        $extra = [];
        if (Schema::hasColumn('permissions', 'description') && ! $permiso->description) {
            $extra['description'] = 'Generar la prefactura actualizada (PDF informativo) de una factura';
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
