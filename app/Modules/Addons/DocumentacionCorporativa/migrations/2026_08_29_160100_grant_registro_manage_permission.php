<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 2c (item roadmap #736). `documentacion-corporativa.registro.manage`
 * gatea crear/editar los 5 registros estructurados (accionistas, capital,
 * actas, poderes, contratos). Se declaró NUEVO en vez de reusar
 * `documentacion-corporativa.concepto.manage` porque ese permiso significa
 * algo distinto: administrar el CATÁLOGO de conceptos (Fase 0), no capturar
 * datos dentro de un concepto ya existente — mismo criterio que ya separa
 * `.concesion.manage` (apartado XIII) de `.concepto.manage`.
 * Aditiva/idempotente: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.registro.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.registro.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
