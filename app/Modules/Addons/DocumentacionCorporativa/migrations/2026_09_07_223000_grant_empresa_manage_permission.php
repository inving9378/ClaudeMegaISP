<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * DC plazo 180d hábiles — Fase 3 (item roadmap #9990575).
 * `documentacion-corporativa.empresa.manage` gatea la captura de
 * `dc_empresas.fecha_inicio_plazo` — dato legal (oficio de la mesa directiva),
 * no se abre a todo el que solo VE el expediente. Mismo patrón aditivo/idempotente
 * que el resto de altas de permiso del módulo: crea el permiso y lo asigna a los
 * roles base (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.empresa.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.empresa.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
