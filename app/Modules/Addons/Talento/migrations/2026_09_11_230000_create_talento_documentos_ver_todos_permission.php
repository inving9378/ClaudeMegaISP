<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990830 (sub-item de #9990816, Fase 1 del tablero de pendientes de firma).
 * Permiso propio para el LISTADO ADMIN cross-colaborador de documentos pendientes de firma
 * (distinto de 'talento.expediente.view', que gatea la ficha de UN colaborador a la vez).
 * DECISIÓN YA TOMADA (ver reporte de #9990816): no existe rol 'RH' en el sistema hoy (verificado
 * en BD los 17 roles reales) -> se asigna solo a los roles base (super-administrator +
 * DESARROLLADOR) vía syncPermissionToBaseRoles, sin rol RH. Mismo patrón que
 * 2026_08_26_220200_create_talento_expediente_view_permission.php. Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.documentos.ver-todos', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.documentos.ver-todos');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
