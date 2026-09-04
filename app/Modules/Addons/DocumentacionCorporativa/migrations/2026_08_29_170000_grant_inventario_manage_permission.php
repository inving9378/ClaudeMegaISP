<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 3.2 (item roadmap #751). `documentacion-corporativa.inventario.manage`
 * gatea crear/editar/eliminar los 3 recursos de inventario nuevos (dc_activos,
 * dc_activos_digitales, dc_inventario_accesos — apartados V, VIII y XI). Un
 * solo permiso de escritura para las 3 tablas, mismo criterio de minimalismo
 * que `.concesion.manage` y `.registro.manage` (VER lo gatea el permiso del
 * apartado dueño de cada recurso, ADMINISTRAR lo gatea este). Aditiva/
 * idempotente: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.inventario.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.inventario.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
