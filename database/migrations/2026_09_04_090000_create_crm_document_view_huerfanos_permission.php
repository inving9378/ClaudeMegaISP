<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Item #9990244 (Fase 1/2 de #9990085) — permiso de la pantalla "Documentos
 * huérfanos" del CRM (reporte de solo lectura + export CSV). Sigue la
 * convención `crm_document_*` ya existente en config/route_permission.php
 * (guion bajo, NO notación con puntos) para mantener consistencia con el
 * resto del módulo.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles` (mismo camino que
 * ModuleLifecycleService::registerPermissions). Sin sufijo `.view`, así que
 * PermissionSyncService::isViewPermission() NO lo reparte a todos los roles
 * base automáticamente — queda en super-administrator + DESARROLLADOR
 * (reporte de documentos de clientes, exposición acotada a propósito).
 */
return new class extends Migration
{
    private const NOMBRE = 'crm_document_view_huerfanos';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::NOMBRE, 'guard_name' => 'web'],
            ['description' => 'Ver y exportar el reporte de documentos CRM huérfanos (sin archivo físico)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::NOMBRE);
    }

    public function down(): void
    {
        Permission::where('name', self::NOMBRE)->delete();
    }
};
