<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos del motor de reglas de desempeño semanal por combinación de
 * roles. Sin grant a ningún rol de negocio específico (a diferencia de
 * comisiones.view, que espejaba un acceso ya verificado) — esto es
 * política nueva, no un espejo de algo existente. Solo el sync a roles
 * base/sistema; Irving asigna manualmente desde el editor de roles a
 * quién corresponda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.reglas_desempeno.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'talento.reglas_desempeno.manage', 'guard_name' => 'web']);

        $sync = app(PermissionSyncService::class);
        $sync->syncPermissionToBaseRoles('talento.reglas_desempeno.view');
        $sync->syncPermissionToBaseRoles('talento.reglas_desempeno.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
