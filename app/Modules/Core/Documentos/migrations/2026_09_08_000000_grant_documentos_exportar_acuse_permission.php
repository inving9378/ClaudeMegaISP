<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990572 (seguimiento de #9990551, q2 — respuesta de Irving: Opción 1).
 * Permiso separado del CRUD de plantillas (`documentos.view/create/edit/delete`) para
 * exportar el acuse de avance en PDF del catálogo `document_templates`. Aditiva/idempotente:
 * crea el permiso y lo asigna a los roles base (super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentos.template.exportar_acuse', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentos.template.exportar_acuse');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
